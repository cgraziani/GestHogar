<?php
// ============================================================
// CONFIGURACIÓN BASE
// Carga  APP_NAME, BASE_URL, etc.
// definidas en config/app.php
// ============================================================
require_once __DIR__ . '/app.php';
?>
<!doctype html>
<html lang="es">

<!-- ============================================================
    (modo claro / oscuro)
    
     ============================================================ -->
<script>
  (function () {
    var override = sessionStorage.getItem('gh-theme');
    var theme = override || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
  })();
</script>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?></title>

    <!-- ============================================================
         CSS Estilos del sistema
        
         1. Icons: 
         2. DataTables
         3. style.css
         ============================================================ -->

    <!-- Íconos -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <!-- DataTables CSS: -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

    <!-- CSS propio del sistema -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=11">
</head>

<body>