<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/uploads.php';
require_once __DIR__ . '/documentos.php';

/**
 * Las herramientas del asistente: abrir un archivo de la obra (PDF,
 * imagen, Excel o CSV) y ver las fotos de avance de una etapa. El
 * asistente las usa solo cuando la pregunta lo necesita.
 *
 * Todo sale de la base y de la carpeta de la obra del cliente: el modelo
 * elige un número de archivo o un nombre de etapa, nunca una ruta.
 */

const ASISTENTE_PDF_TAMANO_MAXIMO = 15 * 1024 * 1024;   // en base64 crece un tercio; la API acepta 32 MB por pedido
const ASISTENTE_IMAGEN_LADO_MAXIMO = 1400;              // px: más grande no suma detalle y cuesta más
const ASISTENTE_FOTOS_POR_ETAPA = 8;
const ASISTENTE_EXCEL_FILAS = 200;
const ASISTENTE_EXCEL_COLUMNAS = 60;

/** Las definiciones que se le pasan a la API. */
function herramientas_asistente(): array
{
    return [
        [
            'name' => 'abrir_archivo',
            'description' => 'Abre un archivo de la obra para leer su contenido: PDF (planos, memorias, contratos), imágenes (renders) o planillas (el Gantt en Excel o CSV). '
                . 'Usala cuando la respuesta dependa de lo que dice o muestra un archivo, no solo de su título. '
                . 'El número de cada archivo figura en el panel como [archivo N].',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'archivo' => ['type' => 'integer', 'description' => 'El número N de [archivo N].'],
                ],
                'required' => ['archivo'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ],
        [
            'name' => 'ver_fotos_de_etapa',
            'description' => 'Muestra hasta ' . ASISTENTE_FOTOS_POR_ETAPA . ' fotos de avance de una etapa de la obra, las más recientes. '
                . 'Usala cuando pregunten cómo se ve o cómo va algo de la obra y las fotos ayuden a responder.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'etapa' => ['type' => 'string', 'description' => 'El nombre exacto de la etapa, como figura en la lista de ETAPAS del panel.'],
                ],
                'required' => ['etapa'],
                'additionalProperties' => false,
            ],
            'strict' => true,
        ],
    ];
}

/**
 * Ejecuta una herramienta. Devuelve [contenido, esError]: el contenido es
 * texto o una lista de bloques (texto, imagen, documento) para el
 * tool_result.
 */
function ejecutar_herramienta_asistente(string $nombre, array $entrada, array $obra): array
{
    try {
        return match ($nombre) {
            'abrir_archivo' => [abrir_archivo_para_asistente((int)($entrada['archivo'] ?? 0), (int)$obra['id']), false],
            'ver_fotos_de_etapa' => [fotos_de_etapa_para_asistente((string)($entrada['etapa'] ?? ''), (int)$obra['id']), false],
            default => ['No existe la herramienta ' . $nombre . '.', true],
        };
    } catch (RuntimeException $ex) {
        return [$ex->getMessage(), true];
    }
}

function abrir_archivo_para_asistente(int $documentoId, int $obraId): array
{
    $stmt = db()->prepare('SELECT * FROM documentos WHERE id = ? AND obra_id = ?');
    $stmt->execute([$documentoId, $obraId]);
    $documento = $stmt->fetch();
    if (!$documento) {
        throw new RuntimeException('No hay ningún archivo ' . $documentoId . ' en esta obra. Revisá los números de la lista de ARCHIVOS.');
    }

    $ruta = directorio_documentos($obraId) . '/' . basename((string)$documento['archivo']);
    if (!is_file($ruta)) {
        throw new RuntimeException('El archivo "' . $documento['titulo'] . '" figura en el panel pero no está en el servidor.');
    }

    $solapa = CATEGORIAS_DOCUMENTO[(string)$documento['categoria']] ?? (string)$documento['categoria'];
    $encabezado = ['type' => 'text', 'text' => 'Archivo ' . $documentoId . ': "' . $documento['titulo'] . '", en la solapa ' . $solapa . '.'];
    $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

    if ($extension === 'pdf') {
        if (filesize($ruta) > ASISTENTE_PDF_TAMANO_MAXIMO) {
            throw new RuntimeException('"' . $documento['titulo'] . '" es muy pesado para leerlo desde acá. El cliente lo puede abrir en la solapa ' . $solapa . '.');
        }
        return [$encabezado, [
            'type' => 'document',
            'title' => (string)$documento['titulo'],
            'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode((string)file_get_contents($ruta))],
        ]];
    }

    if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return [$encabezado, imagen_para_asistente($ruta)];
    }

    if ($extension === 'xlsx') {
        return [$encabezado, ['type' => 'text', 'text' => leer_xlsx_como_texto($ruta)]];
    }

    if ($extension === 'csv') {
        $texto = (string)file_get_contents($ruta, false, null, 0, 200000);
        return [$encabezado, ['type' => 'text', 'text' => $texto]];
    }

    throw new RuntimeException('"' . $documento['titulo'] . '" es un formato que el asistente no puede leer (' . strtoupper($extension) . '). El cliente lo puede abrir en la solapa ' . $solapa . '.');
}

function fotos_de_etapa_para_asistente(string $nombreEtapa, int $obraId): array
{
    $stmt = db()->prepare('SELECT id, nombre FROM etapas WHERE obra_id = ?');
    $stmt->execute([$obraId]);
    $etapa = null;
    $nombres = [];
    foreach ($stmt->fetchAll() as $fila) {
        $nombres[] = $fila['nombre'];
        if (mb_strtolower_seguro(trim($fila['nombre'])) === mb_strtolower_seguro(trim($nombreEtapa))) {
            $etapa = $fila;
        }
    }
    if (!$etapa) {
        throw new RuntimeException('No hay una etapa "' . $nombreEtapa . '". Las etapas son: ' . implode(', ', $nombres) . '.');
    }

    $stmt = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? AND etapa_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . ASISTENTE_FOTOS_POR_ETAPA);
    $stmt->execute([$obraId, $etapa['id']]);
    $fotos = $stmt->fetchAll();
    if (!$fotos) {
        return [['type' => 'text', 'text' => 'La etapa "' . $etapa['nombre'] . '" todavía no tiene fotos.']];
    }

    $bloques = [['type' => 'text', 'text' => count($fotos) . ' fotos de la etapa "' . $etapa['nombre'] . '", de la más nueva a la más vieja:']];
    foreach ($fotos as $foto) {
        $ruta = __DIR__ . '/../uploads/obras/' . $obraId . '/' . basename((string)$foto['archivo']);
        if (!is_file($ruta)) {
            continue;
        }
        $bloques[] = ['type' => 'text', 'text' => 'Foto del ' . formatear_fecha(substr((string)$foto['created_at'], 0, 10)) . ($foto['descripcion'] ? ': ' . $foto['descripcion'] : '')];
        $bloques[] = imagen_para_asistente($ruta);
    }
    return $bloques;
}

function mb_strtolower_seguro(string $texto): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
}

/**
 * Una imagen como bloque para la API, achicada a ASISTENTE_IMAGEN_LADO_MAXIMO
 * y en JPEG. Si no hay GD, va tal cual.
 */
function imagen_para_asistente(string $ruta): array
{
    $info = @getimagesize($ruta);
    if ($info === false) {
        throw new RuntimeException('Una de las imágenes no se pudo leer.');
    }
    $tipos = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
    $abrir = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!isset($tipos[$info[2]])) {
        throw new RuntimeException('Una de las imágenes tiene un formato que no se puede leer.');
    }

    [$ancho, $alto] = $info;
    $escala = min(1, ASISTENTE_IMAGEN_LADO_MAXIMO / max($ancho, $alto));
    $datos = null;

    if (function_exists($abrir[$info[2]]) && function_exists('imagejpeg')) {
        $original = @($abrir[$info[2]])($ruta);
        if ($original) {
            $nuevoAncho = max(1, (int)round($ancho * $escala));
            $nuevoAlto = max(1, (int)round($alto * $escala));
            $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
            imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
            imagecopyresampled($lienzo, $original, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
            ob_start();
            imagejpeg($lienzo, null, 82);
            $datos = (string)ob_get_clean();
            imagedestroy($original);
            imagedestroy($lienzo);
            return ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($datos)]];
        }
    }

    return ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $tipos[$info[2]], 'data' => base64_encode((string)file_get_contents($ruta))]];
}

/**
 * La primera hoja de un .xlsx como texto, una fila por renglón y las
 * celdas separadas por " | ". Lee el XML de adentro del zip, sin
 * librerías. Los colores de las celdas no se leen: en un Gantt alcanza
 * con las columnas de inicio y fin.
 */
function leer_xlsx_como_texto(string $ruta): string
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('El servidor no puede abrir planillas de Excel (falta la extensión zip).');
    }
    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) {
        throw new RuntimeException('La planilla no se pudo abrir.');
    }

    $compartidos = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml !== false) {
        $sst = @simplexml_load_string($xml);
        foreach ($sst ? $sst->si : [] as $si) {
            // Texto simple (<t>) o con formato (<r><t>...</t></r>).
            $texto = isset($si->t) ? (string)$si->t : '';
            foreach ($si->r ?? [] as $r) {
                $texto .= (string)$r->t;
            }
            $compartidos[] = $texto;
        }
    }

    $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    $datos = $hoja !== false ? @simplexml_load_string($hoja) : false;
    if (!$datos || !isset($datos->sheetData)) {
        throw new RuntimeException('La planilla no tiene una hoja que se pueda leer.');
    }

    $lineas = [];
    foreach ($datos->sheetData->row as $fila) {
        if (count($lineas) >= ASISTENTE_EXCEL_FILAS) {
            $lineas[] = '(la planilla sigue; se cortó en ' . ASISTENTE_EXCEL_FILAS . ' filas)';
            break;
        }
        $celdas = [];
        foreach ($fila->c as $c) {
            // "C5" -> columna 3
            preg_match('/^([A-Z]+)/', (string)$c['r'], $m);
            $columna = 0;
            foreach (str_split($m[1] ?? 'A') as $letra) {
                $columna = $columna * 26 + (ord($letra) - 64);
            }
            if ($columna > ASISTENTE_EXCEL_COLUMNAS) {
                continue;
            }
            $tipo = (string)$c['t'];
            $valor = match ($tipo) {
                's' => $compartidos[(int)$c->v] ?? '',
                'inlineStr' => (string)($c->is->t ?? ''),
                default => (string)$c->v,
            };
            if ($valor !== '') {
                $celdas[$columna] = $valor;
            }
        }
        if ($celdas) {
            ksort($celdas);
            $lineas[] = implode(' | ', $celdas);
        }
    }
    return $lineas ? implode("\n", $lineas) : '(la planilla está vacía)';
}
