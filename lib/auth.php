<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function iniciar_sesion_segura(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ]);
    }
}

/** Datos del usuario logueado (id, nombre, email, rol) o null si no hay sesión. */
function usuario_actual(): ?array
{
    iniciar_sesion_segura();
    if (isset($_SESSION['usuario'])) {
        return $_SESSION['usuario'];
    }

    // No hay sesión, pero puede haber quedado un "no me deslogueés" de
    // la última vez.
    return recordar_si_corresponde();
}

/* ========================================
   MANTENER LA SESIÓN INICIADA
   La sesión de PHP se borra sola al rato y al cerrar el navegador. Para
   que el acceso dure de verdad se guarda una cookie aparte con dos
   partes: un nombre (selector) que sirve para encontrar la fila, y un
   secreto (validador) del que en la base SOLO queda el hash.
   Así, aunque alguien se lleve la base, no puede fabricar la cookie.
   ======================================== */

const RECORDAR_COOKIE = 'arhaus_recordar';
const RECORDAR_DIAS = 30;

function recordar_parametros_cookie(int $vence): array
{
    return [
        'expires' => $vence,
        'path' => '/',
        // En el servidor va por HTTPS; en las pruebas locales, no.
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

/**
 * Deja el acceso guardado en este dispositivo por RECORDAR_DIAS días.
 * Devuelve el valor de la cookie, que es lo único que permite probar
 * esto sin un navegador de por medio (la cookie es httponly).
 */
function recordar_este_dispositivo(int $usuarioId): string
{
    asegurar_tabla('recordatorios');

    $selector = bin2hex(random_bytes(8));
    $validador = bin2hex(random_bytes(32));
    $vence = time() + RECORDAR_DIAS * 86400;

    db()->prepare('INSERT INTO recordatorios (usuario_id, selector, validador_hash, expira) VALUES (?, ?, ?, ?)')
        ->execute([$usuarioId, $selector, hash('sha256', $validador), date('Y-m-d H:i:s', $vence)]);

    $valor = $selector . ':' . $validador;
    if (!headers_sent()) {
        setcookie(RECORDAR_COOKIE, $valor, recordar_parametros_cookie($vence));
    }
    // También en la variable del pedido actual: setcookie recién llega al
    // navegador en el próximo, y lo que siga corriendo acá tiene que ver
    // el recordatorio nuevo y no el que se acaba de dar de baja.
    $_COOKIE[RECORDAR_COOKIE] = $valor;

    return $valor;
}

/** Borra el recordatorio de ESTE dispositivo (los otros siguen). */
function olvidar_este_dispositivo(): void
{
    $cookie = (string)($_COOKIE[RECORDAR_COOKIE] ?? '');
    if ($cookie !== '' && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        try {
            asegurar_tabla('recordatorios');
            db()->prepare('DELETE FROM recordatorios WHERE selector = ?')->execute([$selector]);
        } catch (PDOException $ex) {
            // Si la tabla todavía no existe no hay nada que borrar.
        }
    }
    if (!headers_sent()) {
        setcookie(RECORDAR_COOKIE, '', recordar_parametros_cookie(time() - 42000));
    }
    unset($_COOKIE[RECORDAR_COOKIE]);
}

/**
 * Si hay cookie válida, vuelve a iniciar la sesión y devuelve al usuario.
 * El recordatorio usado se cambia por uno nuevo: si alguien llegara a
 * robar la cookie, deja de servirle en cuanto la usa el dueño.
 */
function recordar_si_corresponde(): ?array
{
    $cookie = (string)($_COOKIE[RECORDAR_COOKIE] ?? '');
    if ($cookie === '' || !str_contains($cookie, ':')) {
        return null;
    }
    [$selector, $validador] = explode(':', $cookie, 2);

    try {
        asegurar_tabla('recordatorios');
        $stmt = db()->prepare('SELECT * FROM recordatorios WHERE selector = ? LIMIT 1');
        $stmt->execute([$selector]);
        $fila = $stmt->fetch();
    } catch (PDOException $ex) {
        return null;
    }

    if (!$fila) {
        olvidar_este_dispositivo();
        return null;
    }

    if (strtotime((string)$fila['expira']) < time()) {
        db()->prepare('DELETE FROM recordatorios WHERE id = ?')->execute([(int)$fila['id']]);
        olvidar_este_dispositivo();
        return null;
    }

    // hash_equals y no ==: compara siempre en el mismo tiempo, así no se
    // puede adivinar el secreto midiendo cuánto tarda en contestar.
    if (!hash_equals((string)$fila['validador_hash'], hash('sha256', $validador))) {
        olvidar_este_dispositivo();
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$fila['usuario_id']]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        olvidar_este_dispositivo();
        return null;
    }

    db()->prepare('DELETE FROM recordatorios WHERE id = ?')->execute([(int)$fila['id']]);
    iniciar_login($usuario);
    recordar_este_dispositivo((int)$usuario['id']);

    return $_SESSION['usuario'];
}

/**
 * Corta la ejecución y manda al login si no hay sesión activa.
 * $raiz es la ruta relativa hasta la raíz del sitio desde donde se llama
 * (por ejemplo '../../' desde /panel/admin/index.php, o '' desde /login.php).
 */
function requerir_login(string $raiz = ''): array
{
    $usuario = usuario_actual();
    if (!$usuario) {
        header('Location: ' . $raiz . 'login.html');
        exit;
    }
    return $usuario;
}

/** Como requerir_login(), pero además exige que el rol sea uno de los indicados. */
function requerir_rol(string $raiz, string ...$roles): array
{
    $usuario = requerir_login($raiz);
    if (!in_array($usuario['rol'], $roles, true)) {
        http_response_code(403);
        echo '<p style="font-family:sans-serif;padding:40px">No tenés permiso para ver esta página.</p>';
        exit;
    }
    return $usuario;
}

function iniciar_login(array $usuarioFila): void
{
    iniciar_sesion_segura();
    session_regenerate_id(true);
    $_SESSION['usuario'] = [
        'id' => (int)$usuarioFila['id'],
        'nombre' => $usuarioFila['nombre'],
        'email' => $usuarioFila['email'],
        'rol' => $usuarioFila['rol'],
    ];
}

function cerrar_sesion(): void
{
    iniciar_sesion_segura();
    olvidar_este_dispositivo();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
