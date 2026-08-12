<?php
$script = str_replace('\\','/', $_SERVER['SCRIPT_NAME'] ?? '');
$parts = explode('/', trim($script,'/'));
$base = isset($parts[0]) && $parts[0] !== '' ? '/'.$parts[0] : '';
define('BASE_URL', $base);
define('APP_NAME','GestHogar - Hogar Nongo');

// Versión de los archivos estáticos. Subila cuando cambies style.css para
// que el navegador no siga usando la copia vieja. Antes el número estaba
// escrito a mano en cada página y las de login quedaban desactualizadas.
define('ASSETS_VER', '17');

// Escapa texto antes de imprimirlo en el HTML. Tolera NULL (en PHP 8.2
// pasarle NULL a htmlspecialchars tira aviso de obsoleto).
// Estaba definida solo en turnos/calendario.php; ahora sirve para todo.
function h($s) {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
