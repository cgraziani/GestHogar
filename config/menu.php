<?php require_once __DIR__ . '/session.php';
require_login();
include __DIR__ . '/header.php'; ?>
<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<?php
function es_pagina_actual($ruta_completa) {
    $actual = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    return $actual === rtrim($ruta_completa, '/');
}
?>

<?php
function menu_link($ruta, $texto) {
    $url = BASE_URL . $ruta;
    if (es_pagina_actual($url)) {
        echo '<a class="button-menu active" aria-current="page" aria-disabled="true">' . htmlspecialchars($texto) . '</a>';
    } else {
        echo '<a class="button-menu" href="' . $url . '">' . htmlspecialchars($texto) . '</a>';
    }
}

function menu_group_abierto($rutas) {
    foreach ($rutas as $r) {
        if (es_pagina_actual(BASE_URL . $r)) return true;
    }
    return false;
}
?>

<style>
.menu-group { border: none; margin: 0 }
.menu-group summary.button-menu {
    cursor: pointer; user-select: none;
    display: flex; align-items: center; justify-content: space-between;
}
.menu-group summary.button-menu::-webkit-details-marker { display: none }
.menu-group summary.button-menu::after {
    content: '▸'; font-size: 14px; transition: transform .15s; color: currentColor; opacity: .7;
}
.menu-group[open] summary.button-menu::after { transform: rotate(90deg) }
.menu-group .button-menu:not(summary) {
    padding: 12px 16px 12px 34px;
    font-size: 17px;
    font-weight: 400;
}
.menu-group { margin-bottom: 18px; display: block }
.side {
    max-height: calc(100vh - 60px);
    overflow-y: auto;
    overflow-x: hidden;
}
.side::-webkit-scrollbar { width: 6px }
.side::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 3px }
</style>

<header class="top">
<style>
.brand-nuevo {
    font-weight: 900; font-size: 22px; text-decoration: none;
    color: var(--text-primary); display: flex; align-items: center; gap: 8px;
    font-family: 'Segoe UI Rounded', 'Century Gothic', Verdana, 'Trebuchet MS', sans-serif;
    letter-spacing: .3px;
}
.brand-nuevo .brand-logo {
    width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
    border: 1.5px solid #dfe5ec; box-shadow: 0 1px 4px rgba(15,47,74,.15);
}
</style>
    <a class="brand-nuevo" href="<?= BASE_URL ?>/index.php">
        <img class="brand-logo" src="<?= BASE_URL ?>/assets/img/logo_nongo.png" alt="Hogar Ñongo Ñongo">GestHogar
    </a>
    <?php
$nombre = $_SESSION['usuario']['nombre'];
$partes = explode(' ', trim($nombre));
$iniciales = mb_strtoupper(mb_substr($partes[0], 0, 1)) . (isset($partes[1]) ? mb_strtoupper(mb_substr($partes[1], 0, 1)) : '');
?>
<div class="top-right">
    <div class="user-menu" id="userMenu">
       <button class="user-menu__trigger" id="userMenuTrigger" aria-haspopup="true" aria-expanded="false">
          <span class="user-menu__avatar"><?= htmlspecialchars($iniciales) ?></span>
          <span class="user-menu__info">
              <span class="user-menu__name"><?= htmlspecialchars($nombre) ?></span>
              <span class="user-menu__role"><?= htmlspecialchars(rol()) ?></span>
           </span>
          <svg class="user-menu__chevron" width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M2 4l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
     </button>
     <div class="user-menu__dropdown" id="userMenuDropdown">
         <a class="user-menu__item" href="<?= BASE_URL ?>/auth/recuperar.php">
             Cambiar contraseña
         </a>
         <a class="user-menu__item" href="<?= BASE_URL ?>/auth/logout.php">
            Cerrar sesión
         </a>
      </div>
    </div>

    <label class="theme-toggle" aria-label="Cambiar entre modo claro y oscuro">
       <input type="checkbox" id="themeToggle" aria-label="Cambiar a modo oscuro">
      <i class="ti ti-sun theme-toggle__icon theme-toggle__icon--sun" aria-hidden="true"></i>
      <span class="theme-toggle__track">
          <span class="theme-toggle__thumb"></span>
      </span>
      <i class="ti ti-moon theme-toggle__icon theme-toggle__icon--moon" aria-hidden="true"></i>
    </label>
</div>

</header>

<div class="layout">
    <aside class="side">
        <?php if (!es_profesional()) menu_link('/index.php', 'Inicio'); ?>

        <?php
              ?>
        <?php if (es_profesional()):
            $tipo_prof_menu = $_SESSION['_tipo_prof'] ?? '';

            $p_recetas  = in_array($tipo_prof_menu, ['medico','psiquiatra']);
            $p_med      = in_array($tipo_prof_menu, ['medico','psiquiatra','enfermero']);
            $p_ctrl     = in_array($tipo_prof_menu, ['medico','enfermero']);
            $p_restr    = in_array($tipo_prof_menu, ['medico','nutricionista','fonoaudiologo']);
            $p_tareas   = in_array($tipo_prof_menu, ['medico','psiquiatra','enfermero','kinesiologo',
                                    'fisioterapeuta','podologo','fonoaudiologo','psicologo',
                                    'terapista_ocupacional','nutricionista']);
            $p_rep      = !in_array($tipo_prof_menu, ['peluquero']);
        ?>
            <?php menu_link('/profesional/index.php', 'Mis residentes'); ?>

            <?php
            $rutas_at = array_filter([
                '/profesional/atencion.php',
                $p_recetas ? '/medicacion/recetas.php'             : null,
                $p_med     ? '/medicacion/listar.php'              : null,
                $p_ctrl    ? '/controles/plan.php'                 : null,
                $p_restr   ? '/cocina/listar.php'                  : null,
                $p_tareas  ? '/medicacion/tareas.php'              : null,
            ]);
            ?>
            <details class="menu-group" <?= menu_group_abierto(array_values($rutas_at)) ? 'open' : '' ?>>
                <summary class="button-menu">Atenciones</summary>
                <?php menu_link('/profesional/atencion.php',               'Registrar atención'); ?>
                <?php if ($p_recetas): menu_link('/medicacion/recetas.php',              'Recetas y medicación'); endif; ?>
                <?php if ($p_med && !$p_recetas): menu_link('/medicacion/listar.php',   'Medicación vigente'); endif; ?>
                <?php if ($p_ctrl):   menu_link('/controles/plan.php',                  'Plan de controles'); endif; ?>
                <?php if ($p_restr):  menu_link('/cocina/listar.php?tab=restricciones', 'Restricciones alimentarias'); endif; ?>
                <?php if ($p_tareas): menu_link('/medicacion/tareas.php',               'Tareas de cuidado'); endif; ?>
            </details>

            <?php if ($p_rep): menu_link('/profesional/reportes.php', 'Reportes'); endif; ?>

        <?php
              ?>
        <?php elseif (es_cuidadora() || es_asistente()):
            $rutas = ['/medicacion/agenda.php','/turnos/listar.php','/incidentes/listar.php',
                      '/visitas/listar.php','/visitas_medicas/listar.php','/actividades/listar.php',
                      '/controles/listar.php','/medicacion/listar.php','/medicacion/recetas.php',
                      '/medicacion/tareas.php','/cocina/listar.php','/residentes/listar.php',
                      '/turnos/cronograma.php','/turnos/calendario.php'];
        ?>
            <?php menu_link('/medicacion/agenda.php', ' Agenda del turno'); ?>
            <?php menu_link('/turnos/listar.php', ' Traspaso de turno'); ?>

            <details class="menu-group" <?= menu_group_abierto(['/residentes/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Residentes</summary>
                <?php menu_link('/residentes/listar.php', 'Fichas'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/medicacion/listar.php','/medicacion/recetas.php','/medicacion/tareas.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Medicación</summary>
                <?php menu_link('/medicacion/listar.php',  'Medicación vigente'); ?>
                <?php menu_link('/medicacion/recetas.php', 'Recetas'); ?>
                <?php menu_link('/medicacion/tareas.php',  'Tareas de cuidado'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/incidentes/listar.php','/controles/listar.php','/actividades/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Cuidado</summary>
                <?php menu_link('/incidentes/listar.php',  'Incidentes'); ?>
                <?php menu_link('/controles/listar.php',   'Historial de controles'); ?>
                <?php if (es_admin()) menu_link('/controles/plan.php', 'Plan de controles'); ?>
                <?php menu_link('/actividades/listar.php', 'Actividades'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/visitas/listar.php','/visitas_medicas/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Visitas</summary>
                <?php menu_link('/visitas/listar.php',  'Visitas familiares'); ?>
                <?php menu_link('/medicos/listar.php', 'Atenciones profesionales'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/cocina/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Alimentación</summary>
                <?php menu_link('/cocina/listar.php', 'Cocina y restricciones'); ?>
            </details>

            <?php menu_link('/turnos/calendario.php', 'Cronograma de turnos'); ?>

        <?php
              ?>
        <?php elseif (es_cocinera()): ?>
            <?php menu_link('/cocina/listar.php',         'Cocina y restricciones'); ?>
            <?php menu_link('/residentes/listar.php',     'Residentes'); ?>
            <?php menu_link('/turnos/calendario.php',     'Cronograma de turnos'); ?>

        <?php
              ?>
        <?php else: ?>

            <?php menu_link('/medicacion/agenda.php', ' Agenda del turno'); ?>
            <?php menu_link('/turnos/listar.php',     ' Traspaso de turno'); ?>

            <details class="menu-group" <?= menu_group_abierto(['/residentes/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Residentes</summary>
                <?php menu_link('/residentes/listar.php', 'Fichas'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/medicacion/listar.php','/medicacion/recetas.php','/medicacion/tareas.php','/controles/plan.php','/controles/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Medicación y cuidados</summary>
                <?php menu_link('/medicacion/listar.php',  'Medicación vigente'); ?>
                <?php menu_link('/medicacion/recetas.php', 'Recetas'); ?>
                <?php menu_link('/medicacion/tareas.php',  'Tareas de cuidado'); ?>
                <?php menu_link('/controles/plan.php',     'Controles clínicos'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/incidentes/listar.php','/actividades/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Cuidado</summary>
                <?php menu_link('/incidentes/listar.php',  'Incidentes'); ?>
                <?php menu_link('/actividades/listar.php', 'Actividades'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/visitas/listar.php','/medicos/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Visitas y profesionales</summary>
                <?php menu_link('/visitas/listar.php',  'Visitas familiares'); ?>
                <?php menu_link('/medicos/listar.php',  'Atenciones profesionales externas'); ?>
            </details>

            <?php menu_link('/turnos/calendario.php', 'Cronograma de turnos'); ?>

            <details class="menu-group" <?= menu_group_abierto(['/cocina/listar.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Alimentación</summary>
                <?php menu_link('/cocina/listar.php', 'Cocina y restricciones'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/reportes/dashboard.php','/reportes/index.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Reportes</summary>
                <?php menu_link('/reportes/dashboard.php', 'Dashboard'); ?>
                <?php menu_link('/reportes/index.php',     'Todos los reportes'); ?>
            </details>

            <details class="menu-group" <?= menu_group_abierto(['/admin/usuarios.php','/habitaciones/listar.php','/admin/configuracion.php','/papelera/index.php']) ? 'open' : '' ?>>
                <summary class="button-menu">Administración</summary>
                <?php menu_link('/admin/usuarios.php',       'Usuarios y cuidadoras'); ?>
                <?php menu_link('/habitaciones/listar.php',  'Habitaciones'); ?>
                <?php menu_link('/admin/configuracion.php',  'Configuración'); ?>
                <?php menu_link('/papelera/index.php',       'Historial de auditoría'); ?>
            </details>

        <?php endif; ?>
    </aside>


    <main class="content">

<script>
  const userMenu = document.getElementById('userMenu');
  const trigger = document.getElementById('userMenuTrigger');

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    const isOpen = userMenu.classList.toggle('open');
    trigger.setAttribute('aria-expanded', isOpen);
  });

  document.addEventListener('click', (e) => {
    if (!userMenu.contains(e.target)) {
      userMenu.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      userMenu.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
    }
  });
</script>


<script>
  (function () {
    var KEY = 'gh-theme';
    var toggle = document.getElementById('themeToggle');
    var mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    function actualTema() {
      return document.documentElement.getAttribute('data-theme');
    }

    toggle.checked = actualTema() === 'dark';

    toggle.addEventListener('change', function () {
      var nuevoTema = toggle.checked ? 'dark' : 'light';
      document.documentElement.setAttribute('data-theme', nuevoTema);
      sessionStorage.setItem(KEY, nuevoTema);
    });

    mediaQuery.addEventListener('change', function (e) {
      if (!sessionStorage.getItem(KEY)) {
        var nuevoTema = e.matches ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', nuevoTema);
        toggle.checked = nuevoTema === 'dark';
      }
    });
  })();
</script>





















































































