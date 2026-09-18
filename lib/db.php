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

/**
 * Agrega una columna a una tabla que ya existe, si todavía no la tiene.
 * Es el primo de asegurar_tabla(): en Hostinger la tabla usuarios se creó
 * antes de que existiera la foto de perfil, y así alcanza con subir los
 * archivos nuevos. $definicion es lo que va después del nombre, por
 * ejemplo 'VARCHAR(255) NULL'.
 */
function asegurar_columna(string $tabla, string $columna, string $definicion): void
{
    static $listas = [];
    $clave = $tabla . '.' . $columna;
    if (isset($listas[$clave])) {
        return;
    }

    try {
        db()->query('SELECT ' . $columna . ' FROM ' . $tabla . ' LIMIT 1');
    } catch (PDOException $ex) {
        db()->exec('ALTER TABLE ' . $tabla . ' ADD COLUMN ' . $columna . ' ' . $definicion);
    }
    $listas[$clave] = true;
}

/**
 * El rol "director" (director de obra) llegó después de crear la tabla
 * usuarios, y la columna rol no acepta valores nuevos sin tocarla: en
 * MySQL es un ENUM y en SQLite tiene un CHECK. Esto la actualiza una sola
 * vez; después no hace nada. También suma obras.director_id.
 */
function asegurar_rol_director(): void
{
    static $listo = false;
    if ($listo) {
        return;
    }

    if (db_driver() === 'sqlite') {
        $sql = (string)db()->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'usuarios'")->fetchColumn();
        if ($sql !== '' && str_contains($sql, 'CHECK') && !str_contains($sql, "'director'")) {
            // SQLite no deja cambiar un CHECK: se rearma la tabla con el mismo
            // contenido, que es el procedimiento que indica su documentación.
            $pdo = db();
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->beginTransaction();
            try {
                $pdo->exec(str_replace(
                    ["CREATE TABLE usuarios", "CREATE TABLE \"usuarios\"", "'cliente')"],
                    ["CREATE TABLE usuarios_nueva", "CREATE TABLE usuarios_nueva", "'cliente','director')"],
                    $sql
                ));
                $pdo->exec('INSERT INTO usuarios_nueva SELECT * FROM usuarios');
                $pdo->exec('DROP TABLE usuarios');
                $pdo->exec('ALTER TABLE usuarios_nueva RENAME TO usuarios');
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                $pdo->exec('PRAGMA foreign_keys = ON');
                throw $ex;
            }
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
        asegurar_columna('obras', 'director_id', 'INTEGER');
    } else {
        $columna = db()->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch();
        if ($columna && !str_contains((string)$columna['Type'], "'director'")) {
            db()->exec("ALTER TABLE usuarios MODIFY rol ENUM('admin','arquitecto','cliente','director') NOT NULL");
        }
        asegurar_columna('obras', 'director_id', 'INT UNSIGNED NULL');
    }

    $listo = true;
}

/**
 * Un valor de config.php que no es de la base, como la clave del
 * asistente. Devuelve $defecto si no está cargado.
 */
function config_valor(string $clave, $defecto = null)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config[$clave] ?? $defecto;
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
