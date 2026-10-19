<?php
require_once '../config/session.php';
$tab = $_GET['tab'] ?? 'historial';
if ($tab === 'plan' && es_admin()) {
    header('Location: ' . BASE_URL . '/controles/plan.php');
} else {
    header('Location: ' . BASE_URL . '/controles/listar.php');
}
exit;
