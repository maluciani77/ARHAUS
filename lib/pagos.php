<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/presupuestos.php';
require_once __DIR__ . '/documentos.php';

/**
 * Los pagos de la obra (Propietarios > Pagos). Los carga el estudio
 * (fecha, concepto, monto) y a cada uno se le adjunta el comprobante: lo
 * puede subir el cliente o el estudio. El comprobante se guarda en la
 * carpeta de archivos de la obra, con guardar_archivo_obra().
 */

function pagos_de_obra(int $obraId): array
{
    asegurar_tabla('pagos');
    $stmt = db()->prepare('SELECT * FROM pagos WHERE obra_id = ? ORDER BY fecha DESC, id DESC');
    $stmt->execute([$obraId]);
    return $stmt->fetchAll();
}

/** Devuelve null si se guardó, o el mensaje de error. */
function agregar_pago(int $obraId, array $datos, int $usuarioId): ?string
{
    $concepto = trim((string)($datos['concepto'] ?? ''));
    $monto = parsear_monto((string)($datos['monto'] ?? ''));
    $moneda = (string)($datos['moneda'] ?? 'ARS');
    $fecha = trim((string)($datos['fecha'] ?? ''));
    $detalle = trim((string)($datos['detalle'] ?? '')) ?: null;
    $fechaValida = DateTime::createFromFormat('!Y-m-d', $fecha);

    if ($concepto === '') {
        return 'El pago necesita un concepto.';
    }
    if (largo_texto($concepto) > 190) {
        return 'El concepto es muy largo (hasta 190 caracteres). El resto va en el detalle.';
    }
    if ($monto === null || $monto <= 0 || $monto > MONTO_MAXIMO) {
        return 'El monto no es válido. Escribilo por ejemplo así: 1.250.000,50';
    }
    if (!in_array($moneda, MONEDAS_PRESUPUESTO, true)) {
        return 'Elegí la moneda del pago.';
    }
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $fecha) {
        return 'El pago necesita una fecha válida.';
    }

    asegurar_tabla('pagos');
    $stmt = db()->prepare('INSERT INTO pagos (obra_id, fecha, concepto, monto, moneda, detalle, cargado_por) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$obraId, $fecha, $concepto, $monto, $moneda, $detalle, $usuarioId]);
    return null;
}

function pago_de_obra(int $pagoId, int $obraId): ?array
{
    asegurar_tabla('pagos');
    $stmt = db()->prepare('SELECT * FROM pagos WHERE id = ? AND obra_id = ?');
    $stmt->execute([$pagoId, $obraId]);
    return $stmt->fetch() ?: null;
}

/** Adjunta (o reemplaza) el comprobante de un pago. Devuelve null o el error. */
function adjuntar_comprobante(int $pagoId, int $obraId, array $archivo, int $usuarioId): ?string
{
    $pago = pago_de_obra($pagoId, $obraId);
    if (!$pago) {
        return 'Ese pago no existe.';
    }
    try {
        [$nombreArchivo, $nombreOriginal] = guardar_archivo_obra($obraId, $archivo);
    } catch (RuntimeException $ex) {
        return $ex->getMessage();
    }

    $stmt = db()->prepare('UPDATE pagos SET comprobante = ?, comprobante_nombre = ?, comprobante_subido_por = ? WHERE id = ? AND obra_id = ?');
    $stmt->execute([$nombreArchivo, $nombreOriginal, $usuarioId, $pagoId, $obraId]);
    borrar_archivo_obra($obraId, $pago['comprobante']);
    return null;
}

function quitar_comprobante(int $pagoId, int $obraId): void
{
    $pago = pago_de_obra($pagoId, $obraId);
    if (!$pago) {
        return;
    }
    db()->prepare('UPDATE pagos SET comprobante = NULL, comprobante_nombre = NULL, comprobante_subido_por = NULL WHERE id = ? AND obra_id = ?')
        ->execute([$pagoId, $obraId]);
    borrar_archivo_obra($obraId, $pago['comprobante']);
}

function eliminar_pago(int $pagoId, int $obraId): void
{
    $pago = pago_de_obra($pagoId, $obraId);
    if (!$pago) {
        return;
    }
    db()->prepare('DELETE FROM pagos WHERE id = ? AND obra_id = ?')->execute([$pagoId, $obraId]);
    borrar_archivo_obra($obraId, $pago['comprobante']);
}
