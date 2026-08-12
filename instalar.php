<?php
$nombreBase = 'gesthogar';
$archivoSql = __DIR__ . '/database/gesthogar.sql';
$reemplazar = isset($_GET['reemplazar']) && $_GET['reemplazar'] === '1';

function dividirSentencias($sql)
{
    $sentencias = [];
    $actual = '';
    $enCadena = false;
    $comilla = '';
    $enComentarioLinea = false;
    $enComentarioBloque = false;
    $largo = strlen($sql);

    for ($i = 0; $i < $largo; $i++) {
        $c = $sql[$i];
        $sig = $i + 1 < $largo ? $sql[$i + 1] : '';

        if ($enComentarioLinea) {
            $actual .= $c;
            if ($c === "\n") $enComentarioLinea = false;
            continue;
        }
        if ($enComentarioBloque) {
            $actual .= $c;
            if ($c === '*' && $sig === '/') { $actual .= $sig; $i++; $enComentarioBloque = false; }
            continue;
        }
        if ($enCadena) {
            $actual .= $c;
            if ($c === '\\' && $comilla !== '`') {
                if ($sig !== '') { $actual .= $sig; $i++; }
                continue;
            }
            if ($c === $comilla) {
                if ($sig === $comilla) { $actual .= $sig; $i++; continue; }
                $enCadena = false;
            }
            continue;
        }

        if ($c === "'" || $c === '"' || $c === '`') {
            $enCadena = true; $comilla = $c; $actual .= $c; continue;
        }
        if (($c === '-' && $sig === '-') || $c === '#') {
            $enComentarioLinea = true; $actual .= $c; continue;
        }
        if ($c === '/' && $sig === '*') {
            $enComentarioBloque = true; $actual .= $c; continue;
        }
        if ($c === ';') {
            $sentencias[] = $actual; $actual = ''; continue;
        }
        $actual .= $c;
    }
    $sentencias[] = $actual;

    return array_values(array_filter(array_map('trim', $sentencias), function ($s) {
        if ($s === '') return false;
        $limpio = trim(preg_replace('/^\s*(--[^\n]*|#[^\n]*)$/m', '', $s));
        return $limpio !== '';
    }));
}

try {
    if (!is_readable($archivoSql)) {
        throw new Exception('No se encuentra el archivo ' . $archivoSql);
    }
    $sql = file_get_contents($archivoSql);

    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $existe = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.schemata WHERE schema_name = " . $pdo->quote($nombreBase)
    )->fetchColumn();

    if ($existe && !$reemplazar) {
        echo '<h1>La base ya existe</h1>';
        echo '<p>La base <b>' . htmlspecialchars($nombreBase) . '</b> ya está creada. ';
        echo 'Si querés borrarla y volver a cargarla desde cero (se pierden los datos actuales), ';
        echo 'entrá a <a href="?reemplazar=1">instalar.php?reemplazar=1</a>.</p>';
        echo '<p><a href="auth/login.php">Ir al login</a></p>';
        exit;
    }

    if ($reemplazar) {
        $pdo->exec('DROP DATABASE IF EXISTS `' . $nombreBase . '`');
    }
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $nombreBase . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE `' . $nombreBase . '`');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $sentencias = dividirSentencias($sql);
    $ejecutadas = 0;
    foreach ($sentencias as $n => $sentencia) {
        try {
            $pdo->exec($sentencia);
            $ejecutadas++;
        } catch (PDOException $e) {
            throw new Exception(
                'Falló la sentencia #' . ($n + 1) . ' de ' . count($sentencias) . ":\n\n"
                . substr($sentencia, 0, 400) . "\n\n" . $e->getMessage()
            );
        }
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    echo '<h1>Base instalada correctamente</h1>';
    echo '<p>Base: <b>' . htmlspecialchars($nombreBase) . '</b>: ';
    echo $ejecutadas . ' sentencias ejecutadas, ' . count($tablas) . ' tablas creadas.</p>';
    echo '<p><a href="auth/login.php">Ir al login</a></p>';
    echo '<p>Usuarios: admin@gesthogar.com / ana@gesthogar.com / sofia@gesthogar.com / ';
    echo 'laura@gesthogar.com / daniela@gesthogar.com / marta@gesthogar.com</p>';
    echo '<p>Contraseña para todos: <b>password</b></p>';
} catch (Exception $e) {
    echo '<h1>Error instalando</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
