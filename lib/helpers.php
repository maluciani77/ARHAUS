<?php
declare(strict_types=1);

/** Escapa texto para imprimir seguro dentro de HTML. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/** Redirige (relativo al script actual) y corta la ejecución. */
function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Etiqueta legible para cada rol. */
function nombre_rol(string $rol): string
{
    return match ($rol) {
        'admin' => 'Administrador',
        'arquitecto' => 'Arquitecto',
        'cliente' => 'Cliente',
        default => $rol,
    };
}

/** Etiqueta legible para el estado de una obra. */
function nombre_estado(string $estado): string
{
    return match ($estado) {
        'en_curso' => 'En curso',
        'finalizada' => 'Finalizada',
        'pausada' => 'Pausada',
        default => $estado,
    };
}

function formatear_fecha(?string $fecha): string
{
    if (!$fecha) {
        return '';
    }
    $timestamp = strtotime($fecha);
    return $timestamp ? date('d/m/Y', $timestamp) : $fecha;
}

/** true si el pedido vino del script de subida (espera JSON, no HTML). */
function es_pedido_fetch(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

/**
 * Responde JSON y corta la ejecución.
 *
 * OJO: el tipo de retorno es "void" y no "never" a propósito. "never"
 * existe recién desde PHP 8.1, y si el servidor corre 8.0 el archivo
 * ni siquiera compila: se cae con error 500 TODA página que lo incluya
 * (login, panel, setup...). Con "void" anda igual y es compatible.
 */
function responder_json(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}
