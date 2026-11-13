<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * GALERIAS PUBLICAS (la tarjeta NFC)
 *
 * Un link con token que muestra SOLO las imagenes de una carpeta de la
 * obra, de lectura y nada mas. No inicia sesion, no da acceso al panel y
 * no deja ver pagos, documentos ni datos personales. Pensado para que el
 * cliente acerque el telefono a una tarjeta y le muestre los renders a
 * quien tenga enfrente.
 *
 * El link trae dos partes, como el "no me deslogues" de auth.php: un
 * nombre (selector) para encontrar la fila y un secreto (validador) del
 * que en la base solo queda el hash.
 *
 * Los archivos en si ya se sirven por URL directa desde uploads/ (el
 * nombre es aleatorio), asi que la galeria no deja a la vista nada que
 * no estuviera ya al alcance de quien tuviera el link del archivo. Lo
 * que agrega el token es que no se pueda listar la carpeta de nadie.
 */

/** Carpetas que se pueden publicar en una tarjeta. Solo cosas para mostrar. */
const GALERIA_CATEGORIAS = [
    'render' => 'Renders',
    'brochure' => 'Brochure',
];

function asegurar_tabla_galerias(): void
{
    $sql = db_driver() === 'sqlite'
        ? 'CREATE TABLE IF NOT EXISTS galerias_publicas (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                obra_id INTEGER NOT NULL,
                categoria TEXT NOT NULL,
                selector TEXT NOT NULL UNIQUE,
                validador_hash TEXT NOT NULL,
                visitas INTEGER NOT NULL DEFAULT 0,
                ultima_visita TEXT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
           )'
        : 'CREATE TABLE IF NOT EXISTS galerias_publicas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                obra_id INT NOT NULL,
                categoria VARCHAR(32) NOT NULL,
                selector VARCHAR(32) NOT NULL UNIQUE,
                validador_hash VARCHAR(64) NOT NULL,
                visitas INT NOT NULL DEFAULT 0,
                ultima_visita DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
           ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec($sql);
}

/**
 * Crea la galeria y devuelve el token entero (selector:validador). Es la
 * unica vez que existe completo: en la base queda solo el hash, asi que
 * si se pierde hay que hacer una nueva.
 */
function crear_galeria(int $obraId, string $categoria): string
{
    asegurar_tabla_galerias();

    if (!isset(GALERIA_CATEGORIAS[$categoria])) {
        $categoria = 'render';
    }

    $selector = bin2hex(random_bytes(8));
    $validador = bin2hex(random_bytes(24));

    db()->prepare('INSERT INTO galerias_publicas (obra_id, categoria, selector, validador_hash) VALUES (?, ?, ?, ?)')
        ->execute([$obraId, $categoria, $selector, hash('sha256', $validador)]);

    return $selector . ':' . $validador;
}

function galeria_por_selector(string $selector): ?array
{
    asegurar_tabla_galerias();
    $stmt = db()->prepare('SELECT * FROM galerias_publicas WHERE selector = ? LIMIT 1');
    $stmt->execute([$selector]);
    return $stmt->fetch() ?: null;
}

function galerias_de_obra(int $obraId): array
{
    asegurar_tabla_galerias();
    $stmt = db()->prepare('SELECT * FROM galerias_publicas WHERE obra_id = ? ORDER BY id DESC');
    $stmt->execute([$obraId]);
    return $stmt->fetchAll();
}

function todas_las_galerias(): array
{
    asegurar_tabla_galerias();
    return db()->query('
        SELECT g.*, o.nombre AS obra, c.nombre AS cliente
        FROM galerias_publicas g
        LEFT JOIN obras o ON o.id = g.obra_id
        LEFT JOIN usuarios c ON c.id = o.cliente_id
        ORDER BY g.id DESC
    ')->fetchAll();
}

/** Dar de baja una tarjeta: el link deja de servir en el acto. */
function revocar_galeria(int $galeriaId): void
{
    asegurar_tabla_galerias();
    db()->prepare('DELETE FROM galerias_publicas WHERE id = ?')->execute([$galeriaId]);
}

function registrar_visita_galeria(int $galeriaId): void
{
    db()->prepare('UPDATE galerias_publicas SET visitas = visitas + 1, ultima_visita = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $galeriaId]);
}
