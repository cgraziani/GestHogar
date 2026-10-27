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
?>

<header class="top">
    <a class="brand" href="<?= BASE_URL ?>/index.php">GestHogar <span>Hogar Nongo</span></a>
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
        <?php menu_link('/index.php', 'Inicio'); ?>
        <?php if (es_admin()): ?>
            <?php menu_link('/admin/usuarios.php', 'Usuarios'); ?>
            <?php menu_link('/cuidadoras/listar.php', 'Cuidadoras'); ?>
            <?php menu_link('/habitaciones/listar.php', 'Habitaciones'); ?>
        <?php endif; ?>
        <?php if (es_admin() || es_cuidadora()): ?>
            <?php menu_link('/residentes/listar.php', 'Residentes'); ?>
            <?php menu_link('/medicacion/listar.php', 'Medicación'); ?>
            <?php menu_link('/medicacion/recetas.php', 'Recetas'); ?>
            <?php menu_link('/medicacion/tareas.php', 'Tareas de cuidado'); ?>
            <?php menu_link('/visitas/listar.php', 'Visitas'); ?>
            <?php menu_link('/turnos/listar.php', 'Novedades'); ?>
        <?php endif; ?>
        <?php if (es_admin() || es_cuidadora() || es_cocinera() || es_asistente()): ?>
            <?php menu_link('/turnos/calendario.php', 'Calendario de Turnos'); ?>
        <?php endif; ?>
        <?php if (es_admin() || es_cocinera()): ?>
            <?php menu_link('/cocina/listar.php', 'Cocina'); ?>
        <?php endif; ?>
        <?php if (es_admin()): ?>
            <?php menu_link('/papelera/index.php', 'Papelera'); ?>
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
