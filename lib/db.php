<?php
declare(strict_types=1);

/**
 * Devuelve una conexión PDO reutilizable (patrón singleton simple).
 * Funciona igual con SQLite (local) o MySQL (Hostinger) según config.php.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/../config.php';

    if ($config['driver'] === 'sqlite') {
        // Crear la carpeta database/ si no existe todavía.
        $dir = dirname($config['sqlite_path']);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $pdo = new PDO('sqlite:' . $config['sqlite_path']);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['database']
        );
        $pdo = new PDO($dsn, $config['user'], $config['password']);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    return $pdo;
}

/**
 * Crea la tabla con su definición de database/schema.*.sql si todavía no
 * existe. Así, en un servidor donde la base se importó antes de que la
 * tabla existiera, alcanza con subir los archivos nuevos.
 */
function asegurar_tabla(string $tabla): void
{
    static $listas = [];
    if (isset($listas[$tabla])) {
        return;
    }

    try {
        db()->query('SELECT 1 FROM ' . $tabla . ' LIMIT 1');
    } catch (PDOException $ex) {
        $archivo = db_driver() === 'sqlite' ? 'schema.sqlite.sql' : 'schema.mysql.sql';
        $sql = file_get_contents(__DIR__ . '/../database/' . $archivo);
        foreach (array_map('trim', explode(';', $sql)) as $sentencia) {
            if (stripos($sentencia, 'CREATE TABLE IF NOT EXISTS ' . $tabla . ' ') !== false) {
                db()->exec($sentencia);
            }
        }
    }
    $listas[$tabla] = true;
}

/** Driver activo ('sqlite' | 'mysql'), útil para SQL que difiere entre motores. */
function db_driver(): string
{
    static $driver = null;
    if ($driver === null) {
        $config = require __DIR__ . '/../config.php';
        $driver = $config['driver'];
    }
    return $driver;
}
