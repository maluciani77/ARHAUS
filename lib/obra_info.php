<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Los datos de la obra (ubicación, catastro, superficies, trámites,
 * profesionales). Los carga el estudio y el cliente los ve en Datos >
 * Obra info.
 *
 * Se guardan como pares campo/valor en obra_info: sumar un campo nuevo es
 * agregarlo a CAMPOS_OBRA_INFO, sin tocar la base.
 */

/** clave => [etiqueta, tipo, opciones (para select) o sufijo] */
const CAMPOS_OBRA_INFO = [
    'Ubicación' => [
        'direccion' => ['Dirección (calle y número)', 'text'],
        'barrio' => ['Barrio / country', 'text'],
        'lote' => ['Lote / unidad funcional', 'text'],
        'localidad' => ['Localidad', 'text'],
        'partido' => ['Partido', 'text'],
        'provincia' => ['Provincia', 'text'],
        'codigo_postal' => ['Código postal', 'text'],
        'mapa' => ['Link a Google Maps o coordenadas', 'url'],
    ],
    'Catastro' => [
        'circunscripcion' => ['Circunscripción', 'text'],
        'seccion' => ['Sección', 'text'],
        'manzana' => ['Manzana', 'text'],
        'parcela' => ['Parcela', 'text'],
        'partida' => ['Partida inmobiliaria', 'text'],
        'zonificacion' => ['Zonificación', 'text'],
    ],
    'Superficies' => [
        'sup_terreno' => ['Superficie del terreno', 'number', 'm²'],
        'sup_cubierta' => ['Superficie cubierta', 'number', 'm²'],
        'sup_semicubierta' => ['Superficie semicubierta', 'number', 'm²'],
        'plantas' => ['Cantidad de plantas', 'number', ''],
    ],
    'La obra' => [
        'tipo_obra' => ['Tipo de obra', 'select', ['Vivienda unifamiliar', 'Vivienda multifamiliar', 'Ampliación', 'Remodelación', 'Local comercial', 'Oficinas', 'Otro']],
        'sistema_constructivo' => ['Sistema constructivo', 'text'],
        'fecha_inicio' => ['Fecha de inicio', 'date'],
        'fecha_fin' => ['Fecha estimada de finalización', 'date'],
    ],
    'Municipal' => [
        'expediente' => ['N.º de expediente municipal', 'text'],
        'permiso' => ['N.º de permiso de obra', 'text'],
        'fecha_aprobacion' => ['Fecha de aprobación de planos', 'date'],
    ],
    'Profesionales' => [
        'proyectista' => ['Proyectista', 'text'],
        'matricula_proyectista' => ['Matrícula del proyectista', 'text'],
        'director_obra' => ['Director de obra', 'text'],
        'matricula_director' => ['Matrícula del director de obra', 'text'],
        'constructora' => ['Empresa constructora', 'text'],
    ],
    'Servicios' => [
        'agua' => ['Agua', 'select', ['Conectado', 'En trámite', 'No disponible']],
        'luz' => ['Luz', 'select', ['Conectado', 'En trámite', 'No disponible']],
        'gas' => ['Gas', 'select', ['Conectado', 'En trámite', 'No disponible']],
        'cloacas' => ['Cloacas', 'select', ['Conectado', 'En trámite', 'Pozo', 'No disponible']],
    ],
];

const OBRA_INFO_LARGO_MAXIMO = 500;

/** Todos los campos en una lista plana: clave => definición. */
function campos_obra_info(): array
{
    $campos = [];
    foreach (CAMPOS_OBRA_INFO as $grupo) {
        $campos += $grupo;
    }
    return $campos;
}

/** clave => valor, solo de los campos cargados. */
function obra_info(int $obraId): array
{
    asegurar_tabla('obra_info');
    $stmt = db()->prepare('SELECT campo, valor FROM obra_info WHERE obra_id = ?');
    $stmt->execute([$obraId]);
    $valores = [];
    foreach ($stmt->fetchAll() as $fila) {
        $valores[$fila['campo']] = (string)$fila['valor'];
    }
    return $valores;
}

/** Cómo se muestra un valor: fechas en dd/mm/aaaa, superficies con su unidad. */
/**
 * Saca las coordenadas de lo que el estudio haya cargado en "mapa".
 *
 * Hace falta porque el mapa que se dibuja en la pagina no puede usar un
 * link: tiene que recibir un punto. Si se le pasa la direccion escrita,
 * Google la geocodifica y, cuando cae en un barrio cerrado o en un lote
 * sin numeracion, muestra la zona SIN el pin. Por eso el boton "Abrir en
 * Google Maps" llevaba bien y el mapa de arriba aparecia sin marcador.
 *
 * Acepta las formas en las que la gente copia una ubicacion:
 *   -34.4035, -58.6630
 *   https://www.google.com/maps/place/Casa/@-34.4035,-58.6630,17z/...
 *   https://maps.google.com/?q=-34.4035,-58.6630
 *   ...!3d-34.4035!4d-58.6630
 *
 * Los links cortos (maps.app.goo.gl) NO traen las coordenadas adentro,
 * asi que de esos no se puede sacar nada sin salir a pedirlos.
 *
 * Devuelve "lat,lng" o null.
 */
/**
 * Abre un link corto de Google Maps para quedarse con el largo, que es
 * el unico que trae las coordenadas adentro.
 *
 * Solo se usa al guardar la ficha, nunca al mostrarla: es una salida a
 * internet y no puede colgar la pagina del cliente. Si falla, devuelve
 * null y la ficha se guarda igual.
 */
function expandir_link_corto(string $url): ?string
{
    if (!preg_match('#^https?://(maps\.app\.goo\.gl|goo\.gl/maps)/#i', $url)) {
        return null;
    }
    if (!function_exists('curl_init')) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ARHAUS panel)',
    ]);
    curl_exec($ch);
    $final = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    return ($final !== '' && $final !== $url) ? $final : null;
}

function coordenadas_de_mapa(string $valor): ?string
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    $patrones = [
        '/^\s*(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/',  // pegadas solas
        '/!3d(-?\d{1,3}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',               // el formato interno
        '/@(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',                   // /maps/place/.../@lat,lng
        '/[?&](?:q|query|ll|daddr|center)=(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',
    ];

    foreach ($patrones as $patron) {
        if (preg_match($patron, $valor, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180
                && !($lat === 0.0 && $lng === 0.0)) {
                return $lat . ',' . $lng;
            }
        }
    }

    return null;
}

function valor_obra_info_legible(string $clave, string $valor): string
{
    $definicion = campos_obra_info()[$clave] ?? null;
    if (!$definicion || $valor === '') {
        return $valor;
    }
    if ($definicion[1] === 'date') {
        return formatear_fecha($valor);
    }
    if ($definicion[1] === 'number' && !empty($definicion[2])) {
        return str_replace('.', ',', $valor) . ' ' . $definicion[2];
    }
    return $valor;
}

/**
 * Guarda el formulario completo. Los campos vacíos se borran. Devuelve
 * null si salió bien, o el mensaje de error.
 */
function guardar_obra_info(int $obraId, array $datos): ?string
{
    $limpios = [];
    foreach (campos_obra_info() as $clave => $definicion) {
        $valor = trim((string)($datos[$clave] ?? ''));
        if ($valor === '') {
            continue;
        }
        $etiqueta = $definicion[0];

        if (largo_texto($valor) > OBRA_INFO_LARGO_MAXIMO) {
            return $etiqueta . ': es demasiado largo.';
        }
        switch ($definicion[1]) {
            case 'date':
                $fecha = DateTime::createFromFormat('!Y-m-d', $valor);
                if (!$fecha || $fecha->format('Y-m-d') !== $valor) {
                    return $etiqueta . ': la fecha no es válida.';
                }
                break;
            case 'number':
                $valor = str_replace(',', '.', $valor);
                if (!is_numeric($valor) || (float)$valor < 0) {
                    return $etiqueta . ': tiene que ser un número.';
                }
                break;
            case 'url':
                // El mapa es el unico que ademas de un link acepta un par
                // de coordenadas pegadas: es lo que se copia del telefono
                // manteniendo apretado el punto, y es lo mas exacto.
                if ($clave === 'mapa' && coordenadas_de_mapa($valor) !== null) {
                    break;
                }
                if (!filter_var($valor, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $valor)) {
                    return $etiqueta . ($clave === 'mapa'
                        ? ': pega el link de Google Maps o las coordenadas (por ejemplo -40.1354, -71.2512).'
                        : ': tiene que ser un link que empiece con https://');
                }
                break;
            case 'select':
                if (!in_array($valor, $definicion[2], true)) {
                    return $etiqueta . ': elegí una opción de la lista.';
                }
                break;
        }
        $limpios[$clave] = $valor;
    }

    // Las coordenadas se resuelven UNA VEZ, al guardar, y quedan
    // guardadas aparte. Asi la pagina del cliente no tiene que salir a
    // preguntarle nada a Google cada vez que alguien la abre.
    if (isset($limpios['mapa'])) {
        $coords = coordenadas_de_mapa($limpios['mapa']);
        if ($coords === null) {
            $largo = expandir_link_corto($limpios['mapa']);
            if ($largo !== null) {
                $coords = coordenadas_de_mapa($largo);
            }
        }
        if ($coords !== null) {
            $limpios['mapa_coords'] = $coords;
        }
    }

    asegurar_tabla('obra_info');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM obra_info WHERE obra_id = ?')->execute([$obraId]);
        $ins = $pdo->prepare('INSERT INTO obra_info (obra_id, campo, valor) VALUES (?, ?, ?)');
        foreach ($limpios as $clave => $valor) {
            $ins->execute([$obraId, $clave, $valor]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
    return null;
}
