<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MONEDAS_PRESUPUESTO = ['ARS', 'USD'];
const MONTO_MAXIMO = 999999999999.99; // lo que entra en DECIMAL(14,2)

/**
 * Crea la tabla si todavía no existe. Así, en un servidor donde la base
 * se importó antes de que existieran los presupuestos, alcanza con subir
 * los archivos nuevos: no hace falta volver a importar el esquema.
 */
function asegurar_tabla_presupuestos(): void
{
    static $lista = false;
    if ($lista) {
        return;
    }

    try {
        db()->query('SELECT 1 FROM presupuestos LIMIT 1');
    } catch (PDOException $ex) {
        $archivo = db_driver() === 'sqlite' ? 'schema.sqlite.sql' : 'schema.mysql.sql';
        $sql = file_get_contents(__DIR__ . '/../database/' . $archivo);
        foreach (array_map('trim', explode(';', $sql)) as $sentencia) {
            if (stripos($sentencia, 'CREATE TABLE IF NOT EXISTS presupuestos') !== false) {
                db()->exec($sentencia);
            }
        }
    }
    $lista = true;
}

/**
 * Convierte el monto que escribió el usuario en número. Acepta el formato
 * argentino (1.250.000,50) y el internacional (1250000.50), con o sin "$".
 * Devuelve null si no es un monto válido.
 */
function parsear_monto(string $texto): ?float
{
    $limpio = preg_replace('/\s+|US\$|\$|USD|ARS/i', '', $texto);
    if ($limpio === '' || !preg_match('/^[\d.,]+$/', $limpio)) {
        return null;
    }

    $ultimaComa = strrpos($limpio, ',');
    $ultimoPunto = strrpos($limpio, '.');

    if ($ultimaComa !== false && $ultimoPunto !== false) {
        // Están los dos: el que aparece último separa los decimales.
        $separadorDecimal = $ultimaComa > $ultimoPunto ? ',' : '.';
    } elseif ($ultimaComa !== false || $ultimoPunto !== false) {
        // Hay uno solo. Si se repite ("1.250.000") o el punto deja 3 cifras
        // atrás ("1.500"), son miles. Si no, separa decimales ("1500,50").
        $separador = $ultimaComa !== false ? ',' : '.';
        $cifrasDespues = strlen($limpio) - strrpos($limpio, $separador) - 1;
        $sonMiles = substr_count($limpio, $separador) > 1
            || ($separador === '.' && $cifrasDespues === 3);
        $separadorDecimal = $sonMiles ? null : $separador;
    } else {
        $separadorDecimal = null;
    }

    if ($separadorDecimal === null) {
        $parteEntera = $limpio;
        $decimales = '';
    } else {
        $posicion = strrpos($limpio, $separadorDecimal);
        $parteEntera = substr($limpio, 0, $posicion);
        $decimales = substr($limpio, $posicion + 1);
    }

    // Los separadores de miles tienen que agrupar de a 3 cifras ("1.250.000").
    if (strpbrk($parteEntera, '.,') !== false && !preg_match('/^\d{1,3}([.,]\d{3})+$/', $parteEntera)) {
        return null;
    }
    $entero = str_replace(['.', ','], '', $parteEntera);

    if (!preg_match('/^\d*$/', $entero) || !preg_match('/^\d{0,2}$/', $decimales) || $entero . $decimales === '') {
        return null;
    }

    return (float)(($entero === '' ? '0' : $entero) . '.' . ($decimales === '' ? '0' : $decimales));
}

/** "$ 1.250.000,00" o "US$ 18.500,00". */
function formatear_monto($monto, string $moneda): string
{
    $simbolo = $moneda === 'USD' ? 'US$' : '$';
    return $simbolo . ' ' . number_format((float)$monto, 2, ',', '.');
}

/** Presupuestos de una obra, del más reciente al más viejo. */
function presupuestos_de_obra(int $obraId): array
{
    asegurar_tabla_presupuestos();
    $stmt = db()->prepare('SELECT * FROM presupuestos WHERE obra_id = ? ORDER BY fecha DESC, id DESC');
    $stmt->execute([$obraId]);
    return $stmt->fetchAll();
}

/**
 * Valida los datos del formulario y guarda el presupuesto.
 * Devuelve null si salió bien, o el mensaje de error para mostrar.
 */
function agregar_presupuesto(int $obraId, array $datos, int $usuarioId): ?string
{
    $concepto = trim((string)($datos['concepto'] ?? ''));
    $monto = parsear_monto((string)($datos['monto'] ?? ''));
    $moneda = (string)($datos['moneda'] ?? 'ARS');
    $fecha = trim((string)($datos['fecha'] ?? ''));
    $detalle = trim((string)($datos['detalle'] ?? '')) ?: null;

    $fechaValida = DateTime::createFromFormat('Y-m-d', $fecha);

    if ($concepto === '') {
        return 'El presupuesto necesita un concepto.';
    }
    if (preg_match_all('/./us', $concepto) > 190) {
        return 'El concepto es muy largo (hasta 190 caracteres). El resto va en el detalle.';
    }
    if ($monto === null || $monto <= 0 || $monto > MONTO_MAXIMO) {
        return 'El monto no es válido. Escribilo por ejemplo así: 1.250.000,50';
    }
    if (!in_array($moneda, MONEDAS_PRESUPUESTO, true)) {
        return 'Elegí la moneda del presupuesto.';
    }
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        return 'El presupuesto necesita una fecha válida.';
    }

    asegurar_tabla_presupuestos();
    $stmt = db()->prepare(
        'INSERT INTO presupuestos (obra_id, concepto, monto, moneda, fecha, detalle, cargado_por) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $obraId,
        $concepto,
        number_format($monto, 2, '.', ''),
        $moneda,
        $fecha,
        $detalle,
        $usuarioId,
    ]);
    return null;
}

function eliminar_presupuesto(int $presupuestoId, int $obraId): void
{
    asegurar_tabla_presupuestos();
    $stmt = db()->prepare('DELETE FROM presupuestos WHERE id = ? AND obra_id = ?');
    $stmt->execute([$presupuestoId, $obraId]);
}
