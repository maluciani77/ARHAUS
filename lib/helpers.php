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
