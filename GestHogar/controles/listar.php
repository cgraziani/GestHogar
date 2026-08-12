<?php
// ============================================================
// CONTROLES CLÍNICOS — Registro de signos vitales por residente
// CB-17: Signo vital fuera de rango → advertencia pero permite guardar
// Registro inmutable: solo created_at, sin updated_at
// ============================================================
require_once '../config/session.php';
require_once '../config/db.php';
puede_cuidar();

// Rangos de referencia por tipo (CB-17)
$rangos = [
    'tension_sistolica'   => ['min'=>70,  'max'=>180, 'unidad'=>'mmHg'],
    'tension_diastolica'  => ['min'=>40,  'max'=>120, 'unidad'=>'mmHg'],
    'temperatura'         => ['min'=>34,  'max'=>41,  'unidad'=>'°C'],
    'peso'                => ['min'=>30,  'max'=>200, 'unidad'=>'kg'],
    'glucemia'            => ['min'=>50,  'max'=>400, 'unidad'=>'mg/dL'],
    'saturacion_oxigeno'  => ['min'=>70,  'max'=>100, 'unidad'=>'%'],
    'frecuencia_cardiaca' => ['min'=>40,  'max'=>180, 'unidad'=>'lpm'],
];

$labels = [
    'tension_sistolica'   => 'Tensión sistólica',
    'tension_diastolica'  => 'Tensión diastólica',
    'temperatura'         => 'Temperatura',
    'peso'                => 'Peso',
    'glucemia'            => 'Glucemia',
    'saturacion_oxigeno'  => 'Saturación de oxígeno',
    'frecuencia_cardiaca' => 'Frecuencia cardíaca',
];

$toast = '';
$toast_tipo = 'ok';

// ── REGISTRAR CONTROL ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_control'])) {
    $id_res     = (int)$_POST['id_residente'];
    $tipo       = trim($_POST['tipo_control'] ?? '');
    $valor      = trim($_POST['valor'] ?? '');
    $obs        = trim($_POST['observaciones'] ?? '');
    $fecha_hora = trim($_POST['fecha_hora'] ?? date('Y-m-d H:i:s'));

    $error = '';
    if (!$id_res)                       $error = 'Seleccioná un residente.';
    elseif (!isset($rangos[$tipo]))     $error = 'Tipo de control inválido.';
    elseif (!is_numeric($valor))        $error = 'El valor debe ser numérico.';
    elseif ((float)$valor < 0)          $error = 'El valor no puede ser negativo.';

    if ($error) {
        header('Location: controles_clinicos.php?toast=' . urlencode($error) . '&tipo=error');
        exit;
    }

    // CB-17: Detectar valor fuera de rango — advertir pero guardar igual
    $fuera_rango = false;
    $r = $rangos[$tipo];
    if ((float)$valor < $r['min'] || (float)$valor > $r['max']) {
        $fuera_rango = true;
    }

    $pdo->prepare("
        INSERT INTO controles_clinicos
            (id_residente, id_usuario, tipo_control, valor, unidad, observaciones, fecha_hora)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $id_res, uid(), $tipo, (float)$valor,
        $rangos[$tipo]['unidad'],
        $obs ?: null,
        $fecha_hora
    ]);

    if ($fuera_rango) {
        $msg = 'Control registrado. ⚠️ El valor ' . $valor . ' ' . $r['unidad'] . ' está fuera del rango esperado (' . $r['min'] . '–' . $r['max'] . ' ' . $r['unidad'] . '). Verificá con el médico.';
        header('Location: controles_clinicos.php?toast=' . urlencode($msg) . '&tipo=warn');
    } else {
        header('Location: controles_clinicos.php?toast=Control+registrado+correctamente&tipo=ok');
    }
    exit;
}

// ── DATOS ─────────────────────────────────────────────────────
include '../config/menu.php';

$residentes = $pdo->query("SELECT id_residente, nombre, apellido FROM residentes WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll();

$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$whereRes     = $id_residente ? ' AND cc.id_residente=' . $id_residente : '';

$controles = $pdo->query("
    SELECT cc.*, r.nombre res_nombre, r.apellido res_apellido,
           u.nombre registrado_por
    FROM controles_clinicos cc
    JOIN residentes r ON r.id_residente = cc.id_residente
    LEFT JOIN usuarios u ON u.id_usuario = cc.id_usuario
    WHERE 1=1 $whereRes
    ORDER BY cc.fecha_hora DESC
    LIMIT 200
")->fetchAll();
?>

<style>
.fuera-rango { background:#fff3cd; border-left:3px solid #e67e22 }
.en-rango    { background:#d4edda; border-left:3px solid #28a745 }
</style>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <h1 style="margin:0">Controles clínicos</h1>
    <button class="btn" onclick="document.getElementById('modal-control').style.display='flex'">
        + Registrar control
    </button>
</div>

<!-- Filtro por residente -->
<div style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
    <label style="font-size:13px;font-weight:600">Filtrar por residente:</label>
    <select class="buscador" onchange="window.location='controles_clinicos.php?residente='+this.value"
            style="min-width:240px;padding:8px 12px;border:1.5px solid var(--border-color,#ddd);border-radius:8px;font-size:14px">
        <option value="0" <?= $id_residente==0?'selected':'' ?>>— Todos los residentes —</option>
        <?php foreach ($residentes as $r): ?>
            <option value="<?= $r['id_residente'] ?>" <?= $id_residente==$r['id_residente']?'selected':'' ?>>
                <?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($id_residente): ?>
        <a href="controles_clinicos.php" class="btn gray">Limpiar filtro</a>
    <?php endif; ?>
</div>

<!-- Tabla de controles -->
<?php if (empty($controles)): ?>
    <div style="text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px">
        <div style="font-size:40px;margin-bottom:8px">📋</div>
        <b>No hay controles clínicos registrados<?= $id_residente ? ' para este residente' : '' ?></b>
    </div>
<?php else: ?>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Residente</th>
            <th>Tipo de control</th>
            <th>Valor</th>
            <th>Rango esperado</th>
            <th>Observaciones</th>
            <th>Registrado por</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($controles as $c):
        $tipo  = $c['tipo_control'];
        $valor = (float)$c['valor'];
        $rango = $rangos[$tipo] ?? null;
        $fuera = $rango && ($valor < $rango['min'] || $valor > $rango['max']);
    ?>
        <tr class="<?= $fuera ? 'fuera-rango' : '' ?>">
            <td><?= date('d/m/Y H:i', strtotime($c['fecha_hora'])) ?></td>
            <td><?= htmlspecialchars($c['res_nombre'].' '.$c['res_apellido']) ?></td>
            <td><?= htmlspecialchars($labels[$tipo] ?? $tipo) ?></td>
            <td>
                <b><?= number_format($valor, 2, ',', '.') ?> <?= htmlspecialchars($c['unidad']) ?></b>
                <?php if ($fuera): ?>
                    <span style="color:#e67e22;font-size:12px;margin-left:4px">⚠️ Fuera de rango</span>
                <?php endif; ?>
            </td>
            <td style="font-size:12px;color:#888">
                <?php if ($rango): ?>
                    <?= $rango['min'] ?> – <?= $rango['max'] ?> <?= $rango['unidad'] ?>
                <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= htmlspecialchars($c['observaciones'] ?? '—') ?></td>
            <td><?= htmlspecialchars($c['registrado_por'] ?? '—') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<!-- MODAL REGISTRAR CONTROL -->
<div id="modal-control" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:520px;position:relative">
        <button onclick="document.getElementById('modal-control').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">✕</button>
        <h2 style="margin:0 0 16px">Registrar control clínico</h2>

        <p style="font-size:13px;background:#e8f4fd;border-left:4px solid #2980b9;padding:10px 14px;border-radius:6px;margin-bottom:16px">
            ℹ️ El registro es <b>inmutable</b>: una vez guardado no se puede modificar ni eliminar.
        </p>

        <form method="post">
            <input type="hidden" name="nuevo_control" value="1">

            <!-- Residente + Tipo -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Residente *</label>
                    <select name="id_residente" required class="buscador" style="width:100%;box-sizing:border-box">
                        <option value="">Seleccioná...</option>
                        <?php foreach ($residentes as $r): ?>
                            <option value="<?= $r['id_residente'] ?>" <?= $id_residente==$r['id_residente']?'selected':'' ?>>
                                <?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Tipo de control *</label>
                    <select name="tipo_control" id="sel-tipo" required onchange="actualizarUnidad()"
                            style="width:100%;box-sizing:border-box">
                        <option value="">Seleccioná...</option>
                        <?php foreach ($labels as $val => $lbl): ?>
                            <option value="<?= $val ?>" data-unidad="<?= $rangos[$val]['unidad'] ?>"
                                    data-min="<?= $rangos[$val]['min'] ?>" data-max="<?= $rangos[$val]['max'] ?>">
                                <?= $lbl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Valor + Unidad + Fecha -->
            <div style="display:grid;grid-template-columns:1fr 100px 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Valor *</label>
                    <input type="number" name="valor" id="inp-valor" step="0.01" min="0"
                           placeholder="Ej: 120" required style="width:100%;box-sizing:border-box"
                           oninput="verificarRango()">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Unidad</label>
                    <input id="inp-unidad" readonly style="width:100%;box-sizing:border-box;background:#f0f0f0" placeholder="—">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha y hora</label>
                    <input type="datetime-local" name="fecha_hora" value="<?= date('Y-m-d\TH:i') ?>"
                           style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <!-- Advertencia de rango en tiempo real -->
            <div id="aviso-rango" style="display:none;background:#fff3cd;border-left:4px solid #e67e22;padding:10px 14px;border-radius:6px;margin-top:10px;font-size:13px;color:#856404">
                ⚠️ El valor está fuera del rango esperado. El sistema lo guardará igual — verificá con el médico.
            </div>

            <!-- Observaciones -->
            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Observaciones <span style="color:#aaa;font-weight:400">(opcional)</span></label>
                <textarea name="observaciones" rows="2"
                          placeholder="Ej: paciente estaba agitado antes del control"
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray"
                        onclick="document.getElementById('modal-control').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function actualizarUnidad() {
    const sel = document.getElementById('sel-tipo');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('inp-unidad').value = opt.dataset.unidad || '';
    verificarRango();
}
function verificarRango() {
    const sel   = document.getElementById('sel-tipo');
    const opt   = sel.options[sel.selectedIndex];
    const valor = parseFloat(document.getElementById('inp-valor').value);
    const min   = parseFloat(opt.dataset.min);
    const max   = parseFloat(opt.dataset.max);
    const aviso = document.getElementById('aviso-rango');
    if (!isNaN(valor) && !isNaN(min) && (valor < min || valor > max)) {
        aviso.style.display = 'block';
    } else {
        aviso.style.display = 'none';
    }
}
</script>

<?php include '../config/footer.php'; ?>
