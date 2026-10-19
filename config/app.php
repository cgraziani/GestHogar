<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

$script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '');
$parts = explode('/', trim($script,'/'));
$base = isset($parts[0]) && $parts[0] !== '' ? '/'.$parts[0] : '';
define('BASE_URL', $base);
define('APP_NAME','GestHogar - Hogar Nongo');

$protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host      = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL_FULL', $protocolo . $host . $base);

define('ASSETS_VER', '17');

function h($s) {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}
?>