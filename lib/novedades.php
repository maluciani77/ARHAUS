<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/uploads.php';

/**
 * Dirección de obra: el día a día que deja el director (o el admin) y que
 * el cliente solo lee. Cada novedad puede ser un comentario, una foto, o
 * una foto con su comentario.
 *
 * Las fotos van a la misma carpeta que las de las etapas
 * (uploads/obras/{obra_id}/), con guardar_foto_subida().
 */

function novedades_de_obra(int $obraId, ?int $limite = null): array
{
    asegurar_tabla('novedades');

    $sql = 'SELECT n.*, u.nombre AS autor_nombre, u.rol AS autor_rol
            FROM novedades n
            LEFT JOIN usuarios u ON u.id = n.autor_id
            WHERE n.obra_id = ?
            ORDER BY n.created_at DESC, n.id DESC';
    if ($limite !== null) {
        $sql .= ' LIMIT ' . max(1, $limite);
    }

    $stmt = db()->prepare($sql);
    $stmt->execute([$obraId]);
    return $stmt->fetchAll();
}

/**
 * Agrega una novedad. $archivo es $_FILES['foto'] y puede venir vacío.
 * Devuelve null si salió bien, o el mensaje de error.
 */
function agregar_novedad(int $obraId, array $datos, array $archivo, int $usuarioId): ?string
{
    $texto = trim((string)($datos['texto'] ?? ''));
    $hayArchivo = isset($archivo['error']) && $archivo['error'] !== UPLOAD_ERR_NO_FILE;

    if ($texto === '' && !$hayArchivo) {
        return 'Escribí un comentario o elegí una foto.';
    }

    $nombreArchivo = null;
    if ($hayArchivo) {
        try {
            $nombreArchivo = guardar_foto_subida($archivo, $obraId);
        } catch (RuntimeException $ex) {
            return $ex->getMessage();
        }
    }

    asegurar_tabla('novedades');
    $stmt = db()->prepare('INSERT INTO novedades (obra_id, texto, archivo, autor_id) VALUES (?, ?, ?, ?)');
    $stmt->execute([$obraId, $texto !== '' ? $texto : null, $nombreArchivo, $usuarioId]);

    return null;
}

function eliminar_novedad(int $novedadId, int $obraId): void
{
    asegurar_tabla('novedades');

    $stmt = db()->prepare('SELECT * FROM novedades WHERE id = ? AND obra_id = ?');
    $stmt->execute([$novedadId, $obraId]);
    $novedad = $stmt->fetch();
    if (!$novedad) {
        return;
    }

    if ($novedad['archivo']) {
        $ruta = __DIR__ . '/../uploads/obras/' . $obraId . '/' . $novedad['archivo'];
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }

    $del = db()->prepare('DELETE FROM novedades WHERE id = ? AND obra_id = ?');
    $del->execute([$novedadId, $obraId]);
}
