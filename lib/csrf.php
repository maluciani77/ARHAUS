<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/** Genera (o reutiliza) un token CSRF para la sesión actual. */
function csrf_token(): string
{
    iniciar_sesion_segura();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Campo oculto listo para pegar dentro de un <form>. */
function campo_csrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/** Corta la ejecución con 400 si el token del POST no coincide. */
function verificar_csrf(): void
{
    iniciar_sesion_segura();
    $enviado = $_POST['csrf_token'] ?? '';
    $esperado = $_SESSION['csrf_token'] ?? '';
    if ($enviado === '' || $esperado === '' || !hash_equals($esperado, $enviado)) {
        http_response_code(400);
        echo 'Token de seguridad inválido. Volvé atrás y probá de nuevo.';
        exit;
    }
}
