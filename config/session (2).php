<?php
if(session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/app.php';
function require_login(){ if(empty($_SESSION['usuario'])){ header('Location: '.BASE_URL.'/auth/login.php'); exit; } }
function rol(){ return $_SESSION['usuario']['rol'] ?? ''; }
function uid(){ return $_SESSION['usuario']['id_usuario'] ?? 0; }
function es_admin(){ return rol()==='admin'; }
function es_cuidadora(){ return rol()==='cuidadora'; }
function es_cocinera(){ return rol()==='cocinera'; }
function es_asistente(){ return rol()==='asistente'; }
function solo_admin(){ require_login(); if(!es_admin()) die('Acceso denegado. Solo administradora.'); }
function puede_cuidar(){ require_login(); if(!(es_admin()||es_cuidadora()||es_asistente())) die('Acceso denegado.'); }
function puede_cocina(){ require_login(); if(!(es_admin()||es_cocinera())) die('Acceso denegado.'); }
?>
