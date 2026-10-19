<?php
require_once '../config/session.php';
if (es_profesional()) {
    header('Location: ' . BASE_URL . '/profesional/index.php'); exit;
}
header('Location: ' . BASE_URL . '/medicos/listar.php?tab=atenciones'); exit;
