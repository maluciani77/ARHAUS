<?php
declare(strict_types=1);

const EXTENSIONES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'webp'];
const TAMANO_MAXIMO_BYTES = 8 * 1024 * 1024; // 8 MB

/**
 * Valida y guarda una foto subida ($_FILES['campo']) dentro de
 * uploads/obras/{obra_id}/. Devuelve el nombre de archivo generado
 * (para guardar en la base) o lanza RuntimeException con un mensaje
 * en español listo para mostrarle al usuario.
 */
function guardar_foto_subida(array $archivo, int $obraId): string
{
    if (!isset($archivo['error']) || is_array($archivo['error'])) {
        throw new RuntimeException('Subida de archivo inválida.');
    }

    switch ($archivo['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No se seleccionó ninguna foto.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('La foto es demasiado pesada.');
        default:
            throw new RuntimeException('Error al subir la foto (código ' . $archivo['error'] . ').');
    }

    if ($archivo['size'] > TAMANO_MAXIMO_BYTES) {
        throw new RuntimeException('La foto supera el límite de 8 MB.');
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, EXTENSIONES_PERMITIDAS, true)) {
        throw new RuntimeException('Formato no permitido. Usá JPG, PNG o WEBP.');
    }

    // Verificar que el contenido sea realmente una imagen (no solo la extensión).
    $info = @getimagesize($archivo['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('El archivo no es una imagen válida.');
    }

    $dirObra = __DIR__ . '/../uploads/obras/' . $obraId;
    if (!is_dir($dirObra)) {
        mkdir($dirObra, 0777, true);
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    $rutaDestino = $dirObra . '/' . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        throw new RuntimeException('No se pudo guardar la foto en el servidor.');
    }

    return $nombreArchivo;
}

function ruta_publica_foto(int $obraId, string $archivo): string
{
    return 'uploads/obras/' . $obraId . '/' . $archivo;
}
