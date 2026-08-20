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
    return $_SESSION['usuario'] ?? null;
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
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
