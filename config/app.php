<?php
$script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '');
$parts = explode('/', trim($script,'/'));
$base = isset($parts[0]) && $parts[0] !== '' ? '/'.$parts[0] : '';
define('BASE_URL', $base);
define('APP_NAME','GestHogar - Hogar Nongo');
?>
