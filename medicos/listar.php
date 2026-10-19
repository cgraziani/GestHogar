<?php
require_once '../config/session.php';
require_once '../config/db.php';
solo_admin();

const TIPOS_PROFESIONAL = [
    'medico'         => 'Médico/a',
    'kinesiologo'          => 'Kinesiólogo/a',
    'fisioterapeuta'       => 'Fisioterapeuta',
    'fonoaudiologo'        => 'Fonoaudiólogo/a',
    'psicologo'            => 'Psicólogo/a',
    'psiquiatra'           => 'Psiquiatra',
    'terapista_ocupacional'=> 'Terapista Ocupacional',
    'podologo'       => 'Podólogo/a',
    'enfermero'      => 'Enfermero/a',
    'nutricionista'  => 'Nutricionista',
    'peluquero'      => 'Peluquero/a',
    'manicura'       => 'Manicura',
    'otro'           => 'Otro',
];
const TIPOS_CON_MATRICULA = ['medico','kinesiologo','fisioterapeuta','podologo','enfermero',
                              'nutricionista','fonoaudiologo','psicologo','psiquiatra','terapista_ocupacional'];

if (isset($_GET['baja'])) {
    $id = (int)$_GET['baja'];
    $st = $pdo->prepare("SELECT id_usuario FROM medicos WHERE id_medico=?");
    $st->execute([$id]);
    $id_u = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE medicos SET estado='baja', updated_by=? WHERE id_medico=?")->execute([uid(), $id]);
    if ($id_u > 0) {
        $pdo->prepare("UPDATE usuarios SET estado='baja' WHERE id_usuario=?")->execute([$id_u]);
    }
    header('Location: listar.php?toast=Profesional+dado+de+baja&tipo=ok'); exit;
}
if (isset($_GET['activar'])) {
    $id = (int)$_GET['activar'];
    $st = $pdo->prepare("SELECT id_usuario FROM medicos WHERE id_medico=?");
    $st->execute([$id]);
    $id_u = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE medicos SET estado='activo', updated_by=? WHERE id_medico=?")->execute([uid(), $id]);
    if ($id_u > 0) {
        $pdo->prepare("UPDATE usuarios SET estado='activo' WHERE id_usuario=?")->execute([$id_u]);
    }
    header('Location: listar.php?toast=Profesional+reactivado&tipo=ok'); exit;
}

if (isset($_GET['dar_acceso'])) {
    $med = $pdo->prepare("SELECT * FROM medicos WHERE id_medico=?");
    $med->execute([(int)$_GET['dar_acceso']]);
    $med = $med->fetch();
    if ($med && $med['email'] && !$med['id_usuario']) {
        $existe = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE email=?");
        $existe->execute([$med['email']]);
        $usuario_existente = $existe->fetchColumn();

        if ($usuario_existente) {
            $pass_temp = strtolower($med['apellido']);
            $hash      = password_hash($pass_temp, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE medicos SET id_usuario=? WHERE id_medico=?")
                ->execute([$usuario_existente, $med['id_medico']]);
            $pdo->prepare("UPDATE usuarios SET password=?, rol='profesional' WHERE id_usuario=?")
                ->execute([$hash, $usuario_existente]);
            require_once '../config/email.php';
            $email_ok  = email_acceso_profesional($med['email'], $med['nombre'].' '.$med['apellido'], $pass_temp);
            $_SESSION['acceso_creado'] = [
                'nombre'      => $med['nombre'].' '.$med['apellido'],
                'email'       => $med['email'],
                'password'    => $pass_temp,
                'email_ok'    => $email_ok === true,
                'email_error' => $email_ok !== true ? $email_ok : null,
            ];
            header('Location: listar.php'); exit;
        }

        try {
            $pass_temp = strtolower($med['apellido']);
            $hash      = password_hash($pass_temp, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO usuarios(nombre,apellido,email,password,rol,estado,created_by)
                           VALUES(?,?,?,?,'profesional','activo',?)")
                ->execute([$med['nombre'], $med['apellido'], $med['email'], $hash, uid()]);
            $id_u = $pdo->lastInsertId();
            $pdo->prepare("UPDATE medicos SET id_usuario=? WHERE id_medico=?")
                ->execute([$id_u, $med['id_medico']]);
            require_once '../config/email.php';
            $resultado_email = email_acceso_profesional(
                $med['email'],
                $med['nombre'].' '.$med['apellido'],
                $pass_temp
            );
            $_SESSION['acceso_creado'] = [
                'nombre'       => $med['nombre'].' '.$med['apellido'],
                'email'        => $med['email'],
                'password'     => $pass_temp,
                'email_ok'     => $resultado_email === true,
                'email_error'  => $resultado_email !== true ? $resultado_email : null,
            ];
            header('Location: listar.php'); exit;
        } catch (PDOException $e) {
            header('Location: listar.php?toast='.urlencode('Error al crear el acceso. Verificá que el email no esté en uso.').'&tipo=error'); exit;
        }
    }
    header('Location: listar.php?toast='.urlencode('El profesional necesita email para tener acceso.').'&tipo=error'); exit;
}

if (isset($_GET['quitar_acceso'])) {
    $med = $pdo->prepare("SELECT id_usuario FROM medicos WHERE id_medico=?");
    $med->execute([(int)$_GET['quitar_acceso']]);
    $id_u = $med->fetchColumn();
    if ($id_u) {
        $pdo->prepare("UPDATE usuarios SET estado='baja' WHERE id_usuario=?")->execute([$id_u]);
        $pdo->prepare("UPDATE medicos SET id_usuario=NULL WHERE id_medico=?")->execute([(int)$_GET['quitar_acceso']]);
    }
    header('Location: listar.php?toast=Acceso+al+sistema+desactivado&tipo=warn'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id_medico'] ?? 0);
    $nombre    = trim($_POST['nombre']   ?? '');
    $apellido  = trim($_POST['apellido'] ?? '');
    $tipo_prof = $_POST['tipo_profesional'] ?? 'medico';
    $matricula = trim($_POST['matricula'] ?? '');
    $email     = trim($_POST['email']    ?? '');

    if (!$nombre)   { header('Location: listar.php?err_modal='.urlencode('El nombre es obligatorio.')); exit; }
    if (!$apellido) { header('Location: listar.php?err_modal='.urlencode('El apellido es obligatorio.')); exit; }
    if (!array_key_exists($tipo_prof, TIPOS_PROFESIONAL)) $tipo_prof = 'otro';
    if (in_array($tipo_prof, TIPOS_CON_MATRICULA, true) && !$matricula) {
        header('Location: listar.php?err_modal='.urlencode('La matrícula es obligatoria para este tipo de profesional.')); exit;
    }

    if ($matricula) {
        $dup = $pdo->prepare("SELECT COUNT(*) FROM medicos WHERE matricula=? AND id_medico!=?");
        $dup->execute([$matricula, $id]);
        if ($dup->fetchColumn() > 0) {
            header('Location: listar.php?err_modal='.urlencode('Ya existe un profesional con esa matrícula.')); exit;
        }
    }

    try {
        if ($id) {
            $pdo->prepare("UPDATE medicos SET nombre=?,apellido=?,tipo_profesional=?,matricula=?,
                           especialidad=?,telefono=?,email=?,observaciones=?,updated_by=? WHERE id_medico=?")
                ->execute([$nombre, $apellido, $tipo_prof, $matricula ?: null,
                           trim($_POST['especialidad'] ?? '') ?: null,
                           trim($_POST['telefono']     ?? '') ?: null,
                           $email ?: null,
                           trim($_POST['observaciones'] ?? '') ?: null,
                           uid(), $id]);
            header('Location: listar.php?toast=Profesional+actualizado+correctamente&tipo=ok');
        } else {
            $pdo->prepare("INSERT INTO medicos(nombre,apellido,tipo_profesional,matricula,especialidad,
                           telefono,email,observaciones,created_by) VALUES(?,?,?,?,?,?,?,?,?)")
                ->execute([$nombre, $apellido, $tipo_prof, $matricula ?: null,
                           trim($_POST['especialidad'] ?? '') ?: null,
                           trim($_POST['telefono']     ?? '') ?: null,
                           $email ?: null,
                           trim($_POST['observaciones'] ?? '') ?: null,
                           uid()]);
            $nuevo_id_medico = (int)$pdo->lastInsertId();

            $ids_res_nuevos = $_POST['residentes_nuevos'] ?? [];
            if (!empty($ids_res_nuevos) && $nuevo_id_medico) {
                $st_asig = $pdo->prepare("INSERT INTO visitas_medicas
                    (id_residente,id_medico,id_usuario,fecha_hora,motivo,genera_tareas)
                    VALUES(?,?,?,NOW(),'Asignación inicial por administradora',0)");
                foreach ($ids_res_nuevos as $id_r) {
                    $st_asig->execute([(int)$id_r, $nuevo_id_medico, uid()]);
                }
            }
            header('Location: listar.php?toast=Profesional+creado+correctamente&tipo=ok');
        }
    } catch (PDOException $e) {
        $_SESSION['error_form'] = 'Error al guardar. Verificá los datos e intentá de nuevo.';
        header('Location: listar.php');
    }
    exit;
}

include '../config/menu.php';
$medicos = $pdo->query("SELECT m.*, u.estado u_estado
    FROM medicos m LEFT JOIN usuarios u ON u.id_usuario=m.id_usuario
    ORDER BY m.estado, m.apellido, m.nombre")->fetchAll();

if (isset($_POST['asignar_residentes'])) {
    $id_med_asig = (int)$_POST['id_medico_asig'];
    $ids_res     = $_POST['residentes_asig'] ?? [];
    if ($id_med_asig && !empty($ids_res)) {
        foreach ($ids_res as $id_r) {
            $existe = $pdo->prepare("SELECT COUNT(*) FROM visitas_medicas WHERE id_medico=? AND id_residente=?");
            $existe->execute([$id_med_asig, (int)$id_r]);
            if (!$existe->fetchColumn()) {
                $pdo->prepare("INSERT INTO visitas_medicas(id_residente,id_medico,id_usuario,fecha_hora,motivo,genera_tareas)
                               VALUES(?,?,?,NOW(),'Asignación inicial por administradora',0)")
                    ->execute([(int)$id_r, $id_med_asig, uid()]);
            }
        }
        header('Location: listar.php?tab=atenciones&toast='.urlencode('Residentes asignados correctamente').'&tipo=ok'); exit;
    }
}

if (isset($_GET['quitar_res']) && isset($_GET['med'])) {
    $id_med_q = (int)$_GET['med'];
    $id_res_q = (int)$_GET['quitar_res'];
    $pdo->prepare("DELETE FROM visitas_medicas
                   WHERE id_medico=? AND id_residente=? AND motivo='Asignación inicial por administradora'")
        ->execute([$id_med_q, $id_res_q]);
    $quedan = $pdo->prepare("SELECT COUNT(*) FROM visitas_medicas WHERE id_medico=? AND id_residente=?");
    $quedan->execute([$id_med_q, $id_res_q]);
    $msg = $quedan->fetchColumn() > 0
        ? 'El profesional tiene atenciones registradas con este residente. Solo se quitó la asignación inicial.'
        : 'Residente desasignado del profesional.';
    header('Location: listar.php?tab=atenciones&toast='.urlencode($msg).'&tipo=ok'); exit;
}

$tab_activa  = $_GET['tab'] ?? 'profesionales';
$residentes  = $pdo->query("SELECT id_residente,nombre,apellido FROM residentes WHERE estado='activo' ORDER BY apellido")->fetchAll();
$id_res_vm   = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$id_med_vm   = isset($_GET['medico'])    ? (int)$_GET['medico']    : 0;
$where_vm    = 'WHERE 1=1';
if ($id_res_vm) $where_vm .= ' AND vm.id_residente='.$id_res_vm;
if ($id_med_vm) $where_vm .= ' AND vm.id_medico='.$id_med_vm;
$atenciones = $pdo->query("
    SELECT vm.*, r.nombre rn, r.apellido ra,
           m.nombre mn, m.apellido ma, m.especialidad, m.tipo_profesional,
           CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) reg_por
    FROM visitas_medicas vm
    JOIN residentes r ON r.id_residente=vm.id_residente
    JOIN medicos m ON m.id_medico=vm.id_medico
    LEFT JOIN usuarios u ON u.id_usuario=vm.id_usuario
    $where_vm
    ORDER BY vm.fecha_hora DESC LIMIT 200")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_atencion_m'])) {
    $id_r  = (int)$_POST['id_residente'];
    $id_m  = (int)$_POST['id_medico'];
    $mot   = trim($_POST['motivo'] ?? '');
    $gen   = isset($_POST['genera_tareas']) ? 1 : 0;
    if (!$id_r || !$id_m || !$mot) {
        header('Location: listar.php?tab=atenciones&toast='.urlencode('Residente, profesional y motivo son obligatorios.').'&tipo=error'); exit;
    }
    try {
        $pdo->prepare("INSERT INTO visitas_medicas(id_residente,id_medico,id_usuario,fecha_hora,motivo,observaciones,diagnostico,indicaciones,receta,genera_tareas) VALUES(?,?,?,?,?,?,?,?,?,?)")
            ->execute([$id_r,$id_m,uid(),$_POST['fecha_hora']??date('Y-m-d H:i:s'),$mot,
                       trim($_POST['observaciones']??'')?:null,
                       trim($_POST['diagnostico']??'')?:null,
                       trim($_POST['indicaciones']??'')?:null,
                       trim($_POST['receta']??'')?:null,$gen]);
        if ($gen && trim($_POST['indicaciones']??'')) {
            $mn = $pdo->prepare("SELECT nombre,apellido FROM medicos WHERE id_medico=?");
            $mn->execute([$id_m]); $mn=$mn->fetch();
            $pdo->prepare("INSERT INTO tareas_residente(id_residente,titulo,descripcion,tipo,created_by) VALUES(?,?,?,'indicacion_prof',?)")
                ->execute([$id_r,'Indicación: '.mb_substr($_POST['indicaciones'],0,80),
                           'Indicado por '.($mn['nombre']??'').' '.($mn['apellido']??'').' el '.date('d/m/Y').'. '.$_POST['indicaciones'],uid()]);
        }
        header('Location: listar.php?tab=atenciones&toast=Atenci%C3%B3n+registrada&tipo=ok'); exit;
    } catch (PDOException $e) {
        header('Location: listar.php?tab=atenciones&toast='.urlencode('Error al guardar.').'&tipo=error'); exit;
    }
}
?>

<?php if (!empty($_SESSION['error_form'])): ?>
<div style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
<?= htmlspecialchars($_SESSION['error_form']); unset($_SESSION['error_form']); ?>
</div>
<?php endif; ?>

<?php if (!empty($_SESSION['acceso_creado'])):
    $ac = $_SESSION['acceso_creado'];
    unset($_SESSION['acceso_creado']);
    $url_login = (isset($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].BASE_URL.'/auth/login.php';
?>
<div style="background:#e8f5e9;border-left:5px solid #27ae60;border-radius:8px;padding:16px 20px;margin-bottom:20px">
    <h3 style="margin:0 0 6px;color:#1e7e34">Acceso creado para <?= h($ac['nombre']) ?></h3>

    <?php if ($ac['email_ok']): ?>
        <p style="margin:0 0 12px;font-size:14px;color:#27ae60">
            Se envió un email a <b><?= h($ac['email']) ?></b> con los datos de acceso.
        </p>
    <?php else: ?>
        <p style="margin:0 0 12px;font-size:14px;color:#e67e22">
No se pudo enviar el email automático. Compartí estos datos manualmente:
        </p>
    <?php endif; ?>

    <div style="background:#fff;border:1px solid #a5d6a7;border-radius:6px;padding:14px 16px;font-size:14px;line-height:1.8">
        <div><b>URL del sistema:</b> <a href="<?= h($url_login) ?>" style="color:#2D9B8A"><?= h($url_login) ?></a></div>
        <div><b>Email:</b> <?= h($ac['email']) ?></div>
        <div><b>Contraseña temporal:</b>
            <span style="background:#f0f9f7;border:1px solid #2D9B8A;border-radius:4px;padding:2px 10px;font-family:monospace;font-weight:700;color:#2D9B8A;font-size:15px">
                <?= h($ac['password']) ?>
            </span>
        </div>
    </div>
    <?php if (!$ac['email_ok'] && $ac['email_error']): ?>
        <p class="small" style="margin:6px 0 0;color:#e74c3c">Error técnico: <?= h($ac['email_error']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<h1 style="margin:0 0 16px">Profesionales externos y atenciones</h1>

<div style="display:flex;gap:8px;margin-bottom:20px">
    <a class="tab-btn <?= $tab_activa==='profesionales'?'active':'' ?>" href="listar.php?tab=profesionales">
Profesionales
    </a>
    <a class="tab-btn <?= $tab_activa==='atenciones'?'active':'' ?>" href="listar.php?tab=atenciones">
         Atenciones
    </a>
</div>
<style>
.tab-btn{padding:8px 18px;border:2px solid var(--color-primary,#2D9B8A);background:transparent;
    color:var(--color-primary,#2D9B8A);border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
.tab-btn.active{background:var(--color-primary,#2D9B8A);color:#fff}
</style>

<div id="tab-prof" style="display:<?= $tab_activa==='profesionales'?'block':'none' ?>">
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    
    <button class="btn" onclick="abrirModal()">+ Registrar profesional</button>
</div>

<?php if (empty($medicos)): ?>
<div style="text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px">
    <div style="font-size:40px"></div>
    <b>No hay profesionales registrados todavía</b>
</div>
<?php else: ?>
<table class="datatable">
    <thead><tr>
        <th>Profesional</th><th>Rubro</th><th>Matrícula</th><th>Especialidad</th>
        <th>Teléfono</th><th>Email</th><th>Acceso sistema</th><th>Estado</th><th>Acciones</th>
    </tr></thead>
    <tbody>
    <?php foreach ($medicos as $m): ?>
        <tr>
            <td><b><?= h($m['apellido'].', '.$m['nombre']) ?></b></td>
            <td><span class="badge"><?= h(TIPOS_PROFESIONAL[$m['tipo_profesional']] ?? $m['tipo_profesional']) ?></span></td>
            <td><?= h($m['matricula'] ?: '') ?></td>
            <td><?= h($m['especialidad'] ?? '') ?></td>
            <td><?= h($m['telefono'] ?? '') ?></td>
            <td><?= h($m['email'] ?? '') ?></td>
            <?php $sin_acceso = in_array($m['tipo_profesional'], ['peluquero','manicura']); ?>
            <td>
                <?php if ($sin_acceso): ?>
                    <span class="small" style="color:#aaa">Sin acceso al sistema</span>
                <?php elseif ($m['id_usuario'] && $m['u_estado']==='activo'): ?>
                    <span class="pill pill-ok"> Activo</span>
                    <a class="btn gray" style="font-size:11px"
                       href="?quitar_acceso=<?= $m['id_medico'] ?>"
                       onclick="return confirmarLink(this,'¿Quitar acceso al sistema?','Quitar','red')">Quitar</a>
                <?php elseif ($m['email']): ?>
                    <a class="btn" style="font-size:11px"
                       href="?dar_acceso=<?= $m['id_medico'] ?>"
                       onclick="return confirmarLink(this,'¿Dar acceso? La contraseña temporal será el apellido en minúsculas.','Dar acceso','green')">
                        Dar acceso
                    </a>
                <?php else: ?>
                    <span class="small" style="color:#aaa">Necesita email</span>
                <?php endif; ?>
            </td>
            <td><span class="pill <?= $m['estado']==='activo'?'estado-activo':'estado-problema' ?>">
                <?= $m['estado']==='activo'?'Activo':'Baja' ?></span></td>
            <td>
                <button class="btn" onclick="abrirModal(<?= h(json_encode($m)) ?>)">Modificar</button>
                <?php if ($m['estado']==='activo'): ?>
                    <a class="btn red" href="?baja=<?= $m['id_medico'] ?>"
                       onclick="return confirmarLink(this,'¿Dar de baja a <?= h($m['apellido']) ?>?','Dar de baja','red')">Dar de baja</a>
                <?php else: ?>
                    <a class="btn green" href="?activar=<?= $m['id_medico'] ?>">Reactivar</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

</div>

<div id="tab-at" style="display:<?= $tab_activa==='atenciones'?'block':'none' ?>">

<div class="card" style="margin-bottom:16px">
    <h3 style="margin-top:0">Asignación de residentes por profesional</h3>

    <?php
    $asignaciones = $pdo->query("SELECT m.id_medico, m.nombre mn, m.apellido ma,
        r.id_residente, r.nombre rn, r.apellido ra,
        COUNT(vm.id_visita_medica) n_atenciones,
        MIN(vm.motivo) primer_motivo
        FROM visitas_medicas vm
        JOIN medicos m ON m.id_medico=vm.id_medico
        JOIN residentes r ON r.id_residente=vm.id_residente
        WHERE r.estado='activo' AND m.estado='activo'
        GROUP BY m.id_medico, r.id_residente
        ORDER BY m.apellido, m.nombre, r.apellido, r.nombre")->fetchAll();

    $por_medico = [];
    foreach ($asignaciones as $a) {
        $por_medico[$a['id_medico']]['medico'] = $a['mn'].' '.$a['ma'];
        $por_medico[$a['id_medico']]['residentes'][] = $a;
    }
    ?>

    <?php if (!empty($por_medico)): ?>
    <div style="margin-bottom:16px">
        <?php foreach ($por_medico as $id_m => $data): ?>
        <div style="border:1px solid var(--border-color,#eee);border-radius:8px;padding:12px 14px;margin-bottom:10px">
            <b style="font-size:14px"><?= h($data['medico']) ?></b>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px">
                <?php foreach ($data['residentes'] as $res_asig):
                    $es_inicial = $res_asig['primer_motivo'] === 'Asignación inicial por administradora';
                    $solo_inicial = $res_asig['n_atenciones'] == 1 && $es_inicial;
                ?>
                <div style="display:flex;align-items:center;gap:6px;background:var(--bg-page,#f5f6fa);
                            border-radius:6px;padding:4px 10px;font-size:13px">
                    <span><?= h($res_asig['rn'].' '.$res_asig['ra']) ?></span>
                    <?php if (!$solo_inicial): ?>
                        <span class="small" style="color:var(--text-muted)">(<?= (int)$res_asig['n_atenciones'] ?> atenciones)</span>
                    <?php endif; ?>
                    <a href="?tab=atenciones&quitar_res=<?= $res_asig['id_residente'] ?>&med=<?= $id_m ?>"
                       style="color:#e74c3c;font-size:16px;text-decoration:none;font-weight:700;line-height:1"
                       onclick="return confirmarLink(this,'¿Quitar a <?= h($res_asig['rn'].' '.$res_asig['ra']) ?> de este profesional?','Quitar','red')"
                       title="Quitar asignación">×</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="asignar_residentes" value="1">
        <div>
            <label style="font-size:12px;font-weight:600;display:block">Profesional</label>
            <select name="id_medico_asig" required class="buscador" style="min-width:200px">
                <option value="">Elegir...</option>
                <?php foreach ($medicos as $m): ?>
                    <?php if ($m['estado']==='activo'): ?>
                    <option value="<?= $m['id_medico'] ?>"><?= h($m['apellido'].', '.$m['nombre']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;display:block">Residentes a asignar</label>
            <div style="border:1px solid var(--border-color,#ddd);border-radius:8px;padding:8px 12px;
                        display:flex;flex-wrap:wrap;gap:8px;min-width:300px">
                <?php foreach ($residentes as $r): ?>
                <label style="display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer">
                    <input type="checkbox" name="residentes_asig[]" value="<?= $r['id_residente'] ?>" style="width:auto">
                    <?= h($r['nombre'].' '.$r['apellido']) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn">Asignar</button>
    </form>
</div>

<div style="display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <button class="btn" onclick="document.getElementById('modal-atencion').style.display='flex'">+ Registrar atención</button>
</div>

<div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px">
    <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="tab" value="atenciones">
        <div>
            <label style="font-size:12px;font-weight:600;display:block">Residente</label>
            <select name="residente" class="buscador" style="min-width:180px">
                <option value="0">Todos</option>
                <?php foreach ($residentes as $r): ?>
                    <option value="<?= $r['id_residente'] ?>" <?= $id_res_vm==$r['id_residente']?'selected':'' ?>>
                        <?= h($r['nombre'].' '.$r['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="font-size:12px;font-weight:600;display:block">Profesional</label>
            <select name="medico" class="buscador" style="min-width:180px">
                <option value="0">Todos</option>
                <?php foreach ($medicos as $m): ?>
                    <option value="<?= $m['id_medico'] ?>" <?= $id_med_vm==$m['id_medico']?'selected':'' ?>>
                        <?= h($m['apellido'].', '.$m['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn gray">Filtrar</button>
        <a href="listar.php?tab=atenciones" class="btn gray">Limpiar</a>
    </form>
</div>

<?php if (empty($atenciones)): ?>
<div style="text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px">
    <div style="font-size:40px"></div><b>Sin atenciones registradas</b>
</div>
<?php else: ?>
<table class="datatable">
    <thead><tr>
        <th>Fecha</th><th>Profesional</th><th>Residente</th><th>Motivo</th>
        <th>Diagnóstico</th><th>Indicaciones</th><th>Genera tarea</th><th>Receta</th>
    </tr></thead>
    <tbody>
    <?php foreach ($atenciones as $v):
        $tipos = TIPOS_PROFESIONAL;
    ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($v['fecha_hora'])) ?></td>
            <td><b><?= h($v['ma'].', '.$v['mn']) ?></b><br><span class="small"><?= h($tipos[$v['tipo_profesional']] ?? $v['tipo_profesional']) ?></span></td>
            <td><?= h($v['rn'].' '.$v['ra']) ?></td>
            <td><?= h($v['motivo']) ?></td>
            <td style="font-size:13px"><?= h($v['diagnostico']  ?? '') ?></td>
            <td style="font-size:13px"><?= h($v['indicaciones'] ?? '') ?></td>
            <td><?= $v['genera_tareas'] ? '<span class="pill pill-ok"> Sí</span>' : '' ?></td>
            <td style="font-size:13px"><?= h($v['receta'] ?? '') ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div id="modal-atencion" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:24px 28px;width:100%;max-width:680px;position:relative;max-height:90vh;overflow-y:auto">
        <button onclick="document.getElementById('modal-atencion').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit"></button>
        <h3 style="margin:0 0 16px">Registrar atención profesional</h3>
        <form method="post">
            <input type="hidden" name="nueva_atencion_m" value="1">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Residente *</label>
                    <select name="id_residente" required class="buscador" style="width:100%;box-sizing:border-box">
                        <option value="">Elegir...</option>
                        <?php foreach ($residentes as $r): ?><option value="<?= $r['id_residente'] ?>"><?= h($r['nombre'].' '.$r['apellido']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Profesional *</label>
                    <select name="id_medico" required class="buscador" style="width:100%;box-sizing:border-box">
                        <option value="">Elegir...</option>
                        <?php foreach ($medicos as $m): ?><option value="<?= $m['id_medico'] ?>"><?= h($m['apellido'].', '.$m['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Fecha y hora</label>
                    <input type="datetime-local" name="fecha_hora" value="<?= date('Y-m-d\TH:i') ?>" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Motivo *</label>
                    <input name="motivo" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Diagnóstico / Evolución</label>
                    <textarea name="diagnostico" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Observaciones</label>
                    <textarea name="observaciones" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-size:13px;font-weight:600;display:block">Indicaciones para las cuidadoras</label>
                    <textarea name="indicaciones" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                </div>
                <div style="grid-column:1/-1">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="genera_tareas" value="1" style="width:auto">
                        <b>Convertir indicaciones en tarea para las cuidadoras</b>
                    </label>
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-size:13px;font-weight:600;display:block">Receta / Medicación indicada</label>
                    <textarea name="receta" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                </div>
            </div>
            <div style="margin-top:16px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray" onclick="document.getElementById('modal-atencion').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

</div>

<div id="modal-medico" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:560px;position:relative;max-height:90vh;overflow-y:auto">
        <button onclick="cerrarModal()" style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit"></button>
        <h2 id="modal-titulo" style="margin:0 0 16px">Registrar profesional</h2>
        <div id="modal-error" style="display:none;background:#fdecea;border-left:4px solid #e74c3c;
             padding:10px 14px;border-radius:6px;margin-bottom:14px;font-size:13px"></div>
        <form method="post">
            <input type="hidden" name="id_medico" id="f-id">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Nombre *</label>
                    <input name="nombre" id="f-nombre" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Apellido *</label>
                    <input name="apellido" id="f-apellido" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Rubro *</label>
                    <select name="tipo_profesional" id="f-tipo" required style="width:100%;box-sizing:border-box">
                        <?php foreach (TIPOS_PROFESIONAL as $k=>$v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Matrícula</label>
                    <input name="matricula" id="f-matricula" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Especialidad</label>
                    <input name="especialidad" id="f-especialidad" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Teléfono</label>
                    <input name="telefono" id="f-telefono" style="width:100%;box-sizing:border-box">
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-size:13px;font-weight:600;display:block">Email <span style="color:#aaa;font-weight:400">(necesario para dar acceso al sistema)</span></label>
                    <input name="email" type="email" id="f-email" style="width:100%;box-sizing:border-box">
                </div>
                <div style="grid-column:1/-1">
                    <label style="font-size:13px;font-weight:600;display:block">Observaciones</label>
                    <textarea name="observaciones" id="f-obs" rows="2" style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
                </div>
                <div style="grid-column:1/-1" id="seccion-residentes">
                    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
                        Residentes que atiende
                        <span style="font-weight:400;color:#aaa">(podés asignarlos ahora o después)</span>
                    </label>
                    <div style="border:1px solid var(--border-color,#ddd);border-radius:8px;padding:10px 14px;
                                display:grid;grid-template-columns:1fr 1fr;gap:6px">
                        <?php foreach ($residentes as $r): ?>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                            <input type="checkbox" name="residentes_nuevos[]"
                                   value="<?= $r['id_residente'] ?>"
                                   class="cb-res-nuevo" style="width:auto">
                            <?= h($r['nombre'].' '.$r['apellido']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar</button>
                <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
<?php if (!empty($_GET['err_modal'])): ?>
window.addEventListener('DOMContentLoaded', function() {
    var err = <?= json_encode($_GET['err_modal']) ?>;
    var div = document.getElementById('modal-error');
    div.textContent = err;
    div.style.display = 'block';
    document.getElementById('modal-medico').style.display = 'flex';
    document.getElementById('seccion-residentes').style.display = 'block';
});
<?php endif; ?>

function abrirModal(d) {
    document.getElementById('modal-titulo').textContent    = d ? 'Modificar profesional' : 'Registrar profesional';
    document.getElementById('f-id').value                 = d ? d.id_medico        : '';
    document.getElementById('f-nombre').value             = d ? d.nombre           : '';
    document.getElementById('f-apellido').value           = d ? d.apellido         : '';
    document.getElementById('f-tipo').value               = d ? d.tipo_profesional : 'medico';
    document.getElementById('f-matricula').value          = d ? (d.matricula   ||'') : '';
    document.getElementById('f-especialidad').value       = d ? (d.especialidad||'') : '';
    document.getElementById('f-telefono').value           = d ? (d.telefono    ||'') : '';
    document.getElementById('f-email').value              = d ? (d.email       ||'') : '';
    document.getElementById('f-obs').value                = d ? (d.observaciones||'') : '';
    document.getElementById('seccion-residentes').style.display = d ? 'none' : 'block';
    document.querySelectorAll('.cb-res-nuevo').forEach(cb => cb.checked = false);
    document.getElementById('modal-error').style.display = 'none';
    document.getElementById('modal-medico').style.display = 'flex';
}
function cerrarModal() { document.getElementById('modal-medico').style.display = 'none'; }
window.addEventListener('click', e => { if (e.target===document.getElementById('modal-medico')) cerrarModal(); });
</script>

<?php include '../config/footer.php'; ?>
