<?php
/**
 * Sección Fotos del panel del cliente: cada etapa es un capítulo con sus
 * fotos en grande (estilo book). Las fotos sin etapa van al final.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Fotos</h1>
    <p class="cliente-cabecera__intro">El avance de la obra, etapa por etapa.</p>
</header>

<?php if (!$grupos): ?>
    <p class="cliente-nada">Todavía no hay etapas ni fotos cargadas. Cuando el estudio suba avances, los vas a ver acá.</p>
<?php else: ?>
    <?php if (count($grupos) > 1): ?>
        <nav class="book-indice" aria-label="Etapas">
            <ol>
                <?php $n = 0; foreach ($grupos as $grupo): ?>
                    <?php $etapa = $grupo['etapa']; $cantidad = count($grupo['fotos']); ?>
                    <li>
                        <a href="#<?= $etapa ? 'etapa-' . (int)$etapa['id'] : 'mas-fotos' ?>">
                            <span class="book-indice__num"><?= $etapa ? numero_capitulo(++$n) : '—' ?></span>
                            <span class="book-indice__nombre"><?= $etapa ? e($etapa['nombre']) : 'Más fotos' ?></span>
                            <span class="book-indice__dato">
                                <?php if ($etapa && $etapa['fecha']): ?><?= e(formatear_fecha($etapa['fecha'])) ?> · <?php endif; ?>
                                <?= $cantidad === 0 ? 'sin fotos' : $cantidad . ($cantidad === 1 ? ' foto' : ' fotos') ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
    <?php endif; ?>

    <?php $n = 0; foreach ($grupos as $grupo): ?>
        <?php $etapa = $grupo['etapa']; ?>
        <section class="book-capitulo" id="<?= $etapa ? 'etapa-' . (int)$etapa['id'] : 'mas-fotos' ?>">
            <header class="book-capitulo__cabecera">
                <div>
                    <p class="cliente-etiqueta cliente-etiqueta--acento"><?= $etapa ? 'Etapa ' . numero_capitulo(++$n) : 'Sin etapa' ?></p>
                    <h2 class="book-capitulo__titulo"><?= $etapa ? e($etapa['nombre']) : 'Más fotos' ?></h2>
                    <?php if ($etapa && $etapa['fecha']): ?>
                        <time class="book-capitulo__fecha" datetime="<?= e($etapa['fecha']) ?>"><?= e(formatear_fecha($etapa['fecha'])) ?></time>
                    <?php endif; ?>
                </div>
                <?php if ($etapa && $etapa['descripcion']): ?>
                    <p class="book-capitulo__texto"><?= nl2br(e($etapa['descripcion'])) ?></p>
                <?php endif; ?>
            </header>

            <?php if ($grupo['fotos']): ?>
                <div class="book-fotos">
                    <?php foreach ($grupo['fotos'] as $foto): ?>
                        <?php $pie = ($etapa ? $etapa['nombre'] : 'Más fotos') . ($foto['descripcion'] ? ' — ' . $foto['descripcion'] : ''); ?>
                        <figure class="book-foto">
                            <a href="<?= e(url_foto($raiz, $obra, $foto)) ?>" data-visor data-pie="<?= e($pie) ?>">
                                <img src="<?= e(url_foto($raiz, $obra, $foto)) ?>" alt="<?= e($foto['descripcion'] ?: 'Foto de ' . ($etapa ? $etapa['nombre'] : 'la obra')) ?>" loading="lazy">
                            </a>
                            <?php if ($foto['descripcion']): ?>
                                <figcaption><?= e($foto['descripcion']) ?></figcaption>
                            <?php endif; ?>
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="book-capitulo__sin-fotos">Todavía no hay fotos de esta etapa.</p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
