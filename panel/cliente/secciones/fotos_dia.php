<?php
/**
 * Ejecución de obra > Dirección de obra > Fotos día por día: las fotos
 * que el director de obra subió con sus novedades, de la más nueva a la
 * más vieja, con su comentario. El texto completo está en Seguimiento.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$conFoto = array_values(array_filter($novedades, static fn (array $n): bool => (bool)$n['archivo']));
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Fotos día por día</h1>
    <p class="cliente-cabecera__intro">Las fotos que sube la dirección de obra con cada novedad. Tocá una para verla en grande.</p>
</header>

<?php if (!$conFoto): ?>
    <p class="cliente-nada">Todavía no hay fotos en el día a día de la obra.</p>
<?php else: ?>
    <div class="book-fotos">
        <?php foreach ($conFoto as $novedad): ?>
            <?php $fecha = formatear_fecha(substr((string)$novedad['created_at'], 0, 10)); ?>
            <figure class="book-foto">
                <a href="<?= e(url_foto($raiz, $obra, ['archivo' => $novedad['archivo']])) ?>" data-visor data-pie="<?= e($fecha . ($novedad['texto'] ? ' — ' . $novedad['texto'] : '')) ?>">
                    <img src="<?= e(url_foto($raiz, $obra, ['archivo' => $novedad['archivo']])) ?>" alt="Foto de la obra del <?= e($fecha) ?>" loading="lazy">
                </a>
                <figcaption>
                    <strong><?= e($fecha) ?></strong>
                    <?php if ($novedad['texto']): ?> · <?= e(recortar_texto($novedad['texto'], 90) . (largo_texto($novedad['texto']) > 90 ? '…' : '')) ?><?php endif; ?>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
