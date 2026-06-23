<?php require_once 'config/session.php';
require_login();
require_once 'config/db.php';
include 'config/menu.php';

$total_residentes = $pdo->query("SELECT COUNT(*) FROM residentes")->fetchColumn();
$total_activos    = $pdo->query("SELECT COUNT(*) FROM residentes WHERE estado='activo'")->fetchColumn();
$total_usuarios   = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE estado!='baja'")->fetchColumn();
$total_cuidadoras = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol IN ('cuidadora','asistente') AND estado='activo'")->fetchColumn();
$total_planes     = $pdo->query("SELECT COUNT(*) FROM medicacion_residente WHERE estado='activo'")->fetchColumn();

$hoy    = date('Y-m-d');
$turnos = $pdo->prepare("SELECT tp.*,u.nombre cuidadora,u.color,u.rol FROM turnos_programados tp JOIN usuarios u ON u.id_usuario=tp.id_cuidadora_usuario WHERE tp.estado='activo' AND tp.fecha>=? ORDER BY tp.fecha,tp.hora_inicio LIMIT 20");
$turnos->execute([$hoy]);
$turnos = $turnos->fetchAll();
?>

<h1>Panel principal</h1>

<?php if (es_admin()): ?>

<div class="grid">
    <a href="residentes/listar.php" style="text-decoration:none">
        <div class="card" style="text-align:center;cursor:pointer">
            <div class="stat"><?= $total_activos ?> <span style="font-size:16px;color:#888">/ <?= $total_residentes ?></span></div>
            <b>Residentes activos</b><br>
            <span class="small" style="color:#2D9B8A">Ver listado →</span>
        </div>
    </a>

    <a href="admin/usuarios.php" style="text-decoration:none">
        <div class="card" style="text-align:center;cursor:pointer">
            <div class="stat"><?= $total_usuarios ?></div>
            <b>Usuarios</b><br>
            <span class="small" style="color:#2D9B8A">Ver listado →</span>
        </div>
    </a>

    <a href="admin/usuarios.php" style="text-decoration:none">
        <div class="card" style="text-align:center;cursor:pointer">
            <div class="stat"><?= $total_cuidadoras ?></div>
            <b>Cuidadoras activas</b><br>
            <span class="small" style="color:#2D9B8A">Ver usuarios →</span>
        </div>
    </a>

    <a href="medicacion/listar.php" style="text-decoration:none">
        <div class="card" style="text-align:center;cursor:pointer">
            <div class="stat"><?= $total_planes ?></div>
            <b>Planes de medicación</b><br>
            <span class="small" style="color:#2D9B8A">Ver medicación →</span>
        </div>
    </a>
</div>

<div class="card">
    <h2>Próximos turnos programados</h2>
    <table class="datatable">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Personal</th>
                <th>Horario</th>
                <th>Nota</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($turnos as $t): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($t['fecha'])) ?></td>
                <td>
                    <span style="display:inline-block;width:14px;height:14px;
                                 background:<?= $t['color'] ?>;border-radius:50%;
                                 vertical-align:middle;margin-right:6px"></span>
                    <?= htmlspecialchars($t['cuidadora']) ?>
                    <span class="badge"><?= $t['rol'] ?></span>
                </td>
                <td><?= $t['hora_inicio'] ?> a <?= $t['hora_fin'] ?></td>
                <td><?= htmlspecialchars($t['nota']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p><a class="btn" href="turnos/calendario.php">Abrir calendario completo</a></p>
</div>

<?php elseif (es_cuidadora()): ?>
    <div class="card">
        <h2>Panel de cuidadora</h2>
        <p>
            <a class="btn" href="medicacion/listar.php">Ver medicación y tareas</a>
            <a class="btn gray" href="turnos/calendario.php">Ver calendario</a>
        </p>
    </div>

<?php elseif (es_cocinera()): ?>
    <div class="card">
        <h2>Panel de cocina</h2>
        <p>
            <a class="btn" href="cocina/listar.php">Cargar recetas y gastos</a>
            <a class="btn gray" href="turnos/calendario.php">Ver calendario</a>
        </p>
    </div>

<?php elseif (es_asistente()): ?>
    <div class="card">
        <h2>Panel de asistente</h2>
        <p>
            <a class="btn gray" href="turnos/calendario.php">Ver calendario</a>
            <a class="btn" href="medicacion/listar.php">Ver medicación y tareas</a>
        </p>
    </div>

<?php endif; ?>

<?php include 'config/footer.php'; ?>
