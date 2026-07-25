<?php
$host = 'localhost';
$db = 'gesthogar_db';
$user = 'root';
$pass = '';
try {
  $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
  die('<h2>No conecta a la base de datos</h2><p>Primero abrí <a href="../instalar.php">instalar.php</a> para crear la base.</p><p>Error: ' . $e->getMessage() . '</p>');
}
?>