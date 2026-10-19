<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';
require_once '../config/ajustes.php';

const ETIQUETAS_CONTROL = [
    'tension_sistolica'   => ['Tensión sistólica',    'mmHg', [['60','200'], ['90','140']]],
    'tension_diastolica'  => ['Tensión diastólica',   'mmHg', [['40','130'], ['60','90']]],
    'frecuencia_cardiaca' => ['Frecuencia cardíaca',  'lpm',  [['30','200'], ['50','100']]],
    'temperatura'         => ['Temperatura',          '°C',   [['34','42'],  ['35','37.5']]],
    'peso'                => ['Peso',                 'kg',   [['20','250'], ['30','200']]],
    'glucemia'            => ['Glucemia',             'mg/dL',[['30','600'], ['70','130']]],
    'saturacion_oxigeno'  => ['Saturación de oxígeno','%',   [['50','100'], ['90','100']]],
];

$OPCIONES_MIN = [
    'tension_sistolica'   => ['70'=>'70 mmHg','80'=>'80 mmHg','90'=>'90 mmHg','100'=>'100 mmHg'],
    'tension_diastolica'  => ['40'=>'40 mmHg','50'=>'50 mmHg','60'=>'60 mmHg','70'=>'70 mmHg'],
    'frecuencia_cardiaca' => ['40'=>'40 lpm','50'=>'50 lpm','55'=>'55 lpm','60'=>'60 lpm'],
    'temperatura'         => ['34'=>'34 °C','34.5'=>'34.5 °C','35'=>'35 °C','35.5'=>'35.5 °C'],
    'peso'                => ['25'=>'25 kg','30'=>'30 kg','35'=>'35 kg','40'=>'40 kg'],
    'glucemia'            => ['50'=>'50 mg/dL','60'=>'60 mg/dL','70'=>'70 mg/dL','80'=>'80 mg/dL'],
    'saturacion_oxigeno'  => ['85'=>'85%','88'=>'88%','90'=>'90%','92'=>'92%','94'=>'94%'],
];
$OPCIONES_MAX = [
    'tension_sistolica'   => ['130'=>'130 mmHg','135'=>'135 mmHg','140'=>'140 mmHg','150'=>'150 mmHg','160'=>'160 mmHg'],
    'tension_diastolica'  => ['85'=>'85 mmHg','90'=>'90 mmHg','95'=>'95 mmHg','100'=>'100 mmHg'],
    'frecuencia_cardiaca' => ['90'=>'90 lpm','95'=>'95 lpm','100'=>'100 lpm','110'=>'110 lpm'],
    'temperatura'         => ['37'=>'37 °C','37.2'=>'37.2 °C','37.5'=>'37.5 °C','38'=>'38 °C'],
    'peso'                => ['100'=>'100 kg','120'=>'120 kg','150'=>'150 kg','200'=>'200 kg'],
    'glucemia'            => ['100'=>'100 mg/dL','110'=>'110 mg/dL','120'=>'120 mg/dL','130'=>'130 mg/dL','140'=>'140 mg/dL'],
    'saturacion_oxigeno'  => ['97'=>'97%','98'=>'98%','99'=>'99%','100'=>'100%'],
];

function volver_cfg($mensaje, $tipo = 'ok') {
    header('Location: configuracion.php?toast=' . urlencode($mensaje) . '&tipo=' . $tipo);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_establecimiento'])) {
    $st = $pdo->prepare('UPDATE configuracion SET valor=? WHERE clave=?');
    $st->execute([trim($_POST['direccion'] ?? ''), 'institucion_direccion']);
    $st->execute([trim($_POST['telefono']  ?? ''), 'institucion_telefono']);
    volver_cfg('Datos del establecimiento actualizados');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_operacion'])) {
    $validas = $pdo->query("SELECT clave FROM configuracion WHERE grupo IN('comidas','turnos')")->fetchAll(PDO::FETCH_COLUMN);
    $st = $pdo->prepare('UPDATE configuracion SET valor=? WHERE clave=?');
    foreach (($_POST['cfg'] ?? []) as $clave => $valor) {
        if (!in_array($clave, $validas, true)) continue;
        $st->execute([trim($valor), $clave]);
    }
    volver_cfg('Configuración operativa guardada');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_rangos'])) {
    $st = $pdo->prepare('UPDATE rangos_control SET valor_min=?, valor_max=? WHERE tipo_control=?');
    foreach (array_keys(ETIQUETAS_CONTROL) as $tipo) {
        $vmin = (float)($_POST['min'][$tipo] ?? 0);
        $vmax = (float)($_POST['max'][$tipo] ?? 0);
        if ($vmin >= $vmax) {
            volver_cfg('El mínimo debe ser menor que el máximo en todos los controles.', 'error');
        }
        $st->execute([$vmin, $vmax, $tipo]);
    }
    volver_cfg('Rangos de referencia actualizados');
}

include '../config/menu.php';

$cfg = $pdo->query('SELECT * FROM configuracion ORDER BY grupo, orden')->fetchAll();
$porGrupo = [];
foreach ($cfg as $c) $porGrupo[$c['grupo']][$c['clave']] = $c;

$rangos = $pdo->query('SELECT * FROM rangos_control')->fetchAll();
$rangosPorTipo = [];
foreach ($rangos as $r) $rangosPorTipo[$r['tipo_control']] = $r;

$inst = $porGrupo['institucion'] ?? [];
?>

<h1>Configuración del establecimiento</h1>


<div class="card">
    <h2 style="margin-top:0">Datos del establecimiento</h2>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:16px">
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Nombre</label>
            <div style="padding:10px 12px;background:var(--bg-page,#f5f6fa);border-radius:8px;color:var(--text-muted);font-size:14px">
                <?= h($inst['institucion_nombre']['valor'] ?? 'Hogar Ñongo') ?>
            </div>
            <small style="color:#aaa">Solo lectura</small>
        </div>
        <div>
            <label style="font-size:13px;font-weight:600;display:block">CUIT / Identificación</label>
            <div style="padding:10px 12px;background:var(--bg-page,#f5f6fa);border-radius:8px;color:var(--text-muted);font-size:14px">
                <?= h($inst['institucion_cuit']['valor'] ?? '') ?>
            </div>
            <small style="color:#aaa">Solo lectura</small>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="guardar_establecimiento" value="1">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Dirección</label>
                <input name="direccion" value="<?= h($inst['institucion_direccion']['valor'] ?? '') ?>">
             
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Teléfono</label>
                <input name="telefono" type="tel" value="<?= h($inst['institucion_telefono']['valor'] ?? '') ?>">
                
            </div>
        </div>
        <button type="submit" class="btn" style="margin-top:14px">Guardar datos</button>
    </form>
</div>

<div class="card">
    <h2 style="margin-top:0">Turnos y horarios de comidas</h2>
    <form method="post">
        <input type="hidden" name="guardar_operacion" value="1">
        <h3 style="margin:0 0 10px;font-size:14px;color:var(--text-muted)">Turnos</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;margin-bottom:20px">
            <?php foreach (($porGrupo['turnos'] ?? []) as $c): ?>
            <div>
                <label style="font-size:13px;font-weight:600;display:block"><?= h($c['etiqueta']) ?></label>
                <?php if ($c['tipo']==='hora'): ?>
                    <input type="time" name="cfg[<?= h($c['clave']) ?>]" value="<?= h($c['valor']) ?>">
                <?php else: ?>
                    <input type="number" min="1" max="90" name="cfg[<?= h($c['clave']) ?>]" value="<?= h($c['valor']) ?>">
                <?php endif; ?>
                <?php if ($c['descripcion']): ?><small style="color:var(--text-muted)"><?= h($c['descripcion']) ?></small><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <h3 style="margin:0 0 10px;font-size:14px;color:var(--text-muted)">Horarios de comidas</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px">
            <?php foreach (($porGrupo['comidas'] ?? []) as $c): ?>
            <div>
                <label style="font-size:13px;font-weight:600;display:block"><?= h($c['etiqueta']) ?></label>
                <input type="time" name="cfg[<?= h($c['clave']) ?>]" value="<?= h($c['valor']) ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn" style="margin-top:16px">Guardar configuración</button>
    </form>
</div>

<div class="card">
    <h2 style="margin-top:0">Rangos de referencia: controles clínicos</h2>
    <form method="post">
        <input type="hidden" name="guardar_rangos" value="1">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px">
        <?php foreach (ETIQUETAS_CONTROL as $tipo => [$etiqueta, $unidad, $refs]):
            $r = $rangosPorTipo[$tipo] ?? ['valor_min' => '', 'valor_max' => ''];
            $min_actual = (string)(float)$r['valor_min'];
            $max_actual = (string)(float)$r['valor_max'];
        ?>
        <div style="border:1px solid var(--border-color,#eee);border-radius:10px;padding:14px">
            <b style="display:block;margin-bottom:10px"><?= h($etiqueta) ?> <span class="small">(<?= h($unidad) ?>)</span></b>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                <div>
                    <label style="font-size:12px;color:var(--text-muted);display:block">Valor mínimo</label>
                    <select name="min[<?= $tipo ?>]" style="width:100%">
                        <?php foreach ($OPCIONES_MIN[$tipo] as $val => $etiq): ?>
                            <option value="<?= $val ?>" <?= $min_actual == $val ? 'selected' : '' ?>><?= $etiq ?></option>
                        <?php endforeach; ?>
                        <option value="<?= $min_actual ?>" <?= !isset($OPCIONES_MIN[$tipo][$min_actual]) ? 'selected' : '' ?>>
                            Personalizado: <?= $min_actual ?>
                        </option>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;color:var(--text-muted);display:block">Valor máximo</label>
                    <select name="max[<?= $tipo ?>]" style="width:100%">
                        <?php foreach ($OPCIONES_MAX[$tipo] as $val => $etiq): ?>
                            <option value="<?= $val ?>" <?= $max_actual == $val ? 'selected' : '' ?>><?= $etiq ?></option>
                        <?php endforeach; ?>
                        <option value="<?= $max_actual ?>" <?= !isset($OPCIONES_MAX[$tipo][$max_actual]) ? 'selected' : '' ?>>
                            Personalizado: <?= $max_actual ?>
                        </option>
                    </select>
                </div>
            </div>
            <p class="small" style="margin:8px 0 0;color:var(--text-muted)">
                Referencia habitual: <?= $refs[1][0] ?>–<?= $refs[1][1] ?> <?= h($unidad) ?>
            </p>
        </div>
        <?php endforeach; ?>
        </div>
        <button type="submit" class="btn" style="margin-top:16px">Guardar rangos</button>
    </form>
</div>

<?php include '../config/footer.php'; ?>
