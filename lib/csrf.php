<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

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
        // Si lo enviado supera post_max_size, PHP descarta el POST ENTERO
        // (token incluido) y parecería un token inválido. Mejor avisar bien.
        $excedido = (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && empty($_POST) && empty($_FILES);
        $mensaje = $excedido
            ? 'Lo que intentaste subir pesa demasiado junto. Probá con menos fotos a la vez.'
            : 'Token de seguridad inválido. Volvé atrás y probá de nuevo.';

        if (es_pedido_fetch()) {
            responder_json(['subidas' => 0, 'errores' => [$mensaje]], 400);
        }
        http_response_code(400);
        echo $mensaje;
        exit;
    }
}
