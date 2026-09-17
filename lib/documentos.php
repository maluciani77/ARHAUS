<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Archivos de la obra que el cliente puede ver y descargar, agrupados en
 * solapas: el Gantt de las etapas, los renders, los PDF del proyecto, lo
 * presentado en la municipalidad y un cajón de "varios".
 *
 * Se guardan en uploads/obras/{obra_id}/docs/ con un nombre inventado, y
 * el nombre lindo queda en la base. El .htaccess de uploads/ ya impide
 * que se ejecute PHP ahí adentro.
 */

const CATEGORIAS_DOCUMENTO = [
    'etapas' => 'Etapa de obra',
    'renders' => 'Renders',
    'proyecto' => 'Proyecto',
    'municipal' => 'Municipal',
    'varios' => 'Varios',
];

const DOCUMENTO_TAMANO_MAXIMO = 20 * 1024 * 1024; // 20 MB

/**
 * Extensión => [descripción, firmas posibles del contenido]. Una firma
 * vacía significa que el formato no tiene una cabecera fija y se valida
 * de otra forma (las imágenes, con getimagesize()).
 */
const DOCUMENTO_FORMATOS = [
    'pdf' => ['PDF', ["%PDF-"]],
    'xlsx' => ['Excel', ["PK\x03\x04", "PK\x05\x06"]],
    'xls' => ['Excel', ["\xD0\xCF\x11\xE0"]],
    'csv' => ['CSV', []],
    'jpg' => ['Imagen', []],
    'jpeg' => ['Imagen', []],
    'png' => ['Imagen', []],
    'webp' => ['Imagen', []],
];

function es_categoria_documento(string $categoria): bool
{
    return isset(CATEGORIAS_DOCUMENTO[$categoria]);
}

function directorio_documentos(int $obraId): string
{
    return __DIR__ . '/../uploads/obras/' . $obraId . '/docs';
}

function ruta_publica_documento(int $obraId, string $archivo): string
{
    return 'uploads/obras/' . $obraId . '/docs/' . $archivo;
}

/** "2,4 MB", "870 KB". */
function tamano_legible(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }
    return max(1, (int)round($bytes / 1024)) . ' KB';
}

/** Etiqueta corta del tipo de archivo, para mostrar al lado del nombre. */
function tipo_documento(string $archivo): string
{
    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    return DOCUMENTO_FORMATOS[$extension][0] ?? strtoupper($extension);
}

function documentos_de_obra(int $obraId, ?string $categoria = null): array
{
    asegurar_tabla('documentos');

    if ($categoria !== null) {
        $stmt = db()->prepare('SELECT * FROM documentos WHERE obra_id = ? AND categoria = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$obraId, $categoria]);
    } else {
        $stmt = db()->prepare('SELECT * FROM documentos WHERE obra_id = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$obraId]);
    }
    return $stmt->fetchAll();
}

/** Cuántos archivos hay en cada categoría: ['renders' => 3, ...]. */
function conteo_documentos(int $obraId): array
{
    $conteo = array_fill_keys(array_keys(CATEGORIAS_DOCUMENTO), 0);
    foreach (documentos_de_obra($obraId) as $documento) {
        $categoria = (string)$documento['categoria'];
        if (isset($conteo[$categoria])) {
            $conteo[$categoria]++;
        }
    }
    return $conteo;
}

/**
 * Valida el archivo subido de verdad: extensión permitida y, cuando el
 * formato lo permite, que el contenido sea lo que dice ser. Devuelve la
 * extensión en minúsculas o lanza RuntimeException.
 */
function validar_documento_subido(array $archivo): string
{
    if (!isset($archivo['error']) || is_array($archivo['error'])) {
        throw new RuntimeException('Subida de archivo inválida.');
    }

    switch ($archivo['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No se seleccionó ningún archivo.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('El archivo es demasiado pesado.');
        default:
            throw new RuntimeException('Error al subir el archivo (código ' . $archivo['error'] . ').');
    }

    if ($archivo['size'] > DOCUMENTO_TAMANO_MAXIMO) {
        throw new RuntimeException('El archivo supera el límite de ' . (DOCUMENTO_TAMANO_MAXIMO / 1048576) . ' MB.');
    }

    $extension = strtolower(pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));
    if (!isset(DOCUMENTO_FORMATOS[$extension])) {
        throw new RuntimeException('Formato no permitido. Se aceptan PDF, Excel, CSV y imágenes (JPG, PNG, WEBP).');
    }

    [, $firmas] = DOCUMENTO_FORMATOS[$extension];

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        if (@getimagesize($archivo['tmp_name']) === false) {
            throw new RuntimeException('El archivo no es una imagen válida.');
        }
        return $extension;
    }

    if ($firmas) {
        $manejador = @fopen($archivo['tmp_name'], 'rb');
        if ($manejador === false) {
            throw new RuntimeException('No se pudo leer el archivo subido.');
        }
        $cabecera = (string)fread($manejador, 8);
        fclose($manejador);

        foreach ($firmas as $firma) {
            if (str_starts_with($cabecera, $firma)) {
                return $extension;
            }
        }
        throw new RuntimeException('El archivo no parece un ' . DOCUMENTO_FORMATOS[$extension][0] . ' de verdad.');
    }

    return $extension;
}

/**
 * Guarda un archivo en una categoría. Devuelve null si salió bien o el
 * mensaje de error listo para mostrar.
 */
function agregar_documento(int $obraId, string $categoria, array $datos, array $archivo, int $usuarioId): ?string
{
    if (!es_categoria_documento($categoria)) {
        return 'Esa sección no existe.';
    }

    try {
        $extension = validar_documento_subido($archivo);
    } catch (RuntimeException $ex) {
        return $ex->getMessage();
    }

    $nombreOriginal = basename((string)$archivo['name']);
    $titulo = trim((string)($datos['titulo'] ?? '')) ?: pathinfo($nombreOriginal, PATHINFO_FILENAME);

    $directorio = directorio_documentos($obraId);
    if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
        return 'No se pudo crear la carpeta de archivos de la obra.';
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($archivo['tmp_name'], $directorio . '/' . $nombreArchivo)) {
        return 'No se pudo guardar el archivo en el servidor.';
    }

    asegurar_tabla('documentos');
    $stmt = db()->prepare(
        'INSERT INTO documentos (obra_id, categoria, titulo, archivo, nombre_original, tamano, subido_por) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$obraId, $categoria, recortar_texto($titulo, 190), $nombreArchivo, recortar_texto($nombreOriginal, 255), (int)$archivo['size'], $usuarioId]);

    return null;
}

function eliminar_documento(int $documentoId, int $obraId): void
{
    asegurar_tabla('documentos');

    $stmt = db()->prepare('SELECT * FROM documentos WHERE id = ? AND obra_id = ?');
    $stmt->execute([$documentoId, $obraId]);
    $documento = $stmt->fetch();
    if (!$documento) {
        return;
    }

    $ruta = directorio_documentos($obraId) . '/' . $documento['archivo'];
    if (is_file($ruta)) {
        unlink($ruta);
    }

    $del = db()->prepare('DELETE FROM documentos WHERE id = ? AND obra_id = ?');
    $del->execute([$documentoId, $obraId]);
}
