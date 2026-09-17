<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Mensajes entre el cliente y el estudio, uno abajo del otro como un
 * chat. Van por obra: cada cliente habla del proyecto que le toca, y
 * cualquiera del estudio (admin o el arquitecto asignado) le responde.
 */

const MENSAJE_LARGO_MAXIMO = 2000;

function mensajes_de_obra(int $obraId): array
{
    asegurar_tabla('mensajes');

    $stmt = db()->prepare(
        'SELECT m.*, u.nombre AS autor_nombre, u.rol AS autor_rol
         FROM mensajes m
         LEFT JOIN usuarios u ON u.id = m.autor_id
         WHERE m.obra_id = ?
         ORDER BY m.created_at ASC, m.id ASC'
    );
    $stmt->execute([$obraId]);
    return $stmt->fetchAll();
}

/** Devuelve null si se guardó, o el mensaje de error. */
function agregar_mensaje(int $obraId, array $datos, int $usuarioId): ?string
{
    $texto = trim((string)($datos['texto'] ?? ''));

    if ($texto === '') {
        return 'Escribí un mensaje antes de enviarlo.';
    }
    if (largo_texto($texto) > MENSAJE_LARGO_MAXIMO) {
        return 'El mensaje es muy largo (máximo ' . MENSAJE_LARGO_MAXIMO . ' caracteres).';
    }

    asegurar_tabla('mensajes');
    $stmt = db()->prepare('INSERT INTO mensajes (obra_id, autor_id, texto) VALUES (?, ?, ?)');
    $stmt->execute([$obraId, $usuarioId, $texto]);

    return null;
}

function eliminar_mensaje(int $mensajeId, int $obraId): void
{
    asegurar_tabla('mensajes');

    $del = db()->prepare('DELETE FROM mensajes WHERE id = ? AND obra_id = ?');
    $del->execute([$mensajeId, $obraId]);
}

/** Cuántos mensajes del cliente llegaron después de la última respuesta del estudio. */
function mensajes_sin_responder(int $obraId): int
{
    $mensajes = mensajes_de_obra($obraId);
    $pendientes = 0;
    foreach ($mensajes as $mensaje) {
        if (($mensaje['autor_rol'] ?? '') === 'cliente') {
            $pendientes++;
        } else {
            $pendientes = 0;
        }
    }
    return $pendientes;
}

/** Fecha y hora cortitas para el chat: "17/09 · 08:12". */
function fecha_mensaje(?string $creado): string
{
    if (!$creado) {
        return '';
    }
    $marca = strtotime($creado);
    return $marca ? date('d/m · H:i', $marca) : '';
}
