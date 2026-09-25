<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Entrar con Google.
 *
 * El panel es privado: las cuentas las da de alta el estudio y las
 * engancha a una obra. Así que esto NO crea usuarios. Solo deja entrar
 * sin contraseña a alguien cuyo correo el estudio ya cargó; si el correo
 * de Google no está en la base, se lo manda a pedirle el acceso al
 * estudio.
 */

/**
 * El identificador de la credencial de Google del sitio. NO es un
 * secreto: viaja en el HTML de la página de login, cualquiera que mire
 * el código lo ve. El que sí es secreto (el "client secret") no hace
 * falta para esta forma de entrar, así que no está en ningún lado.
 *
 * Si alguna vez se rehace la credencial, se cambia acá Y en login.html.
 */
const GOOGLE_CLIENT_ID = '218470589355-9f5d0u0d492m26mtqftorf07pd2i2sf2.apps.googleusercontent.com';

/** Google acepta las dos formas en el campo "iss" de sus tokens. */
const GOOGLE_EMISORES = ['accounts.google.com', 'https://accounts.google.com'];

/**
 * Le pregunta a Google si el token es suyo y devuelve lo que dice de la
 * persona. Se le manda el token tal cual vino del navegador y contesta
 * Google: es él quien valida la firma, no nosotros.
 *
 * Devuelve el arreglo de datos, o null si no se pudo consultar.
 */
function google_consultar_token(string $token): ?array
{
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($token);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $cuerpo = curl_exec($ch);
        $estado = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($cuerpo === false || $estado !== 200) {
            return null;
        }
    } else {
        $contexto = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $cuerpo = @file_get_contents($url, false, $contexto);
        if ($cuerpo === false) {
            return null;
        }
    }

    $datos = json_decode((string)$cuerpo, true);
    return is_array($datos) ? $datos : null;
}

/**
 * Revisa que los datos que devolvió Google sean de ESTE sitio y estén
 * en hora. Sin esto, alguien podría traer un token legítimo de Google
 * pero emitido para otra aplicación, y entrar con él.
 *
 * Devuelve el correo (en minúsculas) y el identificador de la cuenta, o
 * un texto de error.
 */
function google_datos_validos(array $datos): array
{
    if (($datos['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
        return ['error' => 'Ese acceso de Google no es de este sitio.'];
    }
    if (!in_array((string)($datos['iss'] ?? ''), GOOGLE_EMISORES, true)) {
        return ['error' => 'No pudimos confirmar el acceso con Google.'];
    }
    if ((int)($datos['exp'] ?? 0) <= time()) {
        return ['error' => 'El acceso de Google venció. Probá de nuevo.'];
    }
    // Google marca si la casilla está confirmada; manda "true" como texto.
    $confirmado = $datos['email_verified'] ?? 'false';
    if ($confirmado !== true && $confirmado !== 'true') {
        return ['error' => 'Esa cuenta de Google no tiene el correo confirmado.'];
    }

    $email = strtolower(trim((string)($datos['email'] ?? '')));
    if ($email === '') {
        return ['error' => 'Google no nos dio un correo para esa cuenta.'];
    }

    return ['email' => $email, 'google_id' => (string)($datos['sub'] ?? '')];
}

/** La columna donde queda enganchada la cuenta de Google de cada usuario. */
function google_asegurar_columna(): void
{
    asegurar_columna('usuarios', 'google_id', db_driver() === 'sqlite' ? 'TEXT' : 'VARCHAR(64) NULL');
}

/**
 * Busca al usuario dueño de esa cuenta de Google. Primero por el
 * identificador de Google (no cambia nunca) y si no, por el correo, que
 * es como se engancha la primera vez.
 */
function google_buscar_usuario(string $email, string $googleId): ?array
{
    google_asegurar_columna();

    if ($googleId !== '') {
        $stmt = db()->prepare('SELECT * FROM usuarios WHERE google_id = ? LIMIT 1');
        $stmt->execute([$googleId]);
        $fila = $stmt->fetch();
        if ($fila) {
            return $fila;
        }
    }

    // Se baja a minúsculas de este lado también: así la función no
    // depende de que quien la llame se haya acordado de hacerlo.
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE LOWER(email) = ? LIMIT 1');
    $stmt->execute([strtolower(trim($email))]);
    $fila = $stmt->fetch();

    return $fila ?: null;
}

/** Deja anotada la cuenta de Google en el usuario, la primera vez. */
function google_enganchar(int $usuarioId, string $googleId): void
{
    if ($googleId === '') {
        return;
    }
    google_asegurar_columna();
    db()->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?')->execute([$googleId, $usuarioId]);
}
