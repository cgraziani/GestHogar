<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_login();

const TIPOS_HOGAR = [
    'cama'          => 'Cama',
    'baranda'       => 'Baranda',
    'mobiliario'    => 'Mobiliario',
    'habitacion'    => 'Habitación',
    'plomeria'      => 'Plomería / caños',
    'electricidad'  => 'Electricidad',
    'climatizacion' => 'Calefacción / refrigeración',
    'faltante'      => 'Elemento faltante',
    'limpieza'      => 'Limpieza',
    'otro'          => 'Otro',
];

const ESTADOS_HOGAR = [
    'pendiente'  => ['Pendiente',  'pill-aviso'],
    'en_curso'   => ['En curso',   'pill-neutro'],
    'resuelto'   => ['Resuelto',   'pill-ok'],
    'descartado' => ['Descartado', 'pill-neutro'],
];

const PRIORIDADES = [
    'alta'  => ['Alta',  'pill-mal'],
    'media' => ['Media', 'pill-aviso'],
    'baja'  => ['Baja',  'pill-neutro'],
];

function volver_hogar($mensaje, $tipo = 'ok') {
    header('Location: hogar.php?toast=' . urlencode($mensaje) . '&tipo=' . $tipo);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo'])) {
    $titulo = trim($_POST['titulo'] ?? '');
    $tipo   = $_POST['tipo'] ?? 'otro';
    $prio   = $_POST['prioridad'] ?? 'media';

    if ($titulo === '') volver_hogar('Poné un título para el problema.', 'error');
    if (!array_key_exists($tipo, TIPOS_HOGAR)) $tipo = 'otro';
    if (!array_key_exists($prio, PRIORIDADES)) $prio = 'media';

    $pdo->prepare("INSERT INTO incidentes_hogar
            (tipo, titulo, descripcion, id_habitacion, id_cama, ubicacion,
             prioridad, estado, reportado_por, fecha_reporte, asignado_a)
            VALUES (?,?,?,?,?,?,?,'pendiente',?,NOW(),?)")
        ->execute([
            $tipo, $titulo, trim($_POST['descripcion'] ?? '') ?: null,
            $_POST['id_habitacion'] ?: null,
            $_POST['id_cama'] ?: null,
            trim($_POST['ubicacion'] ?? '') ?: null,
            $prio, uid(),
            trim($_POST['asignado_a'] ?? '') ?: null,
        ]);

    volver_hogar('Problema registrado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $id     = (int)$_POST['id_incidente_hogar'];
    $estado = $_POST['estado'] ?? 'pendiente';
    $acc    = trim($_POST['acciones'] ?? '');

    if (!array_key_exists($estado, ESTADOS_HOGAR)) $estado = 'pendiente';

    if (in_array($estado, ['resuelto', 'descartado'], true) && mb_strlen($acc) < 5) {
        volver_hogar('Para cerrar el problema hay que contar qué se hizo.', 'error');
    }

    $cerrado = in_array($estado, ['resuelto', 'descartado'], true);
    $pdo->prepare("UPDATE incidentes_hogar
                   SET estado=?, acciones=?, asignado_a=?,
                       fecha_resolucion = " . ($cerrado ? 'NOW()' : 'NULL') . ",
                       resuelto_por = " . ($cerrado ? '?' : 'NULL') . "
                   WHERE id_incidente_hogar=?")
        ->execute($cerrado
            ? [$estado, $acc ?: null, trim($_POST['asignado_a'] ?? '') ?: null, uid(), $id]
            : [$estado, $acc ?: null, trim($_POST['asignado_a'] ?? '') ?: null, $id]);

    volver_hogar('Seguimiento actualizado');
}

include '../config/menu.php';

$habitaciones = $pdo->query("SELECT id_habitacion, numero, sector FROM habitaciones ORDER BY numero")->fetchAll();
$camas        = $pdo->query("SELECT c.id_cama, c.identificador, h.numero hab
    FROM camas c LEFT JOIN habitaciones h ON h.id_habitacion=c.id_habitacion
    ORDER BY h.numero, c.identificador")->fetchAll();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if (array_key_exists($filtro_estado, ESTADOS_HOGAR)) $where = ' AND ih.estado = ' . $pdo->quote($filtro_estado);

$items = $pdo->query("SELECT ih.*, h.numero hab_numero, h.sector hab_sector,
        c.identificador cama_id, ur.nombre reporto, us.nombre resolvio
    FROM incidentes_hogar ih
    LEFT JOIN habitaciones h ON h.id_habitacion = ih.id_habitacion
    LEFT JOIN camas c        ON c.id_cama       = ih.id_cama
    LEFT JOIN usuarios ur    ON ur.id_usuario   = ih.reportado_por
    LEFT JOIN usuarios us    ON us.id_usuario   = ih.resuelto_por
    WHERE 1=1 $where
    ORDER BY FIELD(ih.estado,'pendiente','en_curso','resuelto','descartado'),
             FIELD(ih.prioridad,'alta','media','baja'), ih.fecha_reporte DESC")->fetchAll();

$abiertos = $pdo->query("SELECT
        SUM(estado='pendiente') pendientes,
        SUM(estado='en_curso') en_curso,
        SUM(estado='pendiente' AND prioridad='alta') urgentes
    FROM incidentes_hogar")->fetch();
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:8px">
    <h1 style="margin:0">Incidentes del hogar</h1>
    <button class="btn" onclick="document.getElementById('modal-hogar').style.display='flex'">+ Registrar problema</button>
</div>

<div class="grid">
    <div class="card" style="text-align:center">
        <div class="stat"><?= (int)$abiertos['pendientes'] ?></div>
        <b>Pendientes</b>
    </div>
    <div class="card" style="text-align:center">
        <div class="stat"><?= (int)$abiertos['en_curso'] ?></div>
        <b>En curso</b>
    </div>
    <div class="card" style="text-align:center">
        <div class="stat"><?= (int)$abiertos['urgentes'] ?></div>
        <b>Urgentes sin atender</b>
    </div>
</div>

<div class="card no-imprimir">
    <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
        <div class="campo">
            <label style="font-size:13px;font-weight:600">Estado</label>
            <select name="estado">
                <option value="">Todos</option>
                <?php foreach (ESTADOS_HOGAR as $k => [$et, $cl]): ?>
                    <option value="<?= $k ?>" <?= $filtro_estado === $k ? 'selected' : '' ?>><?= $et ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn gray">Filtrar</button>
    </form>
</div>

<?php if (empty($items)): ?>
    <div class="card" style="text-align:center;color:var(--text-muted)">
        <b>No hay problemas registrados<?= $filtro_estado ? ' con ese estado' : '' ?>.</b>
    </div>
<?php else: ?>
<table class="datatable">
    <thead><tr>
        <th>Reportado</th><th>Tipo</th><th>Problema</th><th>Dónde</th>
        <th>Prioridad</th><th>Estado</th><th>Seguimiento</th><th>Reportó</th>
        <?php if (es_admin()): ?><th></th><?php endif; ?>
    </tr></thead>
    <tbody>
    <?php foreach ($items as $x):
        [$et_estado, $cl_estado] = ESTADOS_HOGAR[$x['estado']] ?? [$x['estado'], 'pill-neutro'];
        [$et_prio,   $cl_prio]   = PRIORIDADES[$x['prioridad']] ?? [$x['prioridad'], 'pill-neutro'];

        $donde = [];
        if ($x['hab_numero']) $donde[] = 'Hab. ' . $x['hab_numero'] . ($x['hab_sector'] ? ' · ' . $x['hab_sector'] : '');
        if ($x['cama_id'])    $donde[] = $x['cama_id'];
        if ($x['ubicacion'])  $donde[] = $x['ubicacion'];
    ?>
        <tr>
            <td><span class="small"><?= date('d/m/Y H:i', strtotime($x['fecha_reporte'])) ?></span></td>
            <td><span class="badge"><?= h(TIPOS_HOGAR[$x['tipo']] ?? $x['tipo']) ?></span></td>
            <td>
                <b><?= h($x['titulo']) ?></b>
                <?php if ($x['descripcion']): ?><br><span class="small"><?= h($x['descripcion']) ?></span><?php endif; ?>
            </td>
            <td><span class="small"><?= h($donde ? implode(' · ', $donde) : '') ?></span></td>
            <td><span class="pill <?= $cl_prio ?>"><?= h($et_prio) ?></span></td>
            <td><span class="pill <?= $cl_estado ?>"><?= h($et_estado) ?></span></td>
            <td>
                <?php if ($x['asignado_a']): ?><span class="small">A cargo: <?= h($x['asignado_a']) ?></span><br><?php endif; ?>
                <?php if ($x['acciones']): ?><span class="small"><?= h($x['acciones']) ?></span><br><?php endif; ?>
                <?php if ($x['fecha_resolucion']): ?>
                    <span class="small">Cerrado <?= date('d/m/Y', strtotime($x['fecha_resolucion'])) ?>
                    <?= $x['resolvio'] ? ' por ' . h($x['resolvio']) : '' ?></span>
                <?php endif; ?>
            </td>
            <td><span class="small"><?= h($x['reporto'] ?: '') ?></span></td>
            <?php if (es_admin()): ?>
            <td>
                <button class="btn" onclick='abrirSeguimiento(<?= json_encode([
                    "id" => (int)$x["id_incidente_hogar"],
                    "titulo" => $x["titulo"],
                    "estado" => $x["estado"],
                    "acciones" => $x["acciones"] ?? "",
                    "asignado" => $x["asignado_a"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Seguimiento</button>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div id="modal-hogar" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:24px 28px;width:100%;max-width:620px;position:relative;max-height:90vh;overflow-y:auto">
        <button onclick="document.getElementById('modal-hogar').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">×</button>
        <h3 style="margin:0 0 16px">Registrar problema del hogar</h3>
        <form method="post">
            <input type="hidden" name="nuevo" value="1">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="campo">
                    <label style="font-size:13px;font-weight:600">Tipo *</label>
                    <select name="tipo" required>
                        <?php foreach (TIPOS_HOGAR as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label style="font-size:13px;font-weight:600">Prioridad</label>
                    <select name="prioridad">
                        <option value="alta">Alta</option>
                        <option value="media" selected>Media</option>
                        <option value="baja">Baja</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">¿Qué pasó? *</label>
                <input name="titulo" required style="width:100%;box-sizing:border-box">
            </div>
            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Detalle</label>
                <textarea name="descripcion" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div class="campo">
                    <label style="font-size:13px;font-weight:600">Habitación</label>
                    <select name="id_habitacion" class="buscador">
                        <option value="">No aplica</option>
                        <?php foreach ($habitaciones as $hh): ?>
                            <option value="<?= $hh['id_habitacion'] ?>">Hab. <?= h($hh['numero']) ?>, <?= h($hh['sector']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label style="font-size:13px;font-weight:600">Cama</label>
                    <select name="id_cama" class="buscador">
                        <option value="">No aplica</option>
                        <?php foreach ($camas as $cc): ?>
                            <option value="<?= $cc['id_cama'] ?>">Hab. <?= h($cc['hab']) ?>, <?= h($cc['identificador']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div class="campo">
                    <label style="font-size:13px;font-weight:600">Otra ubicación</label>
                    <input name="ubicacion">
                </div>
                <div class="campo">
                    <label style="font-size:13px;font-weight:600">A cargo de</label>
                    <input name="asignado_a">
                </div>
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Registrar</button>
                <button type="button" class="btn gray" onclick="document.getElementById('modal-hogar').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<?php if (es_admin()): ?>
<div id="modal-seg" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:24px 28px;width:100%;max-width:520px;position:relative">
        <button onclick="document.getElementById('modal-seg').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">×</button>
        <h3 style="margin:0 0 6px">Seguimiento</h3>
        <p id="seg-titulo" class="small" style="margin-bottom:16px"></p>
        <form method="post">
            <input type="hidden" name="actualizar" value="1">
            <input type="hidden" name="id_incidente_hogar" id="seg-id">

            <label>Estado</label>
            <select name="estado" id="seg-estado">
                <?php foreach (ESTADOS_HOGAR as $k => [$et, $cl]): ?><option value="<?= $k ?>"><?= $et ?></option><?php endforeach; ?>
            </select>

            <label>A cargo de</label>
            <input name="asignado_a" id="seg-asignado">

            <label>¿Qué se hizo?</label>
            <textarea name="acciones" id="seg-acciones" rows="3"></textarea>

            <br>
            <button type="submit" class="btn">Guardar seguimiento</button>
            <button type="button" class="btn gray" onclick="document.getElementById('modal-seg').style.display='none'">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirSeguimiento(d) {
    document.getElementById('seg-id').value       = d.id;
    document.getElementById('seg-titulo').textContent = d.titulo;
    document.getElementById('seg-estado').value    = d.estado;
    document.getElementById('seg-acciones').value  = d.acciones || '';
    document.getElementById('seg-asignado').value  = d.asignado || '';
    document.getElementById('modal-seg').style.display = 'flex';
}
document.getElementById('modal-seg').addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
<?php endif; ?>

<script>
document.getElementById('modal-hogar').addEventListener('click', function (e) {
    if (e.target === this) this.style.display = 'none';
});
</script>

<?php include '../config/footer.php'; ?>
