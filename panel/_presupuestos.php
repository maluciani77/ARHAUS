<?php
/**
 * Sección de presupuestos de una obra para admin y arquitecto: listado,
 * formulario, ocultar/mostrar el monto al cliente y eliminar. El cliente
 * los ve en su propia página (panel/cliente/index.php).
 *
 * Antes de incluirla hay que tener definido $presupuestos (de
 * presupuestos_de_obra()) y opcionalmente $errorPresupuesto, que se
 * muestra arriba del formulario.
 */

// Si el formulario volvió con error, no perder lo que ya se escribió.
$reenvio = ($_POST['accion'] ?? '') === 'agregar_presupuesto' ? $_POST : [];
$valor = function (string $campo, string $defecto = '') use ($reenvio): string {
    return (string)($reenvio[$campo] ?? $defecto);
};
?>
<h2 id="presupuestos">Presupuestos</h2>
<?php if ($presupuestos): ?>
    <div class="panel-tabla-wrap">
        <table class="panel-tabla panel-presupuestos">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th class="panel-presupuestos__monto">Monto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($presupuestos as $presupuesto): ?>
                    <?php $oculto = (bool)$presupuesto['monto_oculto']; ?>
                    <tr>
                        <td class="panel-presupuestos__fecha"><?= e(formatear_fecha($presupuesto['fecha'])) ?></td>
                        <td>
                            <?= e($presupuesto['concepto']) ?>
                            <?php if ($presupuesto['detalle']): ?>
                                <p class="panel-presupuestos__detalle"><?= nl2br(e($presupuesto['detalle'])) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="panel-presupuestos__monto">
                            <?= e(formatear_monto($presupuesto['monto'], $presupuesto['moneda'])) ?>
                            <?php if ($oculto): ?>
                                <span class="panel-presupuestos__oculto">Oculto al cliente</span>
                            <?php endif; ?>
                        </td>
                        <td class="panel-presupuestos__acciones">
                            <form method="post">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="alternar_monto_oculto">
                                <input type="hidden" name="presupuesto_id" value="<?= (int)$presupuesto['id'] ?>">
                                <button type="submit" class="panel-btn panel-btn--secundario panel-btn--chico"><?= $oculto ? 'Mostrar monto' : 'Ocultar monto' ?></button>
                            </form>
                            <form method="post" onsubmit="return confirm('¿Eliminar este presupuesto?');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar_presupuesto">
                                <input type="hidden" name="presupuesto_id" value="<?= (int)$presupuesto['id'] ?>">
                                <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <p class="panel-vacio">Todavía no hay presupuestos cargados.</p>
<?php endif; ?>

<?php if (!empty($errorPresupuesto)): ?><p class="panel-alert panel-alert--error"><?= e($errorPresupuesto) ?></p><?php endif; ?>
<div class="panel-card">
    <form method="post" class="panel-form" action="#presupuestos">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_presupuesto">
        <label>Concepto
            <input type="text" name="concepto" maxlength="190" placeholder="Ej: Estructura de hormigón" value="<?= e($valor('concepto')) ?>" required>
        </label>
        <div class="panel-form__fila">
            <label>Monto
                <input type="text" name="monto" inputmode="decimal" placeholder="Ej: 1.250.000,50" value="<?= e($valor('monto')) ?>" required>
            </label>
            <label>Moneda
                <select name="moneda">
                    <?php foreach (MONEDAS_PRESUPUESTO as $moneda): ?>
                        <option value="<?= $moneda ?>" <?= $moneda === $valor('moneda', 'ARS') ? 'selected' : '' ?>><?= $moneda === 'USD' ? 'Dólares (US$)' : 'Pesos ($)' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Fecha
                <input type="date" name="fecha" value="<?= e($valor('fecha', date('Y-m-d'))) ?>" required>
            </label>
        </div>
        <label>Detalle (opcional)
            <textarea name="detalle" rows="2" placeholder="Qué incluye, validez, forma de pago..."><?= e($valor('detalle')) ?></textarea>
        </label>
        <label class="panel-form__check">
            <input type="checkbox" name="monto_oculto" value="1" <?= $valor('monto_oculto') !== '' ? 'checked' : '' ?>>
            Ocultar el monto al cliente (ve el concepto, la fecha y el detalle)
        </label>
        <button type="submit">Guardar presupuesto</button>
    </form>
</div>
