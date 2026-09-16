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

/**
 * Arma los grupos etapa → fotos, en el orden de las etapas. Incluye las
 * etapas sin fotos (fotos = []). Las fotos sin etapa van en un último
 * grupo con etapa null, solo si hay alguna.
 */
function agrupar_fotos_por_etapa(array $etapas, array $fotos): array
{
    $grupos = [];
    foreach ($etapas as $etapa) {
        $grupos[(int)$etapa['id']] = ['etapa' => $etapa, 'fotos' => []];
    }

    $sueltas = [];
    foreach ($fotos as $foto) {
        $etapaId = (int)($foto['etapa_id'] ?? 0);
        if (isset($grupos[$etapaId])) {
            $grupos[$etapaId]['fotos'][] = $foto;
        } else {
            $sueltas[] = $foto;
        }
    }

    $grupos = array_values($grupos);
    if ($sueltas) {
        $grupos[] = ['etapa' => null, 'fotos' => $sueltas];
    }
    return $grupos;
}

/**
 * Con name="fotos[]" PHP arma $_FILES "dado vuelta": una lista por
 * atributo (name[], tmp_name[], error[]...). Esto lo pasa a una lista
 * de archivos sueltos, con el mismo formato que un $_FILES['foto'] simple.
 */
function normalizar_archivos_subidos(array $campo): array
{
    if (!isset($campo['name'])) {
        return [];
    }
    if (!is_array($campo['name'])) {
        return [$campo];
    }

    $archivos = [];
    foreach ($campo['name'] as $i => $nombre) {
        $archivos[] = [
            'name' => (string)$nombre,
            'type' => $campo['type'][$i] ?? '',
            'tmp_name' => $campo['tmp_name'][$i] ?? '',
            'error' => $campo['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $campo['size'][$i] ?? 0,
        ];
    }
    return $archivos;
}

/** Para comparar nombres sin importar mayúsculas (mbstring puede no estar). */
function texto_comparable(string $texto): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

/** Limpia el nombre de etapa escrito a mano: sin espacios de más. */
function limpiar_nombre_etapa(string $nombre): string
{
    $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;
    $nombre = trim($nombre);
    if (strlen($nombre) > 255) {
        throw new RuntimeException('El nombre de la etapa es demasiado largo.');
    }
    return $nombre;
}

/**
 * La etapa se escribe libre. Si ya existe una con ese nombre en la obra
 * (sin importar mayúsculas) se usa esa; si no, se crea al final.
 */
function obtener_o_crear_etapa(int $obraId, string $nombre): int
{
    $clave = texto_comparable($nombre);

    $stmt = db()->prepare('SELECT id, nombre FROM etapas WHERE obra_id = ?');
    $stmt->execute([$obraId]);
    foreach ($stmt->fetchAll() as $fila) {
        if (texto_comparable($fila['nombre']) === $clave) {
            return (int)$fila['id'];
        }
    }

    $orden = db()->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM etapas WHERE obra_id = ?');
    $orden->execute([$obraId]);
    $ins = db()->prepare('INSERT INTO etapas (obra_id, nombre, orden) VALUES (?, ?, ?)');
    $ins->execute([$obraId, $nombre, (int)$orden->fetchColumn()]);
    return (int)db()->lastInsertId();
}

/**
 * Cambia nombre, fecha y descripción de una etapa. La fecha puede quedar
 * vacía. Devuelve null si salió bien, o el mensaje de error para mostrar.
 */
function actualizar_etapa(int $etapaId, int $obraId, array $datos): ?string
{
    try {
        $nombre = limpiar_nombre_etapa((string)($datos['nombre'] ?? ''));
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
    $fecha = trim((string)($datos['fecha'] ?? ''));
    $descripcion = trim((string)($datos['descripcion'] ?? '')) ?: null;

    if ($nombre === '') {
        return 'La etapa necesita un nombre.';
    }
    if ($fecha !== '') {
        $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
            return 'La fecha de la etapa no es válida.';
        }
    }

    $stmt = db()->prepare('UPDATE etapas SET nombre = ?, fecha = ?, descripcion = ? WHERE id = ? AND obra_id = ?');
    $stmt->execute([$nombre, $fecha ?: null, $descripcion, $etapaId, $obraId]);
    return null;
}

/**
 * Sube una o varias fotos de una obra. Si una falla, las demás siguen.
 * Devuelve ['subidas' => int, 'errores' => ["archivo.jpg: motivo", ...]].
 */
function subir_fotos_obra(int $obraId, array $campoArchivos, string $etapaEscrita, ?string $descripcion, int $usuarioId): array
{
    $archivos = array_values(array_filter(
        normalizar_archivos_subidos($campoArchivos),
        fn(array $a): bool => (int)$a['error'] !== UPLOAD_ERR_NO_FILE
    ));

    if (!$archivos) {
        return ['subidas' => 0, 'errores' => ['No se seleccionó ninguna foto.']];
    }

    try {
        $etapaNombre = limpiar_nombre_etapa($etapaEscrita);
    } catch (RuntimeException $e) {
        return ['subidas' => 0, 'errores' => [$e->getMessage()]];
    }

    // La etapa se crea recién con la primera foto que sale bien: si fallan
    // todas, no queda una etapa vacía creada de más.
    $etapaId = null;
    $etapaResuelta = $etapaNombre === '';

    $ins = db()->prepare('INSERT INTO fotos (obra_id, etapa_id, archivo, descripcion, subido_por) VALUES (?, ?, ?, ?, ?)');
    $subidas = 0;
    $errores = [];

    foreach ($archivos as $archivo) {
        try {
            $guardado = guardar_foto_subida($archivo, $obraId);
            if (!$etapaResuelta) {
                $etapaId = obtener_o_crear_etapa($obraId, $etapaNombre);
                $etapaResuelta = true;
            }
            $ins->execute([$obraId, $etapaId, $guardado, $descripcion, $usuarioId]);
            $subidas++;
        } catch (RuntimeException $e) {
            $errores[] = $archivo['name'] . ': ' . $e->getMessage();
        }
    }

    return ['subidas' => $subidas, 'errores' => $errores];
}
