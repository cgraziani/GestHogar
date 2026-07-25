<?php
require_once __DIR__ . '/app.php';
?>
<!doctype html>
<html lang="es">

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

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.default.min.css">

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= ASSETS_VER ?>">
</head>

<body>