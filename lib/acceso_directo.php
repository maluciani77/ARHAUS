<?php
declare(strict_types=1);

/**
 * ACCESOS DIRECTOS (las tarjetas NFC)
 *
 * Un acceso directo es un link que entra a la cuenta de alguien sin
 * pedirle la contraseña. Se graba en el chip NFC de una tarjeta: el
 * cliente la apoya contra el teléfono y queda adentro de su panel.
 *
 * El link tiene dos partes separadas por dos puntos, igual que la cookie
 * de "recordame" de lib/auth.php:
 *   - el selector, que sirve para encontrar la fila en la base;
 *   - el validador, que es el secreto de verdad.
 * En la base queda SOLO el hash del validador. Así, aunque alguien se
 * lleve una copia de la base, no puede fabricar un link que funcione.
 *
 * OJO con lo que esto significa: el que tenga la tarjeta en la mano entra
 * a esa cuenta. Es la misma confianza que una llave de la casa. Por eso
 * cada acceso se puede revocar de una, queda registrado cuántas veces y
 * cuándo se usó, y se le puede poner fecha de vencimiento.
 */

require_once __DIR__ . '/db.php';

/** Cuántos bytes tiene cada mitad del token. */
const ACCESO_BYTES_SELECTOR = 6;  // 12 caracteres
const ACCESO_BYTES_VALIDADOR = 16; // 32 caracteres, 128 bits

/**
 * El link completo, listo para grabar en la tarjeta.
 * Se arma corto a propósito: un chip NTAG213 -el más barato- guarda unos
 * 130 caracteres, y con esto queda holgado.
 */
function acceso_directo_url(string $token, string $base = 'https://www.arhaus.com.ar/'): string
{
    return rtrim($base, '/') . '/acceso.php?t=' . $token;
}

/**
 * Crea un acceso nuevo y devuelve el token en claro.
 * Es la ÚNICA vez que se puede ver: después queda solo el hash. Si se
 * pierde, se revoca este y se hace otro.
 */
function crear_acceso_directo(int $usuarioId, string $nota = '', ?string $expira = null, ?int $creadoPor = null): string
{
    asegurar_tabla('accesos_directos');

    $selector = bin2hex(random_bytes(ACCESO_BYTES_SELECTOR));
    $validador = bin2hex(random_bytes(ACCESO_BYTES_VALIDADOR));

    db()->prepare(
        'INSERT INTO accesos_directos (usuario_id, selector, validador_hash, nota, expira, creado_por)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([
        $usuarioId,
        $selector,
        hash('sha256', $validador),
        // mbstring no está en todos lados (en el servidor sí, en las
        // pruebas locales no): si falta, se corta por bytes.
        function_exists('mb_substr') ? mb_substr(trim($nota), 0, 190, 'UTF-8') : substr(trim($nota), 0, 190),
        $expira,
        $creadoPor,
    ]);

    return $selector . ':' . $validador;
}

/**
 * Valida un token y devuelve la fila del usuario, o null.
 * Si es válido deja anotado el uso. No rota el secreto -a diferencia de
 * la cookie de "recordame"-: la tarjeta es un objeto físico y se usa
 * muchas veces; si rotara, el chip quedaría viejo en el primer uso.
 */
function usar_acceso_directo(string $token): ?array
{
    if ($token === '' || !str_contains($token, ':')) {
        return null;
    }
    [$selector, $validador] = explode(':', $token, 2);

    // Si viene cualquier cosa, ni siquiera se consulta la base.
    if (!ctype_xdigit($selector) || !ctype_xdigit($validador)) {
        return null;
    }

    try {
        asegurar_tabla('accesos_directos');
        $stmt = db()->prepare('SELECT * FROM accesos_directos WHERE selector = ? LIMIT 1');
        $stmt->execute([$selector]);
        $fila = $stmt->fetch();
    } catch (PDOException $ex) {
        return null;
    }

    if (!$fila) {
        return null;
    }

    // hash_equals y no ==: compara siempre en el mismo tiempo, así no se
    // puede adivinar el secreto midiendo cuánto tarda en contestar.
    if (!hash_equals((string)$fila['validador_hash'], hash('sha256', $validador))) {
        return null;
    }

    if ($fila['revocado_el'] !== null && $fila['revocado_el'] !== '') {
        return null;
    }

    if (!empty($fila['expira']) && strtotime((string)$fila['expira']) < time()) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$fila['usuario_id']]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        return null;
    }

    db()->prepare('UPDATE accesos_directos SET usos = usos + 1, ultimo_uso = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), (int)$fila['id']]);

    return $usuario;
}

/** Da de baja un acceso. El link deja de servir en el acto. */
function revocar_acceso_directo(int $id): void
{
    asegurar_tabla('accesos_directos');
    db()->prepare('UPDATE accesos_directos SET revocado_el = ? WHERE id = ? AND revocado_el IS NULL')
        ->execute([date('Y-m-d H:i:s'), $id]);
}

/** Borra la fila del todo (para limpiar los revocados viejos). */
function eliminar_acceso_directo(int $id): void
{
    asegurar_tabla('accesos_directos');
    db()->prepare('DELETE FROM accesos_directos WHERE id = ?')->execute([$id]);
}

/** Todos los accesos, con el nombre del dueño, los activos primero. */
function listar_accesos_directos(): array
{
    asegurar_tabla('accesos_directos');

    return db()->query(
        'SELECT a.*, u.nombre AS usuario_nombre, u.email AS usuario_email, u.rol AS usuario_rol
         FROM accesos_directos a
         JOIN usuarios u ON u.id = a.usuario_id
         ORDER BY (a.revocado_el IS NOT NULL), a.created_at DESC'
    )->fetchAll();
}

/** Estado legible de un acceso: para qué sirve mirar la lista. */
function estado_acceso_directo(array $acceso): string
{
    if (!empty($acceso['revocado_el'])) {
        return 'revocado';
    }
    if (!empty($acceso['expira']) && strtotime((string)$acceso['expira']) < time()) {
        return 'vencido';
    }
    return 'activo';
}
