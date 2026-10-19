<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/ajustes.php';

define('SESSION_TIMEOUT', 1800);
if (!empty($_SESSION['usuario'])) {
    if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) > SESSION_TIMEOUT) {
        session_unset(); session_destroy();
        header('Location: ' . BASE_URL . '/auth/login.php?motivo=inactividad'); exit;
    }
    $_SESSION['ultima_actividad'] = time();
}

function require_login() {
    if (empty($_SESSION['usuario'])) {
        header('Location: ' . BASE_URL . '/auth/login.php'); exit;
    }
}
function rol()            { return $_SESSION['usuario']['rol']        ?? ''; }
function uid()            { return $_SESSION['usuario']['id_usuario'] ?? 0;  }
function es_admin()       { return rol() === 'admin';        }
function es_cuidadora()   { return rol() === 'cuidadora';    }
function es_cocinera()    { return rol() === 'cocinera';     }
function es_asistente()   { return rol() === 'asistente';    }
function es_profesional() { return rol() === 'profesional';  }

function acceso_denegado($mensaje = 'No tenés permisos para acceder a esta página.') {
    include __DIR__ . '/menu.php';
    echo '<div class="card" style="border-left:4px solid #E05C5C;padding:20px">
        <h2>Acceso denegado</h2>
        <p>' . htmlspecialchars($mensaje) . '</p>
        <a class="btn gray" href="' . BASE_URL . '/index.php">← Volver al inicio</a>
    </div>';
    include __DIR__ . '/footer.php';
    exit;
}
function solo_admin() {
    require_login();
    if (!es_admin()) acceso_denegado('Esta sección es solo para la administradora.');
}
function puede_cuidar() {
    require_login();
    if (!(es_admin() || es_cuidadora() || es_asistente()))
        acceso_denegado('Esta sección es para cuidadoras y asistentes.');
}
function puede_cocina() {
    require_login();
    if (!(es_admin() || es_cocinera()))
        acceso_denegado('Esta sección es solo para la cocinera.');
}
function puede_ver_atenciones() {
    require_login();
    if (!(es_admin() || es_cuidadora() || es_asistente() || es_profesional()))
        acceso_denegado('No tenés permisos para ver esta sección.');
}

