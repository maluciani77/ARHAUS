<?php
/**
 * Pagos de la obra para admin y arquitecto: el formulario para
 * registrarlos, el comprobante de cada uno (lo puede subir el cliente o el
 * estudio) y eliminar. El cliente los ve en Propietarios > Pagos.
 *
 * Antes de incluirlo hay que tener definidos $obra, $pagos (de
 * pagos_de_obra()) y opcionalmente $errorPago.
 */

$reenvioPago = ($_POST['accion'] ?? '') === 'agregar_pago' ? $_POST : [];
?>
<h2 id="pagos">Pagos</h2>
<p class="panel-subtitle">Los pagos que hizo el cliente. Él los ve en Propietarios &gt; Pagos y puede adjuntar el comprobante de cada uno.</p>

<div class="panel-card">
    <?php if (!empty($errorPago)): ?>
        <p class="panel-alert panel-alert--error"><?= e($errorPago) ?></p>
    <?php endif; ?>
    <form method="post" action="#pagos" class="panel-form">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_pago">
        <label>Fecha
            <input type="date" name="fecha" value="<?= e((string)($reenvioPago['fecha'] ?? date('Y-m-d'))) ?>" required>
        </label>
        <label>Concepto
            <input type="text" name="concepto" maxlength="190" value="<?= e((string)($reenvioPago['concepto'] ?? '')) ?>" placeholder="Anticipo de carpinterías" required>
        </label>
        <label>Monto
            <input type="text" name="monto" inputmode="decimal" value="<?= e((string)($reenvioPago['monto'] ?? '')) ?>" placeholder="1.250.000,50" required>
        </label>
        <label>Moneda
            <select name="moneda">
                <?php foreach (MONEDAS_PRESUPUESTO as $moneda): ?>
                    <option value="<?= e($moneda) ?>" <?= ($reenvioPago['moneda'] ?? 'ARS') === $moneda ? 'selected' : '' ?>><?= $moneda === 'ARS' ? 'Pesos' : 'Dólares' ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Detalle (opcional)
            <textarea name="detalle" rows="2"><?= e((string)($reenvioPago['detalle'] ?? '')) ?></textarea>
        </label>
        <button type="submit">Registrar pago</button>
    </form>
</div>

<?php if (!$pagos): ?>
    <p class="panel-vacio">Todavía no hay pagos registrados.</p>
<?php else: ?>
    <div class="panel-tabla-wrap">
        <table class="panel-tabla panel-presupuestos">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th class="panel-presupuestos__monto">Monto</th>
                    <th>Comprobante</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <tr>
                        <td class="panel-presupuestos__fecha"><?= e(formatear_fecha($pago['fecha'])) ?></td>
                        <td>
                            <?= e($pago['concepto']) ?>
                            <?php if ($pago['detalle']): ?><br><span class="panel-docs__peso"><?= e($pago['detalle']) ?></span><?php endif; ?>
                        </td>
                        <td class="panel-presupuestos__monto"><?= e(formatear_monto($pago['monto'], $pago['moneda'])) ?></td>
                        <td>
                            <?php if ($pago['comprobante']): ?>
                                <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $pago['comprobante'])) ?>" target="_blank" rel="noopener">Ver</a>
                                <form method="post" action="#pagos" style="display:inline" onsubmit="return confirm('¿Sacar este comprobante?');">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="quitar_comprobante">
                                    <input type="hidden" name="pago_id" value="<?= (int)$pago['id'] ?>">
                                    <button type="submit" class="panel-btn panel-btn--chico">Sacar</button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="#pagos" enctype="multipart/form-data" class="panel-inline">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="adjuntar_comprobante">
                                    <input type="hidden" name="pago_id" value="<?= (int)$pago['id'] ?>">
                                    <input type="file" name="comprobante" required accept=".pdf,.jpg,.jpeg,.png,.webp">
                                    <button type="submit" class="panel-btn panel-btn--chico">Adjuntar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="#pagos" onsubmit="return confirm('¿Eliminar este pago? Se borra también su comprobante.');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar_pago">
                                <input type="hidden" name="pago_id" value="<?= (int)$pago['id'] ?>">
                                <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
