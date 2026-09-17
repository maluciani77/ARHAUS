<?php
/**
 * Listado de archivos de una categoría (renders, proyecto, municipal,
 * varios). Lo incluye cada solapa, que antes define $categoriaDocumento
 * y, si quiere, $introArchivos.
 *
 * Las imágenes se muestran como galería; el resto (PDF, Excel, CSV) como
 * una lista para abrir o descargar.
 */

if (!isset($obra, $categoriaDocumento) || !es_categoria_documento($categoriaDocumento)) {
    http_response_code(404);
    exit;
}

$deLaSeccion = array_values(array_filter($documentos, static function (array $documento) use ($categoriaDocumento): bool {
    return (string)$documento['categoria'] === $categoriaDocumento;
}));

$esImagen = static function (array $documento): bool {
    return tipo_documento($documento['archivo']) === 'Imagen';
};

$imagenes = array_values(array_filter($deLaSeccion, $esImagen));
$otros = array_values(array_filter($deLaSeccion, static function (array $d) use ($esImagen): bool {
    return !$esImagen($d);
}));
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1><?= e(CATEGORIAS_DOCUMENTO[$categoriaDocumento]) ?></h1>
    <?php if (!empty($introArchivos)): ?>
        <p class="cliente-cabecera__intro"><?= e($introArchivos) ?></p>
    <?php endif; ?>
</header>

<?php if (!$deLaSeccion): ?>
    <p class="cliente-nada">Todavía no hay archivos en esta sección.</p>
<?php else: ?>

    <?php if ($imagenes): ?>
        <div class="book-fotos">
            <?php foreach ($imagenes as $documento): ?>
                <figure class="book-foto">
                    <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" data-visor data-pie="<?= e($documento['titulo']) ?>">
                        <img src="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" alt="<?= e($documento['titulo']) ?>" loading="lazy">
                    </a>
                    <figcaption><?= e($documento['titulo']) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($otros): ?>
        <ul class="book-archivos">
            <?php foreach ($otros as $documento): ?>
                <li class="book-archivo">
                    <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" target="_blank" rel="noopener">
                        <span class="book-archivo__tipo"><?= e(tipo_documento($documento['archivo'])) ?></span>
                        <span class="book-archivo__nombre"><?= e($documento['titulo']) ?></span>
                        <span class="book-archivo__dato">
                            <?= e(formatear_fecha(substr((string)$documento['created_at'], 0, 10))) ?> · <?= e(tamano_legible((int)$documento['tamano'])) ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php endif; ?>
