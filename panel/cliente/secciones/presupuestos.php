<?php
/**
 * Sección Presupuestos del panel del cliente, del más reciente al más
 * viejo. Los montos que el estudio marcó como ocultos no se imprimen.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Presupuestos</h1>
    <p class="cliente-cabecera__intro">Los presupuestos de tu obra, del más reciente al más antiguo.</p>
</header>

<?php if (!$presupuestos): ?>
    <p class="cliente-nada">Todavía no hay presupuestos cargados.</p>
<?php else: ?>
    <ul class="book-presupuestos">
        <?php foreach ($presupuestos as $presupuesto): ?>
            <li class="book-presupuesto">
                <time class="book-presupuesto__fecha" datetime="<?= e($presupuesto['fecha']) ?>"><?= e(formatear_fecha($presupuesto['fecha'])) ?></time>
                <div class="book-presupuesto__cuerpo">
                    <h2><?= e($presupuesto['concepto']) ?></h2>
                    <?php if ($presupuesto['detalle']): ?>
                        <p class="book-presupuesto__detalle"><?= nl2br(e($presupuesto['detalle'])) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!$presupuesto['monto_oculto']): ?>
                    <p class="book-presupuesto__monto"><?= e(formatear_monto($presupuesto['monto'], $presupuesto['moneda'])) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
