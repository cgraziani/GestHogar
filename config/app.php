<?php
$script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '');
$parts = explode('/', trim($script,'/'));
$base = isset($parts[0]) && $parts[0] !== '' ? '/'.$parts[0] : '';
define('BASE_URL', $base);
define('APP_NAME','GestHogar - Hogar Nongo');

define('ASSETS_VER', '13');

function h($s) {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
