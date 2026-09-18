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
        'mapa' => ['Link a Google Maps', 'url'],
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
                if (!filter_var($valor, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $valor)) {
                    return $etiqueta . ': tiene que ser un link que empiece con https://';
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
