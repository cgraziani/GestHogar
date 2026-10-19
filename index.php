<?php
require_once 'config/session.php';
require_login();
require_once 'config/db.php';
include 'config/menu.php';

$total_residentes = $pdo->query("SELECT COUNT(*) FROM residentes")->fetchColumn();
$total_activos    = $pdo->query("SELECT COUNT(*) FROM residentes WHERE estado='activo'")->fetchColumn();
$total_usuarios   = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado!='baja'")->fetchColumn();
$total_cuidadoras = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('cuidadora','asistente') AND estado='activo'")->fetchColumn();
$total_planes     = $pdo->query("SELECT COUNT(*) FROM medicacion_residente WHERE estado='activo'")->fetchColumn();
$total_visitas_hoy = $pdo->query("SELECT COUNT(*) FROM visitas
    WHERE estado IN('agendada','en_curso')
    AND DATE(COALESCE(hora_ingreso, created_at)) = CURDATE()")->fetchColumn();

$traspasos_pendientes = $pdo->query("SELECT COUNT(*) FROM traspaso_turno WHERE estado='pendiente'")->fetchColumn();
$incidentes_graves    = $pdo->query("SELECT COUNT(*) FROM incidentes WHERE gravedad='grave' AND estado='pendiente'")->fetchColumn();

$visitas_solicitadas = $pdo->query("SELECT v.*, r.nombre rn, r.apellido ra
    FROM visitas v
    JOIN residentes r ON r.id_residente=v.id_residente
    WHERE v.estado='solicitada'
    ORDER BY v.created_at ASC")->fetchAll();

$hoy         = date('Y-m-d');
$hora_actual = date('H:i:s');
$turno_ahora = $pdo->prepare("
    SELECT u.id_usuario, u.nombre, u.apellido, u.rol, u.color, tp.hora_inicio, tp.hora_fin
    FROM turnos_programados tp
    JOIN usuarios u ON u.id_usuario = tp.id_cuidadora_usuario
    WHERE tp.estado='activo' AND tp.fecha = ?
      AND u.estado = 'activo'
    ORDER BY FIELD(u.rol,'cuidadora','asistente'), u.nombre");
$turno_ahora->execute([$hoy]);
$turno_ahora = $turno_ahora->fetchAll();

$adhesion_semanas = $pdo->query("SELECT
    DATE_FORMAT(DATE_SUB(am.fecha_hora, INTERVAL WEEKDAY(am.fecha_hora) DAY), '%d/%m') semana,
    ROUND(SUM(am.estado_adm='administrado') * 100 / COUNT(*)) porc,
    COUNT(*) total
    FROM administracion_medicacion am
    WHERE am.fecha_hora >= DATE_SUB(NOW(), INTERVAL 56 DAY)
    GROUP BY semana ORDER BY MIN(am.fecha_hora)")->fetchAll();

$inc_mes = $pdo->query("SELECT
    DATE_FORMAT(fecha_hora,'%m/%Y') mes,
    COUNT(*) total,
    SUM(gravedad='grave') graves
    FROM incidentes
    WHERE fecha_hora >= DATE_SUB(NOW(), INTERVAL 4 MONTH)
    GROUP BY mes ORDER BY MIN(fecha_hora)")->fetchAll();

$alertas_higiene = [];
$tipos_higiene = ['peluquero' => 20, 'manicura' => 30];
$residentes_activos = $pdo->query("SELECT id_residente, nombre, apellido FROM residentes WHERE estado='activo'")->fetchAll();

foreach ($tipos_higiene as $tipo_vis => $dias_umbral) {
    foreach ($residentes_activos as $res) {
        $ultima = $pdo->prepare("SELECT MAX(hora_ingreso) FROM visitas
            WHERE id_residente=? AND tipo_visita=? AND estado='finalizada'");
        $ultima->execute([$res['id_residente'], $tipo_vis]);
        $fecha_ultima = $ultima->fetchColumn();

        $necesita_alerta = false;
        $dias_desde = null;
        if (!$fecha_ultima) {
            $necesita_alerta = true;
        } else {
            $dias_desde = (int)((time() - strtotime($fecha_ultima)) / 86400);
            $necesita_alerta = $dias_desde >= $dias_umbral;
        }

        if ($necesita_alerta) {
            $alertas_higiene[] = [
                'id_residente' => $res['id_residente'],
                'nombre'       => $res['nombre'],
                'apellido'     => $res['apellido'],
                'tipo'         => $tipo_vis,
                'ultima_visita'=> $fecha_ultima,
                'dias_desde'   => $dias_desde,
                'umbral'       => $dias_umbral,
            ];
        }
    }
}


function iniciales_dashboard($nombre, $apellido) {
    $n = mb_strtoupper(mb_substr(trim($nombre), 0, 1));
    $a = $apellido ? mb_strtoupper(mb_substr(trim($apellido), 0, 1)) : '';
    return $n . $a;
}

function fecha_es($timestamp) {
    $dias  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $meses = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto',
               'septiembre','octubre','noviembre','diciembre'];
    return $dias[(int)date('w',$timestamp)].' '.date('j',$timestamp).' de '.$meses[(int)date('n',$timestamp)].' de '.date('Y',$timestamp);
}
?>

<div>

<?php if (es_admin()): ?>

<div style="margin-bottom:1.5rem">
    <h1 style="margin:0 0 4px">Hola, <?= htmlspecialchars(explode(' ', $_SESSION['usuario']['nombre'])[0]) ?></h1>
    <p style="margin:0;font-size:14px;color:var(--text-muted,#888)"><?= fecha_es(strtotime($hoy)) ?></p>
</div>

<?php if ($traspasos_pendientes > 0 || $incidentes_graves > 0): ?>
<div style="display:flex;flex-direction:column;gap:10px;margin-bottom:1.5rem">
    <?php if ($incidentes_graves > 0): ?>
    <div style="background:#fdecea;border-left:3px solid #c0392b;border-radius:0 8px 8px 0;padding:12px 16px">
        <p style="margin:0;font-size:14px;font-weight:600;color:#c0392b">
             <?= $incidentes_graves ?> incidente<?= $incidentes_graves > 1 ? 's' : '' ?> grave<?= $incidentes_graves > 1 ? 's' : '' ?> sin revisar
        </p>
        <a href="incidentes/listar.php" style="font-size:13px;color:#c0392b;font-weight:600">Ver incidentes →</a>
    </div>
    <?php endif; ?>
    <?php if ($traspasos_pendientes > 0): ?>
    <div style="background:#fff3cd;border-left:3px solid #e67e22;border-radius:0 8px 8px 0;padding:12px 16px">
        <p style="margin:0;font-size:14px;font-weight:600;color:#856404">
<?= $traspasos_pendientes ?> traspaso<?= $traspasos_pendientes > 1 ? 's' : '' ?> pendiente<?= $traspasos_pendientes > 1 ? 's' : '' ?> de confirmación
        </p>
        <a href="turnos/listar.php" style="font-size:13px;color:#e67e22;font-weight:600">Ver traspasos →</a>
    </div>
    <?php endif; ?>
    <?php if (!empty($visitas_solicitadas)): ?>
    <div style="background:#fff3cd;border-left:3px solid #2980b9;border-radius:0 8px 8px 0;padding:12px 16px">
        <p style="margin:0 0 6px;font-size:14px;font-weight:600;color:#1a5276">
             <?= count($visitas_solicitadas) ?> solicitud<?= count($visitas_solicitadas) > 1 ? 'es' : '' ?> de visita pendiente<?= count($visitas_solicitadas) > 1 ? 's' : '' ?> de aprobación
        </p>
        <?php foreach ($visitas_solicitadas as $vs): ?>
            <p style="margin:2px 0;font-size:13px">
                <b><?= htmlspecialchars($vs['nombre_visitante'].' '.$vs['apellido_visitante']) ?></b>
                visita a <b><?= htmlspecialchars($vs['rn'].' '.$vs['ra']) ?></b>
                solicitó el <?= date('d/m/Y H:i', strtotime($vs['created_at'])) ?>
                <a href="visitas/listar.php?aprobar=<?= $vs['id_visita'] ?>"
                   style="margin-left:8px;background:#27ae60;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;text-decoration:none">
                    Aprobar
                </a>
                <a href="visitas/listar.php?rechazar=<?= $vs['id_visita'] ?>"
                   style="margin-left:4px;background:#e74c3c;color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;text-decoration:none">
                    Rechazar
                </a>
            </p>
        <?php endforeach; ?>
        <a href="visitas/listar.php" style="font-size:13px;color:#2980b9;font-weight:600;display:block;margin-top:6px">Ver todas las visitas →</a>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<?php if (es_admin() && !empty($alertas_higiene)): ?>
<div class="card" style="border-left:4px solid #8e44ad;margin-bottom:16px">
    <h3 style="margin:0 0 10px;color:#8e44ad;font-size:15px">Servicios de higiene personal pendientes</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:8px">
        <?php foreach ($alertas_higiene as $ah):
            $tipo_label = $ah['tipo']==='peluquero' ? 'Peluquería' : 'Manicura';
            $dias_txt   = $ah['dias_desde'] !== null
                ? 'Hace '.$ah['dias_desde'].' días'
                : 'Sin registro';
        ?>
        <div style="display:flex;align-items:center;justify-content:space-between;
                    background:var(--bg-page,#f5f6fa);border-radius:8px;padding:8px 12px">
            <div>
                <b style="font-size:13px"><?= h($ah['nombre'].' '.$ah['apellido']) ?></b>
                <span class="badge" style="margin-left:6px"><?= $tipo_label ?></span><br>
                <span class="small" style="color:#e74c3c"><?= $dias_txt ?> (umbral: <?= $ah['umbral'] ?> días)</span>
            </div>
            <a href="visitas/listar.php" class="btn gray" style="font-size:11px;padding:4px 8px;white-space:nowrap">
                Registrar visita
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:1.5rem">
    <a href="residentes/listar.php" style="text-decoration:none;color:inherit">
        <div class="card" style="padding:16px;margin:0;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--color-accent)"><?= $total_activos ?><span style="font-size:14px;color:var(--text-muted);font-weight:400"> / <?= $total_residentes ?></span></div>
            <div style="font-size:13px;color:var(--text-muted)">Residentes activos</div>
        </div>
    </a>
    <a href="admin/usuarios.php?tab=cuidadoras" style="text-decoration:none;color:inherit">
        <div class="card" style="padding:16px;margin:0;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--color-accent)"><?= $total_cuidadoras ?></div>
            <div style="font-size:13px;color:var(--text-muted)">Cuidadoras activas</div>
        </div>
    </a>
    <a href="medicacion/listar.php" style="text-decoration:none;color:inherit">
        <div class="card" style="padding:16px;margin:0;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--color-accent)"><?= $total_planes ?></div>
            <div style="font-size:13px;color:var(--text-muted)">Planes de medicación</div>
        </div>
    </a>
    <a href="visitas/listar.php" style="text-decoration:none;color:inherit">
        <div class="card" style="padding:16px;margin:0;text-align:center">
            <div style="font-size:30px;font-weight:700;color:var(--color-accent)"><?= $total_visitas_hoy ?></div>
            <div style="font-size:13px;color:var(--text-muted)">Visitas hoy</div>
        </div>
    </a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:1.5rem">

    <?php if (!empty($adhesion_semanas)): ?>
    <div class="card" style="margin:0">
        <h3 style="margin:0 0 12px;font-size:15px">Adhesión a la medicación: últimas semanas</h3>
        <canvas id="grafico-adhesion" height="160"></canvas>
    </div>
    <?php endif; ?>

    <?php if (!empty($inc_mes)): ?>
    <div class="card" style="margin:0">
        <h3 style="margin:0 0 12px;font-size:15px">Incidentes: últimos 4 meses</h3>
        <canvas id="grafico-incidentes" height="160"></canvas>
    </div>
    <?php endif; ?>

</div>

<h2 style="margin:0 0 10px;font-size:16px">Quién está de turno ahora</h2>
<div class="card" style="padding:16px 20px;margin:0 0 1.5rem">
    <?php if (empty($turno_ahora)): ?>
        <p style="margin:0;font-size:14px;color:var(--text-muted,#888)"><a href="turnos/calendario.php" style="color:var(--color-accent)">Generar cronograma →</a>
        </p>
    <?php else: ?>
        <?php foreach ($turno_ahora as $i => $t): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:8px 0;<?= $i < count($turno_ahora)-1 ? 'border-bottom:1px solid var(--border-color,#eee)' : '' ?>">
            <div style="width:36px;height:36px;border-radius:50%;background:<?= htmlspecialchars($t['color'] ?: 'var(--color-accent)') ?>;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:13px;color:#fff;flex-shrink:0">
                <?= iniciales_dashboard($t['nombre'], $t['apellido'] ?? '') ?>
            </div>
            <div style="flex:1">
                <p style="margin:0;font-size:14px;font-weight:600"><?= htmlspecialchars($t['nombre'].' '.($t['apellido'] ?? '')) ?></p>
                <p style="margin:0;font-size:12px;color:var(--text-muted,#888)">
                    <?= ucfirst($t['rol']) ?> · <?= substr($t['hora_inicio'],0,5) ?> a <?= substr($t['hora_fin'],0,5) ?>
                </p>
            </div>
            <span style="font-size:12px;color:#155724;background:#d4edda;padding:3px 10px;border-radius:8px">En turno</span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<h2 style="margin:0 0 10px;font-size:16px">Accesos rápidos</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px;margin-bottom:1.5rem">
    <a href="medicacion/agenda.php" class="btn gray" style="display:flex;align-items:center;gap:8px;text-decoration:none">
         Agenda del turno
    </a>
    <a href="reportes/dashboard.php" class="btn gray" style="display:flex;align-items:center;gap:8px;text-decoration:none">
         Dashboard completo
    </a>
    <a href="incidentes/listar.php" class="btn gray" style="display:flex;align-items:center;gap:8px;text-decoration:none">
         Registrar incidente
    </a>
    <a href="turnos/listar.php" class="btn gray" style="display:flex;align-items:center;gap:8px;text-decoration:none">
         Traspaso de turno
    </a>
    <a href="controles/plan.php" class="btn gray" style="display:flex;align-items:center;gap:8px;text-decoration:none">
         Plan de controles
    </a>
    <a href="reportes/index.php" class="btn gray" style="display:flex;align-items:center;gap:8px;text-decoration:none">
         Reportes
    </a>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($adhesion_semanas)): ?>
new Chart(document.getElementById('grafico-adhesion'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($adhesion_semanas, 'semana')) ?>,
        datasets: [{
            label: 'Adhesión %',
            data:  <?= json_encode(array_column($adhesion_semanas, 'porc')) ?>,
            borderColor: '#2D9B8A', backgroundColor: 'rgba(45,155,138,.1)',
            tension: .3, fill: true, pointRadius: 4
        }]
    },
    options: {
        scales: { y: { min:0, max:100, ticks:{ callback: v => v+'%' } } },
        plugins: { legend:{ display:false } }
    }
});
<?php endif; ?>
<?php if (!empty($inc_mes)): ?>
new Chart(document.getElementById('grafico-incidentes'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($inc_mes, 'mes')) ?>,
        datasets: [
            { label:'Total',  data:<?= json_encode(array_column($inc_mes,'total')) ?>, backgroundColor:'#e67e22' },
            { label:'Graves', data:<?= json_encode(array_column($inc_mes,'graves')) ?>, backgroundColor:'#e74c3c' }
        ]
    },
    options: { plugins:{ legend:{ position:'bottom' } } }
});
<?php endif; ?>
</script>

<?php elseif (es_cuidadora() || es_asistente()): ?>
    <div>
        <h1 style="margin:0 0 4px">Hola, <?= htmlspecialchars(explode(' ', $_SESSION['usuario']['nombre'])[0]) ?></h1>
        <p style="margin:0 0 1.5rem;font-size:14px;color:var(--text-muted)"><?= fecha_es(strtotime($hoy)) ?></p>
    </div>
    <div class="card">
        <h2>Panel de <?= es_cuidadora() ? 'cuidadora' : 'asistente' ?></h2>
        <p>Podés ver residentes, medicación, tareas y calendario de turnos.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn" href="medicacion/agenda.php">Ver agenda del turno</a>
            <a class="btn" href="medicacion/tareas.php">Ver tareas de cuidado</a>
            <a class="btn gray" href="turnos/calendario.php">Ver calendario</a>
        </div>
    </div>

<?php elseif (es_cocinera()): ?>
    <div class="card">
        <h2>Panel de cocina</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn" href="cocina/listar.php">Cocina y alimentación</a>
            <a class="btn gray" href="turnos/calendario.php">Ver calendario</a>
        </div>
    </div>
<?php endif; ?>

</div>

<?php include 'config/footer.php'; ?>

