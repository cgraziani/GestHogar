<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

if (isset($_GET['estado']) && isset($_GET['id'])) {
    $estado = $_GET['estado'];
    if (in_array($estado, ['disponible', 'mantenimiento', 'ocupada'])) {

        if (in_array($estado, ['disponible', 'mantenimiento'])) {
            $camas_ocupadas = $pdo->prepare("SELECT COUNT(*) FROM camas WHERE id_habitacion=? AND estado='ocupada'");
            $camas_ocupadas->execute([$_GET['id']]);
            if ($camas_ocupadas->fetchColumn() > 0) {
                header('Location: listar.php?toast=No+se+puede+cambiar+el+estado%3A+la+habitaci%C3%B3n+tiene+camas+ocupadas.+Dar%C3%A1+de+baja+al+residente+primero.&tipo=error');
                exit;
            }
        }

        $pdo->prepare('UPDATE habitaciones SET estado=?, updated_by=? WHERE id_habitacion=?')
            ->execute([$estado, uid(), $_GET['id']]);
        $labels = ['disponible' => 'disponible', 'mantenimiento' => 'en+mantenimiento', 'ocupada' => 'ocupada'];
        header('Location: listar.php?toast=Habitaci%C3%B3n+marcada+como+' . $labels[$estado] . '&tipo=ok');
        exit;
    }
}

if (isset($_GET['eliminar'])) {
    $camas_ocupadas = $pdo->prepare("SELECT COUNT(*) FROM camas WHERE id_habitacion=? AND estado='ocupada'");
    $camas_ocupadas->execute([$_GET['eliminar']]);
    if ($camas_ocupadas->fetchColumn() > 0) {
        header('Location: listar.php?toast=No+se+puede+cambiar+el+estado%3A+la+habitaci%C3%B3n+tiene+camas+ocupadas.&tipo=error');
        exit;
    }
    $pdo->prepare("UPDATE habitaciones SET estado='mantenimiento', updated_by=? WHERE id_habitacion=?")
        ->execute([uid(), $_GET['eliminar']]);
    header('Location: listar.php?toast=Habitaci%C3%B3n+puesta+en+mantenimiento&tipo=warn');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_hab'])) {
    $numero = trim($_POST['numero'] ?? '');
    $id_hab = (int)($_POST['id'] ?? 0);

    if ($numero === '') {
        $_SESSION['error_form'] = 'El número de habitación es obligatorio.';
        header('Location: listar.php'); exit;
    }
    if ((int)$_POST['capacidad'] < 1 || (int)$_POST['capacidad'] > 10) {
        $_SESSION['error_form'] = 'La capacidad debe ser entre 1 y 10 camas.';
        header('Location: listar.php'); exit;
    }
    $q = $pdo->prepare('SELECT COUNT(*) FROM habitaciones WHERE numero=? AND eliminado=0' . ($id_hab ? ' AND id_habitacion!=?' : ''));
    $q->execute($id_hab ? [$numero, $id_hab] : [$numero]);
    if ($q->fetchColumn() > 0) {
        $_SESSION['error_form'] = 'Ya existe una habitación con el número "' . htmlspecialchars($numero) . '". Usá otro número.';
        header('Location: listar.php'); exit;
    }

    try {
        if ($id_hab) {
            $pdo->prepare('UPDATE habitaciones SET numero=?,sector=?,piso=?,capacidad=?,habilitada=?,sexo_asignado=?,admite_pareja=?,estado=?,observaciones=?,updated_by=? WHERE id_habitacion=?')
                ->execute([$numero, $_POST['sector'], $_POST['piso'], (int)$_POST['capacidad'],
                           isset($_POST['habilitada']) ? 1 : 0, $_POST['sexo_asignado'] ?? 'mixta',
                           isset($_POST['admite_pareja']) ? 1 : 0,
                           $_POST['estado'], $_POST['observaciones'], uid(), $id_hab]);
            header('Location: listar.php?toast=Habitaci%C3%B3n+modificada+correctamente&tipo=ok');
        } else {
            $pdo->prepare('INSERT INTO habitaciones(numero,sector,piso,capacidad,habilitada,sexo_asignado,admite_pareja,estado,observaciones,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)')
                ->execute([$numero, $_POST['sector'], $_POST['piso'], (int)$_POST['capacidad'],
                           isset($_POST['habilitada']) ? 1 : 0, $_POST['sexo_asignado'] ?? 'mixta',
                           isset($_POST['admite_pareja']) ? 1 : 0,
                           $_POST['estado'], $_POST['observaciones'], uid()]);
            $id_nueva  = $pdo->lastInsertId();
            $capacidad = max(1, (int)$_POST['capacidad']);
            for ($i = 1; $i <= $capacidad; $i++) {
                $pdo->prepare('INSERT INTO camas(id_habitacion,identificador,estado,created_by) VALUES(?,?,?,?)')
                    ->execute([$id_nueva, 'Cama '.chr(64+$i), 'disponible', uid()]);
            }
            header('Location: listar.php?toast=Habitaci%C3%B3n+y+camas+creadas+correctamente&tipo=ok');
        }
    } catch (PDOException $e) {
        $_SESSION['error_form'] = 'Error al guardar la habitación. Verificá los datos e intentá de nuevo.';
        header('Location: listar.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_cama'])) {
    $pdo->prepare('INSERT INTO camas(id_habitacion, identificador, tipo, tiene_barandas, apta_movilidad_reducida, estado, observaciones, created_by) VALUES(?,?,?,?,?,?,?,?)')
        ->execute([$_POST['id_habitacion'], $_POST['identificador'],
                   $_POST['tipo'] ?? 'estandar',
                   isset($_POST['tiene_barandas']) ? 1 : 0,
                   isset($_POST['apta_movilidad_reducida']) ? 1 : 0,
                   'disponible', trim($_POST['obs_cama'] ?? '') ?: null, uid()]);
    header('Location: listar.php?toast=Cama+agregada+correctamente&tipo=ok');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$h  = ['numero' => '', 'sector' => '', 'piso' => '', 'capacidad' => 1, 'estado' => 'disponible',
       'observaciones' => '', 'habilitada' => 1, 'sexo_asignado' => 'mixta', 'admite_pareja' => 0];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM habitaciones WHERE id_habitacion=?');
    $st->execute([$id]);
    $h = $st->fetch();
}

function caracteristicas_cama(array $c) {
    $tipos = ['estandar'=>'Estándar','articulada'=>'Articulada',
              'ortopedica'=>'Ortopédica','electrica'=>'Eléctrica'];
    $p = [$tipos[$c['tipo']] ?? $c['tipo']];
    if (!empty($c['tiene_barandas']))          $p[] = 'con barandas';
    if (!empty($c['apta_movilidad_reducida'])) $p[] = 'apta movilidad reducida';
    if (!empty($c['observaciones']))           $p[] = $c['observaciones'];
    return implode(' · ', $p);
}

function icono_cama(array $c) {
    $i = '';
    if (!empty($c['apta_movilidad_reducida'])) $i .= ' ';
    if ($c['tipo'] !== 'estandar')             $i .= ' ';
    return $i;
}

include '../config/menu.php';

$rows = $pdo->query("SELECT h.*,
        (SELECT GROUP_CONCAT(CONCAT(r.nombre,' ',r.apellido) ORDER BY r.apellido SEPARATOR ', ')
           FROM residentes r
          WHERE r.id_habitacion = h.id_habitacion AND r.estado IN ('activo','pausado')) residentes,
        (SELECT COUNT(*) FROM camas c WHERE c.id_habitacion = h.id_habitacion) total_camas,
        (SELECT COUNT(*) FROM camas c WHERE c.id_habitacion = h.id_habitacion AND c.estado='disponible') camas_disponibles,
        (SELECT COUNT(*) FROM camas c WHERE c.id_habitacion = h.id_habitacion AND c.estado='ocupada') ocupadas,
        (SELECT COUNT(*) FROM residentes r
          WHERE r.id_habitacion = h.id_habitacion AND r.estado IN ('activo','pausado')) residentes_activos
     FROM habitaciones h
     ORDER BY h.numero")->fetchAll();

$camas_por_hab = [];
$camas_all = $pdo->query("SELECT c.*, r.id_residente, r.nombre res_nombre, r.apellido res_apellido, r.estado res_estado, r.foto res_foto
                           FROM camas c
                           LEFT JOIN residentes r ON r.id_cama=c.id_cama AND r.estado IN ('activo','pausado')
                           ORDER BY c.id_habitacion, c.identificador")->fetchAll();
foreach ($camas_all as $c) {
    $camas_por_hab[$c['id_habitacion']][] = $c;
}
?>

<?php if (!empty($_SESSION['error_form'])): ?>
<div style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
<?= htmlspecialchars($_SESSION['error_form']); unset($_SESSION['error_form']); ?>
</div>
<?php endif; ?>

<style>
.hab-resumen{display:grid;grid-template-columns:repeat(4,minmax(150px,1fr));gap:12px;margin:14px 0 22px}
.hab-kpi{background:var(--bg-surface);border:1px solid rgba(127,127,127,.18);border-radius:14px;padding:15px 18px;box-shadow:0 4px 14px rgba(0,0,0,.05)}
.hab-kpi strong{display:block;font-size:28px;line-height:1.1;margin-bottom:4px}.hab-kpi span{font-size:13px;color:var(--text-muted)}
.hab-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:18px;margin-top:16px}
.hab-card{background:var(--bg-surface);border:1px solid rgba(127,127,127,.2);border-radius:16px;padding:18px;box-shadow:0 5px 18px rgba(0,0,0,.06);transition:.18s ease}
.hab-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.10)}
.hab-card__top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.hab-card__numero{font-size:24px;font-weight:800}.hab-card__ubicacion{font-size:13px;color:var(--text-muted);margin-top:3px}
.hab-estado{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700}.hab-estado.disponible{background:#e8f7ee;color:#177245}.hab-estado.parcial{background:#fff4d6;color:#956400}.hab-estado.completa{background:#fdeaea;color:#a93232}.hab-estado.mantenimiento{background:#eceff3;color:#5d6570}
.hab-progreso{height:9px;background:rgba(127,127,127,.16);border-radius:999px;overflow:hidden;margin:14px 0 10px}.hab-progreso>span{display:block;height:100%;background:#4c9b68;border-radius:999px}
.hab-metricas{display:flex;justify-content:space-between;gap:8px;font-size:13px;margin-bottom:14px}.hab-metricas b{font-size:15px}.hab-card__acciones{display:flex;gap:8px;flex-wrap:wrap}
.hab-detalle{background:var(--bg-surface);border:1px solid rgba(127,127,127,.2);border-radius:16px;padding:20px;margin:14px 0 24px}.hab-detalle__cab{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap}.hab-detalle__cab h2{margin:0 0 4px}
.camas-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px;margin-top:18px}.cama-card{border:1px solid rgba(127,127,127,.2);border-radius:14px;padding:16px;background:rgba(127,127,127,.035)}.cama-card.ocupada{border-left:5px solid #c94d4d}.cama-card.disponible{border-left:5px solid #3d9a63}.cama-card.mantenimiento{border-left:5px solid #7c8794}.cama-titulo{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:12px}.cama-titulo strong{font-size:17px}.residente-mini{display:flex;gap:12px;align-items:center;margin-top:10px}.residente-mini img{width:58px;height:58px;object-fit:cover;border-radius:12px;border:1px solid rgba(127,127,127,.2)}.residente-mini__foto{width:58px;height:58px;border-radius:12px;background:rgba(127,127,127,.15);display:flex;align-items:center;justify-content:center;font-size:28px}.residente-mini__info{flex:1}.residente-mini__info b{display:block;font-size:16px}.hab-alerta{margin-top:10px;padding:9px 11px;border-radius:10px;background:#fff4d6;color:#7f5d00;font-size:12px}
@media(max-width:720px){.hab-resumen{grid-template-columns:repeat(2,1fr)}.hab-card__acciones .btn{flex:1;text-align:center}.hab-detalle{padding:15px}}
</style>

<?php
$total_habitaciones = count($rows);
$total_camas_global = 0;
$total_ocupadas_global = 0;
$total_disponibles_global = 0;
foreach ($rows as $rr) {
    $total_camas_global += (int)$rr['total_camas'];
    $total_ocupadas_global += (int)$rr['ocupadas'];
    $total_disponibles_global += (int)$rr['camas_disponibles'];
}

$ver_hab = (int)($_GET['habitacion'] ?? 0);
$hab_sel = null;
if ($ver_hab) {
    foreach ($rows as $rr) {
        if ((int)$rr['id_habitacion'] === $ver_hab) { $hab_sel = $rr; break; }
    }
}

function estado_ocupacion_hab($r) {
    if (!$r['habilitada'] || $r['estado'] === 'mantenimiento') return ['Mantenimiento','mantenimiento'];
    $total = (int)$r['total_camas'];
    $ocup = (int)$r['ocupadas'];
    if ($total <= 0 || $ocup <= 0) return ['Disponible','disponible'];
    if ($ocup < $total) return ['Ocupación parcial','parcial'];
    return ['Completa','completa'];
}
?>

<h1>Habitaciones</h1>

<div class="hab-resumen">
    <div class="hab-kpi"><strong><?= $total_habitaciones ?></strong><span>Habitaciones creadas</span></div>
    <div class="hab-kpi"><strong><?= $total_camas_global ?></strong><span>Camas totales</span></div>
    <div class="hab-kpi"><strong><?= $total_ocupadas_global ?></strong><span>Camas ocupadas</span></div>
    <div class="hab-kpi"><strong><?= $total_disponibles_global ?></strong><span>Camas disponibles</span></div>
</div>

<div style="margin-bottom:16px">
    <button class="btn" onclick="abrirModal(0, null)">+ Crear habitación</button>
</div>

<?php if ($hab_sel):
    [$et_sel,$cl_sel] = estado_ocupacion_hab($hab_sel);
    $camas_sel = $camas_por_hab[$hab_sel['id_habitacion']] ?? [];
?>
<div class="hab-detalle" id="detalle-habitacion">
    <div class="hab-detalle__cab">
        <div>
            <a href="listar.php" style="text-decoration:none;color:var(--text-muted);font-size:13px">← Volver a todas las habitaciones</a>
            <h2>Habitación <?= h($hab_sel['numero']) ?></h2>
            <div style="color:var(--text-muted)"><?= h($hab_sel['sector'] ?: 'Sin sector') ?> · <?= h($hab_sel['piso'] ?: 'Sin piso') ?></div>
        </div>
        <span class="hab-estado <?= $cl_sel ?>"><?= h($et_sel) ?></span>
    </div>

    <div class="hab-metricas" style="max-width:520px;margin-top:16px">
        <span><b><?= (int)$hab_sel['ocupadas'] ?></b> ocupada(s)</span>
        <span><b><?= (int)$hab_sel['camas_disponibles'] ?></b> disponible(s)</span>
        <span><b><?= (int)$hab_sel['total_camas'] ?></b> total</span>
    </div>

    <div class="camas-grid">
    <?php if (!$camas_sel): ?>
        <div class="cama-card disponible"><b>No hay camas cargadas en esta habitación.</b></div>
    <?php endif; ?>
    <?php foreach ($camas_sel as $c):
        $estado_visual = $c['estado'];
        if ($c['res_nombre']) $estado_visual = 'ocupada';
        $texto_estado = $estado_visual === 'ocupada' ? 'Ocupada' : ($estado_visual === 'mantenimiento' ? 'Mantenimiento' : 'Disponible');
    ?>
        <div class="cama-card <?= h($estado_visual) ?>">
            <div class="cama-titulo">
                <strong> <?= h($c['identificador']) ?><?= icono_cama($c) ?></strong>
                <span class="hab-estado <?= $estado_visual === 'ocupada' ? 'completa' : h($estado_visual) ?>"><?= h($texto_estado) ?></span>
            </div>
            <div class="small" style="color:var(--text-muted)"><?= h(caracteristicas_cama($c)) ?></div>

            <?php if ($c['res_nombre']): ?>
                <div class="residente-mini">
                    <?php if (!empty($c['res_foto'])): ?>
                        <img src="<?= BASE_URL . '/' . h($c['res_foto']) ?>" alt="Foto de residente">
                    <?php else: ?>
                        <div class="residente-mini__foto"></div>
                    <?php endif; ?>
                    <div class="residente-mini__info">
                        <b><?= h($c['res_nombre'] . ' ' . $c['res_apellido']) ?></b>
                        <span class="small"><?= $c['res_estado'] === 'pausado' ? 'Servicio suspendido' : 'Residente activo' ?></span><br>
                        <a class="btn" style="margin-top:8px;display:inline-block" href="<?= BASE_URL ?>/residentes/listar.php?ver=<?= (int)$c['id_residente'] ?>">Ver ficha del residente</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>

    <?php if ((int)$hab_sel['residentes_activos'] !== (int)$hab_sel['ocupadas']): ?>
        <div class="hab-alerta">Aviso: hay <?= (int)$hab_sel['residentes_activos'] ?> residente(s) asignado(s), pero <?= (int)$hab_sel['ocupadas'] ?> cama(s) figuran como ocupadas. Conviene revisar la asignación.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php
$rows_activas = array_filter($rows, fn($r) => $r['estado'] !== 'mantenimiento');
$rows_mant    = array_filter($rows, fn($r) => $r['estado'] === 'mantenimiento');
?>

<div class="hab-grid">
<?php foreach ($rows_activas as $r):
    [$et,$cl] = estado_ocupacion_hab($r);
    $total = max(1,(int)$r['total_camas']);
    $ocup  = (int)$r['ocupadas'];
    $porc  = min(100, round(($ocup / $total) * 100));
?>
    <div class="hab-card">
        <div class="hab-card__top">
            <div>
                <div class="hab-card__numero"> Habitación <?= h($r['numero']) ?></div>
                <div class="hab-card__ubicacion"><?= h($r['sector'] ?: 'Sin sector') ?> · <?= h($r['piso'] ?: 'Sin piso') ?></div>
            </div>
            <span class="hab-estado <?= $cl ?>"><?= h($et) ?></span>
        </div>
        <div class="hab-progreso" title="<?= $porc ?>% de ocupación"><span style="width:<?= $porc ?>%"></span></div>
        <div class="hab-metricas">
            <span><b><?= $ocup ?></b> ocupada(s)</span>
            <span><b><?= (int)$r['camas_disponibles'] ?></b> disponible(s)</span>
            <span><b><?= (int)$r['total_camas'] ?></b> total</span>
        </div>
        <?php if ($r['residentes']): ?>
            <div class="small" style="margin-bottom:12px"><b>Residentes:</b> <?= h($r['residentes']) ?></div>
        <?php endif; ?>
        <div class="hab-card__acciones">
            <a class="btn" href="?habitacion=<?= (int)$r['id_habitacion'] ?>#detalle-habitacion">Ver</a>
            <button class="btn gray" onclick='abrirModal(<?= (int)$r['id_habitacion'] ?>, <?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Modificar</button>
            <button class="btn gray" onclick="abrirModalCama(<?= (int)$r['id_habitacion'] ?>, '<?= h($r['numero']) ?>')">+ Cama</button>
            <a class="btn orange" href="?estado=mantenimiento&id=<?= (int)$r['id_habitacion'] ?>"
               onclick="return confirmarLink(this,'¿Marcar la habitación <?= h($r['numero']) ?> como en mantenimiento?','Confirmar','orange')">
               Mantenimiento
            </a>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if (!empty($rows_mant)): ?>
<details style="margin-top:28px" <?= isset($_GET['ver_mant']) ? 'open' : '' ?>>
    <summary style="cursor:pointer;font-size:16px;font-weight:700;padding:12px 0;color:var(--text-muted);display:flex;align-items:center;gap:10px">
         Habitaciones en mantenimiento
        <span style="background:#e67e22;color:#fff;border-radius:10px;padding:2px 10px;font-size:12px"><?= count($rows_mant) ?></span>
    </summary>
    <div style="background:var(--bg-surface);border:1px solid rgba(127,127,127,.2);border-radius:12px;padding:16px;margin-top:8px">
        <table class="no-datatable" style="width:100%">
            <thead><tr><th>Habitación</th><th>Sector / Piso</th><th>Camas</th><th>Observaciones</th><th>Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($rows_mant as $r): ?>
                <tr>
                    <td><b>Hab. <?= h($r['numero']) ?></b></td>
                    <td><?= h($r['sector'] ?: '') ?> / <?= h($r['piso'] ?: '') ?></td>
                    <td><?= (int)$r['total_camas'] ?> camas</td>
                    <td><span class="small"><?= h($r['observaciones'] ?: '') ?></span></td>
                    <td style="display:flex;gap:8px;flex-wrap:wrap">
                        <a class="btn" href="?habitacion=<?= (int)$r['id_habitacion'] ?>#detalle-habitacion">Ver</a>
                        <button class="btn gray" onclick='abrirModal(<?= (int)$r['id_habitacion'] ?>, <?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>Modificar</button>
                        <a class="btn green"
                           href="?estado=disponible&id=<?= (int)$r['id_habitacion'] ?>"
                           onclick="return confirmarLink(this,'¿Marcar la habitación <?= h($r['numero']) ?> como disponible?','Confirmar','green')">
                            Marcar disponible
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
<?php endif; ?>

<div id="modal-habitacion"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;
                width:100%;max-width:480px;position:relative;color:var(--text-main)">
        <button onclick="cerrarModal()"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer"></button>
        <h2 id="modal-titulo" style="margin-top:0">Crear habitación</h2>
        <form method="post" id="form-habitacion">
            <input type="hidden" name="accion_hab" value="1">
            <input type="hidden" name="id" id="f-id">
            <label>Número de habitación <span style="color:red">*</span></label>
            <input name="numero" id="f-numero" required>
            <label>Sector</label>
            <input name="sector" id="f-sector">
            <label>Piso</label>
            <input name="piso" id="f-piso">
            <label>Capacidad (cantidad de camas)</label>
            <input name="capacidad" id="f-capacidad" type="number" min="1" max="10" value="1">

            <label>Sexo de los residentes</label>
            <select name="sexo_asignado" id="f-sexo">
                <option value="mixta">Mixta (sin restricción)</option>
                <option value="F">Solo mujeres</option>
                <option value="M">Solo varones</option>
            </select>

            <label style="display:flex;align-items:center;gap:8px;margin-top:10px">
                <input type="checkbox" name="admite_pareja" id="f-pareja" style="width:auto">
                Admite pareja (matrimonio que comparte habitación)
            </label>

            <label style="display:flex;align-items:center;gap:8px;margin-top:10px">
                <input type="checkbox" name="habilitada" id="f-habilitada" style="width:auto" checked>
                Habitación habilitada
            </label>

            <label>Estado</label>
            <select name="estado" id="f-estado">
                <option value="disponible">Disponible</option>
                <option value="ocupada">Ocupada</option>
                <option value="mantenimiento">Mantenimiento</option>
            </select>
            <label>Observaciones</label>
            <input name="observaciones" id="f-obs" placeholder="Observaciones opcionales">
            <br><br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
        </form>
    </div>
</div>

<div id="modal-cama"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;
                width:100%;max-width:400px;position:relative;color:var(--text-main)">
        <button onclick="document.getElementById('modal-cama').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer"></button>
        <h2 id="modal-cama-titulo" style="margin-top:0">Agregar cama</h2>
        <form method="post">
            <input type="hidden" name="accion_cama" value="1">
            <input type="hidden" name="id_habitacion" id="f-cama-hab">
            <label>Identificador de cama</label>
            <input name="identificador" required>

            <label>Tipo de cama</label>
            <select name="tipo">
                <option value="estandar">Estándar</option>
                <option value="articulada">Articulada</option>
                <option value="ortopedica">Ortopédica</option>
                <option value="electrica">Eléctrica</option>
            </select>

            <label style="display:flex;align-items:center;gap:8px;margin-top:10px">
                <input type="checkbox" name="tiene_barandas" style="width:auto"> Tiene barandas
            </label>
            <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="apta_movilidad_reducida" style="width:auto"> Apta para movilidad reducida
            </label>

            <label>Observaciones</label>
            <input name="obs_cama">
            <br><br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="document.getElementById('modal-cama').style.display='none'">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModal(id, datos) {
    document.getElementById('modal-habitacion').style.display = 'flex';
    document.getElementById('f-id').value         = id || '';
    document.getElementById('f-numero').value     = datos ? datos.numero       : '';
    document.getElementById('f-sector').value     = datos ? datos.sector       : '';
    document.getElementById('f-piso').value       = datos ? datos.piso         : '';
    document.getElementById('f-capacidad').value  = datos ? datos.capacidad    : 1;
    document.getElementById('f-estado').value     = datos ? datos.estado       : 'disponible';
    document.getElementById('f-sexo').value        = datos ? datos.sexo_asignado : 'mixta';
    document.getElementById('f-pareja').checked    = datos ? datos.admite_pareja == 1 : false;
    document.getElementById('f-habilitada').checked = datos ? datos.habilitada == 1 : true;
    document.getElementById('f-obs').value        = datos ? datos.observaciones: '';
    document.getElementById('modal-titulo').textContent = id ? 'Modificar habitación' : 'Crear habitación';
}
function cerrarModal() {
    document.getElementById('modal-habitacion').style.display = 'none';
}
function abrirModalCama(id_hab, numero) {
    document.getElementById('modal-cama').style.display = 'flex';
    document.getElementById('f-cama-hab').value = id_hab;
    document.getElementById('modal-cama-titulo').textContent = 'Agregar cama a Hab. ' + numero;
}
document.getElementById('modal-habitacion').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});
document.getElementById('modal-cama').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
<?php if ($id && $h): ?>
abrirModal(<?= $id ?>, <?= json_encode($h) ?>);
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
