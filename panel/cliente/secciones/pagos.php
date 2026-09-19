<?php
/**
 * Propietarios > Pagos: los pagos de la obra que carga el estudio. A cada
 * uno el cliente le puede adjuntar el comprobante (o reemplazarlo), y
 * sacar el que subió él.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$totales = [];
foreach ($pagos as $pago) {
    $totales[$pago['moneda']] = ($totales[$pago['moneda']] ?? 0) + (float)$pago['monto'];
}
$pagoConError = (int)($_POST['pago_id'] ?? 0);
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Pagos</h1>
    <p class="cliente-cabecera__intro">Los pagos de la obra que registró el estudio. Adjuntá el comprobante de cada uno para que quede todo junto.</p>
</header>

<?php if ($avisoCuenta): ?>
    <p class="cliente-aviso" role="status"><?= e($avisoCuenta) ?></p>
<?php endif; ?>

<?php if (!$pagos): ?>
    <p class="cliente-nada">Todavía no hay pagos registrados.</p>
<?php else: ?>
    <p class="pagos__total">
        Total registrado:
        <?php foreach ($totales as $moneda => $total): ?>
            <strong><?= e(formatear_monto($total, $moneda)) ?></strong>
        <?php endforeach; ?>
    </p>

    <ul class="pagos">
        <?php foreach ($pagos as $pago): ?>
            <li class="pago">
                <div class="pago__cabecera">
                    <time datetime="<?= e($pago['fecha']) ?>"><?= e(formatear_fecha($pago['fecha'])) ?></time>
                    <h2><?= e($pago['concepto']) ?></h2>
                    <p class="pago__monto"><?= e(formatear_monto($pago['monto'], $pago['moneda'])) ?></p>
                </div>
                <?php if ($pago['detalle']): ?>
                    <p class="pago__detalle"><?= nl2br(e($pago['detalle'])) ?></p>
                <?php endif; ?>

                <div class="pago__comprobante">
                    <?php if ($pago['comprobante']): ?>
                        <a class="book-archivo__tipo" href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $pago['comprobante'])) ?>" target="_blank" rel="noopener">Ver comprobante</a>
                        <span class="pago__estado pago__estado--ok"><?= (int)$pago['comprobante_subido_por'] === (int)$usuario['id'] ? 'Lo subiste vos' : 'Lo subió el estudio' ?></span>
                        <?php if ((int)$pago['comprobante_subido_por'] === (int)$usuario['id']): ?>
                            <form method="post" action="<?= e(url_seccion('pagos')) ?>" onsubmit="return confirm('¿Sacar este comprobante?');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="quitar_comprobante">
                                <input type="hidden" name="pago_id" value="<?= (int)$pago['id'] ?>">
                                <button type="submit" class="cliente-boton cliente-boton--texto">Sacar</button>
                            </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="pago__estado">Sin comprobante</span>
                    <?php endif; ?>

                    <form method="post" action="<?= e(url_seccion('pagos')) ?>" enctype="multipart/form-data" class="pago__subir">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="adjuntar_comprobante">
                        <input type="hidden" name="pago_id" value="<?= (int)$pago['id'] ?>">
                        <label class="visually-hidden" for="comprobante-<?= (int)$pago['id'] ?>">Comprobante</label>
                        <input type="file" id="comprobante-<?= (int)$pago['id'] ?>" name="comprobante" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                        <button type="submit" class="cliente-boton cliente-boton--secundario"><?= $pago['comprobante'] ? 'Reemplazar' : 'Adjuntar' ?></button>
                    </form>
                    <?php if ($errorComprobante && $pagoConError === (int)$pago['id']): ?>
                        <p class="cliente-alerta"><?= e($errorComprobante) ?></p>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
