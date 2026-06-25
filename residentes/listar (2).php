<?php
// ============================================================
// SECCIÓN 1: SEGURIDAD Y CONFIGURACIÓN
// Permite acceso a admin, cuidadoras y asistentes.
// Carga la base de datos y define funciones de estado
// usadas en el historial de medicación.
// ============================================================
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

// Convierte el código de estado de medicación a texto legible
function estado_label($e) {
    $map = [
        'administrado' => 'Administrado',
        'realizada'    => 'Realizada',
        'no_pudo'      => 'No se pudo',
        'no_dado'      => 'No administrado',
        'no_realizada' => 'No realizada',
        'pendiente'    => 'Pendiente',
        ''             => 'Pendiente',
        null           => 'Pendiente'
    ];
    return $map[$e] ?? $e;
}

// Devuelve la clase CSS según el estado de administración
function estado_class($e) {
    if ($e === 'administrado' || $e === 'realizada') return 'estado-ok';
    if ($e === 'no_pudo')                            return 'estado-alerta';
    if ($e === 'no_dado'  || $e === 'no_realizada')  return 'estado-problema';
    return 'estado-pendiente';
}

// ============================================================
// SECCIÓN 2: ACCIONES (solo admin)
// Cambia el estado de un residente (activo/pausado/baja)
// o lo manda a la papelera (baja lógica, no borra el registro).
// Redirige con toast para informar al usuario del resultado.
// ============================================================
if (isset($_GET['accion']) && es_admin()) {
    $pdo->prepare('UPDATE residentes SET estado=? WHERE id_residente=?')
        ->execute([$_GET['accion'], $_GET['id']]);
    header('Location: listar.php?toast=Estado+actualizado+correctamente&tipo=ok');
    exit;
}
if (isset($_GET['eliminar']) && es_admin()) {
    $pdo->prepare('UPDATE residentes SET eliminado=1 WHERE id_residente=?')
        ->execute([$_GET['eliminar']]);
    $pdo->prepare("INSERT INTO papelera(tabla,registro_id,accion,usuario) VALUES('residentes',?,'eliminado',?)")
        ->execute([$_GET['eliminar'], $_SESSION['usuario']['email']]);
    header('Location: listar.php?toast=Residente+enviado+a+la+papelera&tipo=warn');
    exit;
}


// ============================================================
// ELIMINAR DEFINITIVAMENTE DESDE DADOS DE BAJA
// Primero intenta borrar el residente. Si MySQL no lo permite por
// historial relacionado, queda oculto con eliminado=1 y ya no aparece
// en Dados de baja ni se puede reincorporar desde el sistema.
// ============================================================
if (isset($_GET['eliminar_baja']) && es_admin()) {
    $id = (int)$_GET['eliminar_baja'];
    if ($id > 0) {
        try {
            $pdo->prepare('DELETE FROM residentes WHERE id_residente=?')->execute([$id]);
        } catch(Exception $e) {
            $pdo->prepare("UPDATE residentes SET eliminado=1, estado='baja' WHERE id_residente=?")->execute([$id]);
        }
        $pdo->prepare("DELETE FROM papelera WHERE tabla='residentes' AND registro_id=?")->execute([$id]);
        header('Location: listar.php?ver_bajas=1&toast=Residente+eliminado+definitivamente&tipo=warn');
        exit;
    }
}

// ============================================================
// SECCIÓN 3: MENÚ DE NAVEGACIÓN
// ============================================================
include '../config/menu.php';

// ============================================================
// SECCIÓN 4: VISTA DE FICHA COMPLETA (?ver=ID)
// Muestra todos los datos del residente con jerarquía visual:
// - Encabezado: foto + nombre + estado como badge destacado
// - Tabla horizontal: datos cortos (DNI, fecha, habitación, etc.)
// - Bloque aparte: observaciones médicas (necesitan más espacio)
// - Tabla: medicamentos y recetas activas
// - Tabla: historial de medicación últimos 7 días
// Las tablas de detalle usan "datatable" para búsqueda.
// ============================================================
if (isset($_GET['ver'])) {

    // Trae los datos del residente con su habitación
    $st = $pdo->prepare('SELECT r.*,h.numero habitacion,h.sector,h.piso,h.cama,h.estado estado_habitacion
                         FROM residentes r
                         LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
                         WHERE r.id_residente=?');
    $st->execute([$_GET['ver']]);
    $r = $st->fetch();

    // Trae los medicamentos y recetas activas del residente
    $med = $pdo->prepare("SELECT mr.*,m.nombre med,m.presentacion,
                                 rec.diagnostico,rec.fecha_inicio r_inicio,rec.fecha_fin r_fin,rec.foto_receta
                          FROM medicacion_residente mr
                          JOIN medicamentos m ON m.id_medicamento=mr.id_medicamento
                          LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
                          WHERE mr.id_residente=? AND mr.eliminado=0
                          ORDER BY rec.fecha_inicio DESC, mr.horario");
    $med->execute([$r['id_residente']]);
    $med = $med->fetchAll();

    // Trae el historial de administración de los últimos 7 días
    $hist = $pdo->prepare("SELECT am.*,m.nombre med,m.presentacion,u.nombre cuidadora,
                                  mr.dosis,mr.horario,rec.diagnostico
                           FROM administracion_medicacion am
                           JOIN medicamentos m ON m.id_medicamento=am.id_medicamento
                           LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario
                           LEFT JOIN medicacion_residente mr ON mr.id_plan=am.id_plan
                           LEFT JOIN recetas_medicas rec ON rec.id_receta=mr.id_receta
                           WHERE am.id_residente=?
                           AND am.fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                           ORDER BY am.fecha_hora DESC");
    $hist->execute([$r['id_residente']]);
    $hist = $hist->fetchAll();
?>

<h1>Ficha del residente</h1>

<!-- ── ENCABEZADO: foto + nombre + estado destacado arriba ── -->
<div class="card">
    <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">

        <!-- Foto del residente (si tiene) -->
        <?php if ($r['foto']): ?>
            <img src="<?= BASE_URL . '/' . $r['foto'] ?>"
                 style="width:90px;height:90px;object-fit:cover;border-radius:10px;border:2px solid #ddd">
        <?php endif; ?>

        <div style="flex:1">
            <!-- Nombre y estado como badge en la misma línea -->
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
                <h2 style="margin:0"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></h2>
                <!-- Estado como badge visual destacado, fuera de la tabla -->
                <span class="pill estado-<?= $r['estado'] ?>"><?= $r['estado'] ?></span>
            </div>

            <!-- Tabla horizontal con datos cortos (no-datatable: es de 1 sola fila) -->
            <table class="no-datatable" style="width:100%">
                <thead>
                    <tr>
                        <th>DNI</th>
                        <th>Fecha de nacimiento</th>
                        <th>Habitación</th>
                        <th>Movilidad autónoma</th>
                        <th>Familiar</th>
                        <th>Teléfono familiar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= htmlspecialchars($r['dni']) ?></td>
                        <td><?= $r['fecha_nacimiento'] ?></td>
                        <td>
                            <?php if ($r['habitacion']): ?>
                                Hab. <?= $r['habitacion'] ?> · <?= $r['sector'] ?><br>
                                <span class="small"><?= $r['piso'] ?> · <?= $r['cama'] ?></span>
                            <?php else: ?>
                                <span class="small">Sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $r['movilidad_autonoma'] ? '✅ Sí' : '❌ No' ?></td>
                        <td><?= htmlspecialchars($r['contacto_familiar']) ?></td>
                        <td><?= htmlspecialchars($r['telefono_familiar']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── OBSERVACIONES MÉDICAS: bloque aparte con espacio amplio ── -->
<!-- Separado de la tabla porque necesita más espacio y contexto visual -->
<div class="card">
    <h3 style="margin-top:0">Observaciones médicas</h3>
    <p style="line-height:1.7;white-space:pre-wrap">
        <?= $r['obs_medicas'] ? htmlspecialchars($r['obs_medicas']) : '<em style="color:#aaa">Sin observaciones registradas.</em>' ?>
    </p>
</div>

<!-- ── MEDICAMENTOS Y RECETAS ACTIVAS ── -->
<h2>Medicamentos y recetas</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Tratamiento / receta</th>
            <th>Medicamento</th>
            <th>Dosis</th>
            <th>Horario</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Indicación</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($med as $m): ?>
        <tr>
            <td>
                <?= htmlspecialchars($m['diagnostico'] ?: '-') ?>
                <?php if ($m['foto_receta']): ?>
                    <br><a class="btn blue" target="_blank"
                       href="<?= BASE_URL . '/' . $m['foto_receta'] ?>">Ver foto receta</a>
                <?php endif; ?>
            </td>
            <td><?= $m['med'] ?> <?= $m['presentacion'] ?></td>
            <td><?= $m['dosis'] ?></td>
            <td><b><?= $m['horario'] ?></b></td>
            <td><?= $m['fecha_inicio'] ?></td>
            <td><?= $m['fecha_fin'] ?: 'Permanente' ?></td>
            <td><?= htmlspecialchars($m['indicacion']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- ── HISTORIAL DE MEDICACIÓN — ÚLTIMOS 7 DÍAS ── -->
<h2>Historial de medicación — últimos 7 días</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Medicamento</th>
            <th>Dosis</th>
            <th>Tratamiento</th>
            <th>Estado</th>
            <th>Registrado por</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($hist as $h): ?>
        <tr>
            <td><?= $h['fecha_hora'] ?></td>
            <td><?= $h['med'] ?> <?= $h['presentacion'] ?></td>
            <td><?= $h['dosis'] ?></td>
            <td><?= htmlspecialchars($h['diagnostico'] ?: '-') ?></td>
            <td>
                <div class="estado-cuadro <?= estado_class($h['estado_adm']) ?>">
                    <?= estado_label($h['estado_adm']) ?>
                </div>
            </td>
            <td><?= $h['cuidadora'] ?: '-' ?></td>
            <td><?= htmlspecialchars($h['observaciones']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<br>
<!-- Botones de acción al pie de la ficha (solo admin) -->
<?php if (es_admin()): ?>
    <a class="btn" href="crear.php?id=<?= $r['id_residente'] ?>">Modificar datos</a>
<?php endif; ?>
<a class="btn gray" href="listar.php">Volver al listado</a>

<?php
    include '../config/footer.php';
    exit;
}

// ============================================================
// SECCIÓN 5: LISTADO GENERAL DE RESIDENTES
// Muestra todos los residentes en una tabla con DataTables.
// Columnas reducidas: sin "Datos médicos" (era demasiado texto)
// Botones reducidos: solo Ver + acciones de estado (admin)
// Se eliminó la columna de observaciones del listado porque
// es información extensa que corresponde a la ficha individual.
// ============================================================
$rows = $pdo->query('SELECT r.*,h.numero habitacion,h.sector
                     FROM residentes r
                     LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
                     WHERE r.eliminado=0 AND r.estado != \'baja\'
                     ORDER BY r.apellido,r.nombre')->fetchAll();
?>

<h1>Residentes</h1>

<!-- ============================================================
     Vista de residentes dados de baja (?ver_bajas=1)
     Muestra solo residentes con estado="baja" con botón
     para reincorporarlos. Accesible desde el botón de arriba.
     ============================================================ -->
<?php if (isset($_GET['ver_bajas']) && es_admin()): ?>
    <?php
    $bajas = $pdo->query("SELECT r.*,h.numero habitacion FROM residentes r
                          LEFT JOIN habitaciones h ON h.id_habitacion=r.id_habitacion
                          WHERE r.eliminado=0 AND r.estado='baja'
                          ORDER BY r.apellido,r.nombre")->fetchAll();
    ?>
    <h2>Residentes dados de baja</h2>
    <p><a class="btn gray" href="listar.php">← Volver al listado activo</a></p>
    <?php if (empty($bajas)): ?>
        <div class="card"><p>No hay residentes dados de baja.</p></div>
    <?php else: ?>
    <table class="datatable">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>DNI</th>
                <th>Habitación</th>
                <th>Familiar</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($bajas as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?></td>
                <td><?= $b['dni'] ?></td>
                <td><?= $b['habitacion'] ? 'Hab. ' . $b['habitacion'] : '—' ?></td>
                <td><?= htmlspecialchars($b['contacto_familiar']) ?></td>
                <td>
                    <a class="btn blue" href="?ver=<?= $b['id_residente'] ?>">Ver ficha</a>
                    <a class="btn green"
                       href="?accion=activo&id=<?= $b['id_residente'] ?>"
                       onclick="return confirm('¿Reincorporar a <?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?>?')">
                       Reincorporar
                    </a>
                    <a class="btn red"
                       href="?eliminar_baja=<?= $b['id_residente'] ?>"
                       onclick="return confirm('¿Eliminar definitivamente a <?= htmlspecialchars($b['nombre'] . ' ' . $b['apellido']) ?>? Esta acción no se puede deshacer.')">
                       Eliminar
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php include '../config/footer.php'; exit; ?>
<?php endif; ?>

<!-- Botones de acción principales -->
<?php if (es_admin()): ?>
    <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
        <a class="btn" href="crear.php">+ Crear residente</a>
        <a class="btn gray" href="?ver_bajas=1">Ver residentes dados de baja</a>
    </div>
<?php else: ?>
    <div class="card">
        <b>Solo lectura:</b> podés consultar la ficha completa de cada residente,
        pero no podés crear ni modificar residentes.
    </div>
<?php endif; ?>

<!-- Tabla de residentes con DataTables -->
<!-- Columna "Datos médicos" eliminada del listado: es extensa y
     corresponde a la ficha individual (Ver ficha completa) -->
<table class="datatable">
    <thead>
        <tr>
            <th>Foto</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Habitación</th>
            <th>Familiar</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <!-- Foto en miniatura -->
            <td>
                <?php if ($r['foto']): ?>
                    <img src="<?= BASE_URL . '/' . $r['foto'] ?>"
                         style="width:40px;height:40px;object-fit:cover;border-radius:6px">
                <?php else: ?>
                    <span style="color:#ccc">—</span>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?></td>
            <td><?= $r['dni'] ?></td>
            <td>
                <?= $r['habitacion']
                    ? 'Hab. ' . $r['habitacion'] . ' · ' . $r['sector']
                    : '<span class="small" style="color:#aaa">Sin asignar</span>' ?>
            </td>
            <td>
                <?= htmlspecialchars($r['contacto_familiar']) ?><br>
                <span class="small"><?= htmlspecialchars($r['telefono_familiar']) ?></span>
            </td>
            <!-- Estado como badge, alineado al centro -->
            <td><span class="pill estado-<?= $r['estado'] ?>"><?= $r['estado'] ?></span></td>
            <td>
                <!-- Ver ficha: disponible para todos los roles (azul = navegación) -->
                <a class="btn blue" href="?ver=<?= $r['id_residente'] ?>">Ver ficha</a>

                <?php if (es_admin()): ?>
                    <!-- Modificar: acción neutra (sin color especial) -->
                    <a class="btn" href="crear.php?id=<?= $r['id_residente'] ?>">Modificar</a>

                    <?php if ($r['estado'] === 'activo'): ?>
                        <!-- Pausar: naranja = advertencia media -->
                        <a class="btn orange"
                           href="?accion=pausado&id=<?= $r['id_residente'] ?>"
                           onclick="return confirm('¿Pausar a este residente?')">Pausar</a>
                        <!-- Dar de baja: rojo = acción destructiva (según semiótica del profesor) -->
                        <a class="btn red"
                           href="?accion=baja&id=<?= $r['id_residente'] ?>"
                           onclick="return confirm('¿Dar de baja a <?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?>?')">Dar de baja</a>

                    <?php elseif ($r['estado'] === 'pausado'): ?>
                        <!-- Activar: verde = acción positiva -->
                        <a class="btn green"
                           href="?accion=activo&id=<?= $r['id_residente'] ?>">Activar</a>
                        <!-- Dar de baja: rojo = acción destructiva -->
                        <a class="btn red"
                           href="?accion=baja&id=<?= $r['id_residente'] ?>"
                           onclick="return confirm('¿Dar de baja a <?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido']) ?>?')">Dar de baja</a>

                    <?php elseif ($r['estado'] === 'baja'): ?>
                        <!-- Reincorporar: verde = acción positiva -->
                        <a class="btn green"
                           href="?accion=activo&id=<?= $r['id_residente'] ?>">Reincorporar</a>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>
