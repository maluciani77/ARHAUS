<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Archivos de la obra que el cliente puede ver y descargar. Cada categoría
 * es una solapa del panel del cliente, dentro de uno de sus grupos
 * (Proyecto, Datos, Municipal, Ejecución de obra).
 *
 * Se guardan en uploads/obras/{obra_id}/docs/ con un nombre inventado, y
 * el nombre lindo queda en la base. El .htaccess de uploads/ ya impide
 * que se ejecute PHP ahí adentro.
 */

const CATEGORIAS_DOCUMENTO = [
    // Propietarios
    'prop_archivos' => 'Archivos del propietario',
    'contrato' => 'Contrato',
    'referentes' => 'Referentes · Ideas',
    'info_varios' => 'Archivos varios',
    // Proyecto > Planos
    'planos_arq' => 'Arq',
    'planos_electricos' => 'Eléctricos',
    'planos_sanitarios' => 'Sanitarios',
    'planos_carpinteria' => 'Carpintería',
    'planos_cielorrasos' => 'Cielorrasos',
    'plano_estructura' => 'Estructura',
    'planos_amoblamiento' => 'Amoblamiento',
    'planos_municipales' => 'Municipales',
    'demolicion' => 'Demolición',
    'arq_varios' => 'Varios',
    // Proyecto > Visuales
    'brochure' => 'Brochure',
    'render' => 'Renders',
    'videos' => 'Videos',
    // Proyecto > Obra
    'computo' => 'Cómputo',
    'presupuesto_proy' => 'Presupuesto',
    'planificacion_gantt' => 'Planificación (Gantt)',
    'obra_otros' => 'Otros',
    // Ejecución de obra
    'informes' => 'Informes',
    'planificacion' => 'Gantt',
    'ejec_archivos' => 'Archivos de obra',
];

/** En qué botón del panel del cliente cae cada categoría (así se ordenan en el panel del estudio). */
const GRUPOS_DOCUMENTO = [
    'Propietarios' => ['prop_archivos', 'contrato', 'referentes', 'info_varios'],
    'Proyecto · Planos' => ['planos_arq', 'planos_electricos', 'planos_sanitarios', 'planos_carpinteria', 'planos_cielorrasos', 'plano_estructura', 'planos_amoblamiento', 'planos_municipales', 'demolicion', 'arq_varios'],
    'Proyecto · Visuales' => ['brochure', 'render', 'videos'],
    'Proyecto · Obra' => ['computo', 'presupuesto_proy', 'planificacion_gantt', 'obra_otros'],
    'Ejecución de obra' => ['informes', 'planificacion', 'ejec_archivos'],
];

/**
 * Las categorías que carga el propio cliente (sus documentos y sus
 * ideas). Todas las demás las carga solo el estudio.
 */
const CATEGORIAS_DEL_CLIENTE = ['prop_archivos', 'referentes'];

/**
 * Categorías con datos personales: no se le pasan al asistente ni se
 * pueden abrir desde él (DNI, constancia de CUIT...).
 */
const CATEGORIAS_PRIVADAS = ['prop_archivos'];

/**
 * Las categorías de versiones anteriores del panel, y a cuál pasó cada
 * una. migrar_categorias_documentos() las actualiza en la base. Se
 * aplican EN ORDEN, así que las cadenas se resuelven solas: "renders"
 * pasó a "fotos_render" y de ahí a "render".
 */
const CATEGORIAS_DOCUMENTO_ANTERIORES = [
    'etapas' => 'planificacion',
    'renders' => 'fotos_render',
    'fotos_render' => 'render',
    'archivos' => 'ejec_archivos',
    // El árbol de Proyecto que pidió el estudio (25/09/2026)
    'proyecto' => 'arq_varios',
    'anteproyecto' => 'arq_varios',
    'varios' => 'arq_varios',
    'arquitectura' => 'planos_arq',
    'proy_planificacion' => 'planificacion_gantt',
    'municipal' => 'planos_municipales',
    'planos_aprobados' => 'planos_municipales',
    'planos_en_proceso' => 'planos_municipales',
];

/**
 * Las dos carpetas municipales se juntaron en una sola, pero la
 * diferencia sigue importando: Obra info muestra los planos registrados,
 * que son los aprobados. Por eso cada archivo de esa carpeta lleva su
 * marca, y al juntarlas los que venían de "aprobados" la arrancan puesta.
 */
const CATEGORIA_CON_APROBACION = 'planos_municipales';

function categoria_lleva_aprobacion(string $categoria): bool
{
    return $categoria === CATEGORIA_CON_APROBACION;
}

/** Pasa los archivos cargados con categorías viejas a las nuevas. Una vez por pedido. */
function migrar_categorias_documentos(): void
{
    static $lista = false;
    if ($lista) {
        return;
    }
    asegurar_tabla('documentos');
    asegurar_columna('documentos', 'aprobado', db_driver() === 'sqlite' ? 'INTEGER NOT NULL DEFAULT 0' : 'TINYINT(1) NOT NULL DEFAULT 0');

    // Lo que estaba en "Planos aprobados" queda marcado como aprobado
    // ANTES de juntar las carpetas; después ya no se sabría cuál era cuál.
    db()->prepare('UPDATE documentos SET aprobado = 1 WHERE categoria = ?')->execute(['planos_aprobados']);

    $upd = db()->prepare('UPDATE documentos SET categoria = ? WHERE categoria = ?');
    foreach (CATEGORIAS_DOCUMENTO_ANTERIORES as $vieja => $nueva) {
        $upd->execute([$nueva, $vieja]);
    }
    $lista = true;
}

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
    migrar_categorias_documentos();

    if ($categoria !== null) {
        $stmt = db()->prepare('SELECT * FROM documentos WHERE obra_id = ? AND categoria = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$obraId, $categoria]);
    } else {
        $stmt = db()->prepare('SELECT * FROM documentos WHERE obra_id = ? ORDER BY created_at DESC, id DESC');
        $stmt->execute([$obraId]);
    }
    return $stmt->fetchAll();
}

/** Cuántos archivos hay en cada categoría: ['render' => 3, ...]. */
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
 * Valida un archivo subido ($_FILES[...]) y lo guarda en la carpeta de
 * archivos de la obra con un nombre inventado. Devuelve [nombre guardado,
 * nombre original] o lanza RuntimeException con el mensaje para mostrar.
 * La usan los documentos, los comprobantes de pago y los adjuntos de los
 * contactos.
 */
function guardar_archivo_obra(int $obraId, array $archivo): array
{
    $extension = validar_documento_subido($archivo);
    $nombreOriginal = basename((string)$archivo['name']);

    $directorio = directorio_documentos($obraId);
    if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear la carpeta de archivos de la obra.');
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($archivo['tmp_name'], $directorio . '/' . $nombreArchivo)) {
        throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
    }
    return [$nombreArchivo, recortar_texto($nombreOriginal, 255)];
}

/** Borra un archivo de la carpeta de la obra, si existe. */
function borrar_archivo_obra(int $obraId, ?string $archivo): void
{
    if ($archivo && is_file(directorio_documentos($obraId) . '/' . basename($archivo))) {
        unlink(directorio_documentos($obraId) . '/' . basename($archivo));
    }
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
        [$nombreArchivo, $nombreOriginal] = guardar_archivo_obra($obraId, $archivo);
    } catch (RuntimeException $ex) {
        return $ex->getMessage();
    }
    $titulo = trim((string)($datos['titulo'] ?? '')) ?: pathinfo($nombreOriginal, PATHINFO_FILENAME);

    migrar_categorias_documentos();   // deja lista la columna "aprobado"
    $aprobado = categoria_lleva_aprobacion($categoria) && !empty($datos['aprobado']) ? 1 : 0;

    $stmt = db()->prepare(
        'INSERT INTO documentos (obra_id, categoria, titulo, archivo, nombre_original, tamano, subido_por, aprobado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$obraId, $categoria, recortar_texto($titulo, 190), $nombreArchivo, recortar_texto($nombreOriginal, 255), (int)$archivo['size'], $usuarioId, $aprobado]);

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
