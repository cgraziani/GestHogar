<?php
// ============================================================
// HISTORIAL DE AUDITORÍA
// Cambios v2:
// - Reemplaza la lógica de restaurar eliminado=0
//   (ya no existe esa columna)
// - Ahora muestra el historial_estados como fuente principal
// - Mantiene consulta de acciones generales por tabla
//   (bajas por estado) para referencia histórica
// ============================================================
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

include '../config/menu.php';

// Historial de estados (fuente principal en v2)
$historial = $pdo->query("SELECT h.*,
                                  CASE h.entidad
                                    WHEN 'usuario'   THEN u2.nombre
                                    WHEN 'residente' THEN CONCAT(r.nombre,' ',r.apellido)
                                  END nombre_registro,
                                  u.nombre cambiado_por_nombre
                           FROM historial_estados h
                           LEFT JOIN usuarios u  ON u.id_usuario  = h.cambiado_por
                           LEFT JOIN usuarios u2 ON u2.id_usuario = h.id_registro AND h.entidad='usuario'
                           LEFT JOIN residentes r ON r.id_residente = h.id_registro AND h.entidad='residente'
                           ORDER BY h.created_at DESC")->fetchAll();
?>

<h1>Historial de auditoría</h1>
<p>Registro completo de todos los cambios de estado en el sistema: altas, bajas y pausas de usuarios y residentes.</p>

<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Módulo</th>
            <th>Registro</th>
            <th>Cambio</th>
            <th>Realizado por</th>
            <th>Ir al módulo</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($historial as $h): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
            <td>
                <span class="badge">
                    <?= $h['entidad'] === 'usuario' ? 'Usuario' : 'Residente' ?>
                </span>
            </td>
            <td><?= htmlspecialchars($h['nombre_registro'] ?? 'ID ' . $h['id_registro']) ?></td>
            <td>
                <span class="pill estado-<?= $h['estado_anterior'] ?: 'pendiente' ?>">
                    <?= $h['estado_anterior'] ?: 'alta' ?>
                </span>
                →
                <span class="pill estado-<?= $h['estado_nuevo'] ?>">
                    <?= $h['estado_nuevo'] ?>
                </span>
            </td>
            <td><?= htmlspecialchars($h['cambiado_por_nombre'] ?? '—') ?></td>
            <td>
                <?php if ($h['entidad'] === 'usuario'): ?>
                    <a class="btn gray" href="../admin/usuarios.php?ver=<?= $h['id_registro'] ?>">Ver usuario</a>
                <?php else: ?>
                    <a class="btn gray" href="../residentes/listar.php?ver=<?= $h['id_registro'] ?>">Ver residente</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>
