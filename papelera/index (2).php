<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$tablasPermitidas = [
    'usuarios' => ['pk' => 'id_usuario', 'nombre' => 'Usuarios'],
    'residentes' => ['pk' => 'id_residente', 'nombre' => 'Residentes'],
    'habitaciones' => ['pk' => 'id_habitacion', 'nombre' => 'Habitaciones'],
    'medicacion_residente' => ['pk' => 'id_plan', 'nombre' => 'Medicación'],
    'tareas_residente' => ['pk' => 'id_tarea', 'nombre' => 'Tareas de cuidado'],
    'recetas_medicas' => ['pk' => 'id_receta', 'nombre' => 'Recetas médicas'],
    'cocina' => ['pk' => 'id_cocina', 'nombre' => 'Cocina'],
    'turnos_programados' => ['pk' => 'id_turno_prog', 'nombre' => 'Turnos programados'],
];

function registro_sigue_en_papelera($pdo, $tabla, $pk, $id){
    try{
        $st = $pdo->prepare("SELECT eliminado FROM `$tabla` WHERE `$pk`=? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row && (int)$row['eliminado'] === 1;
    }catch(Exception $e){
        return false;
    }
}

// Restaurar: vuelve eliminado=0 y borra el registro de papelera para que NO aparezca más.
if(isset($_GET['restaurar'], $_GET['tabla'])){
    $tabla = $_GET['tabla'];
    $id = (int)$_GET['restaurar'];
    if(isset($tablasPermitidas[$tabla]) && $id > 0){
        $pk = $tablasPermitidas[$tabla]['pk'];
        $pdo->prepare("UPDATE `$tabla` SET eliminado=0 WHERE `$pk`=?")->execute([$id]);
        $pdo->prepare("DELETE FROM papelera WHERE tabla=? AND registro_id=?")->execute([$tabla, $id]);
        header('Location: index.php?toast=Registro+restaurado+y+quitado+de+papelera&tipo=ok');
        exit;
    }
}

// Eliminar de papelera: intenta borrar definitivamente. Si tiene relaciones y MySQL no deja,
// lo mantiene con eliminado=1 pero lo quita de la papelera para que no siga apareciendo.
if(isset($_GET['eliminar_def'], $_GET['tabla'])){
    $tabla = $_GET['tabla'];
    $id = (int)$_GET['eliminar_def'];
    if(isset($tablasPermitidas[$tabla]) && $id > 0){
        $pk = $tablasPermitidas[$tabla]['pk'];
        try{
            $pdo->prepare("DELETE FROM `$tabla` WHERE `$pk`=?")->execute([$id]);
        }catch(Exception $e){
            // Si no se puede borrar por historial relacionado, al menos queda oculto del sistema.
            try{ $pdo->prepare("UPDATE `$tabla` SET eliminado=1 WHERE `$pk`=?")->execute([$id]); }catch(Exception $e2){}
        }
        $pdo->prepare("DELETE FROM papelera WHERE tabla=? AND registro_id=?")->execute([$tabla, $id]);
        header('Location: index.php?toast=Registro+eliminado+de+la+papelera&tipo=warn');
        exit;
    }
}

include '../config/menu.php';

// Papelera real: solo muestra registros enviados a papelera (accion='eliminado').
// No muestra dados de baja, pausados ni activados; esos se ven en sus módulos.
$auditoria = $pdo->query("SELECT * FROM papelera WHERE accion='eliminado' ORDER BY fecha DESC")->fetchAll();
$rows = [];
foreach($auditoria as $r){
    if(!isset($tablasPermitidas[$r['tabla']])) continue;
    $pk = $tablasPermitidas[$r['tabla']]['pk'];
    if(registro_sigue_en_papelera($pdo, $r['tabla'], $pk, (int)$r['registro_id'])){
        $clave = $r['tabla'].'-'.$r['registro_id'];
        if(!isset($rows[$clave])) $rows[$clave] = $r;
    }
}
$rows = array_values($rows);
?>

<h1>Papelera</h1>
<p>Acá aparecen solamente los registros eliminados. Los registros dados de baja se siguen manejando desde su propio módulo.</p>
<div class="alert"><b>Atención:</b> si elegís <b>Eliminar</b>, el registro se quitará de la papelera. Si MySQL no permite borrarlo por tener historial relacionado, quedará oculto del sistema pero no aparecerá más acá.</div>

<?php if(empty($rows)): ?>
    <div class="card"><p>No hay registros en la papelera.</p></div>
<?php else: ?>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Módulo</th>
            <th>ID Registro</th>
            <th>Acción</th>
            <th>Realizado por</th>
            <th>Opciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($rows as $r):
            $modulo = $tablasPermitidas[$r['tabla']]['nombre'] ?? $r['tabla'];
        ?>
        <tr>
            <td><?=h($r['fecha'])?></td>
            <td><?=h($modulo)?></td>
            <td><?= (int)$r['registro_id'] ?></td>
            <td><span class="pill estado-problema">En papelera</span></td>
            <td><?=h($r['usuario'])?></td>
            <td>
                <a class="btn blue" href="?tabla=<?=urlencode($r['tabla'])?>&restaurar=<?=(int)$r['registro_id']?>" onclick="return confirm('¿Restaurar este registro?')">Restaurar</a>
                <a class="btn red" href="?tabla=<?=urlencode($r['tabla'])?>&eliminar_def=<?=(int)$r['registro_id']?>" onclick="return confirm('¿Eliminar este registro de la papelera? Esta acción no se puede deshacer desde el sistema.')">Eliminar</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
