<?php
/**
 * Sección Dirección de obra del panel del cliente: el día a día que deja
 * el director, de lo más nuevo a lo más viejo. Acá el cliente solo lee;
 * si quiere decir algo, va a Mensajes.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Dirección de obra</h1>
    <p class="cliente-cabecera__intro">El día a día de la obra, contado por quien la dirige.</p>
</header>

<?php if (!$novedades): ?>
    <p class="cliente-nada">Todavía no hay novedades cargadas.</p>
<?php else: ?>
    <ol class="book-diario">
        <?php foreach ($novedades as $novedad): ?>
            <li class="book-diario__item">
                <div class="book-diario__meta">
                    <time datetime="<?= e(substr((string)$novedad['created_at'], 0, 10)) ?>"><?= e(formatear_fecha(substr((string)$novedad['created_at'], 0, 10))) ?></time>
                    <?php if ($novedad['autor_nombre']): ?>
                        <span><?= e($novedad['autor_nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($novedad['archivo']): ?>
                    <?php $pieFoto = 'Dirección de obra — ' . formatear_fecha(substr((string)$novedad['created_at'], 0, 10)); ?>
                    <figure class="book-diario__foto">
                        <a href="<?= e(url_foto($raiz, $obra, ['archivo' => $novedad['archivo']])) ?>" data-visor data-pie="<?= e($pieFoto) ?>">
                            <img src="<?= e(url_foto($raiz, $obra, ['archivo' => $novedad['archivo']])) ?>" alt="Avance de obra del <?= e(formatear_fecha(substr((string)$novedad['created_at'], 0, 10))) ?>" loading="lazy">
                        </a>
                    </figure>
                <?php endif; ?>

                <?php if ($novedad['texto']): ?>
                    <p class="book-diario__texto"><?= nl2br(e($novedad['texto'])) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
