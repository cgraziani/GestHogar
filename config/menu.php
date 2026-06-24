<?php require_once __DIR__.'/session.php'; require_login(); include __DIR__.'/header.php'; ?>
<header class="top"><div class="brand">GestHogar <span>Hogar Nongo</span></div><div><?=htmlspecialchars($_SESSION['usuario']['nombre'])?> (<?=rol()?>) | <a href="<?=BASE_URL?>/auth/logout.php">Salir</a></div></header>
<div class="layout"><aside class="side">
<a href="<?=BASE_URL?>/index.php">Inicio</a>
<?php if(es_admin()):?>
<a href="<?=BASE_URL?>/admin/usuarios.php">Usuarios</a>
<a href="<?=BASE_URL?>/cuidadoras/listar.php">Cuidadoras</a>
<a href="<?=BASE_URL?>/habitaciones/listar.php">Habitaciones</a>
<?php endif;?>
<?php if(es_admin()||es_cuidadora()):?>
<a href="<?=BASE_URL?>/residentes/listar.php">Residentes</a>
<a href="<?=BASE_URL?>/medicacion/listar.php">Medicación / Recetas / Tareas</a>
<a href="<?=BASE_URL?>/turnos/listar.php">Novedades</a>
<?php endif;?>
<?php if(es_admin()||es_cuidadora()||es_cocinera()||es_asistente()):?>
<a href="<?=BASE_URL?>/turnos/calendario.php">Calendario de Turnos</a>
<?php endif;?>
<?php if(es_admin()||es_cocinera()):?>
<a href="<?=BASE_URL?>/cocina/listar.php">Cocina</a>
<?php endif;?>
<?php if(es_admin()):?><a href="<?=BASE_URL?>/papelera/index.php">Papelera</a><?php endif;?>
</aside><main class="content">
