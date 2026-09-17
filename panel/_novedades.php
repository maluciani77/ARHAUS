<?php
/**
 * Dirección de obra para admin y arquitecto: el día a día que ve el
 * cliente. Se carga un comentario, una foto, o las dos cosas juntas.
 *
 * Antes de incluirlo hay que tener definidos $obra, $novedades (de
 * novedades_de_obra()) y opcionalmente $errorNovedad.
 */

$reenvioNovedad = ($_POST['accion'] ?? '') === 'agregar_novedad' ? $_POST : [];
?>
<h2 id="direccion">Dirección de obra</h2>
<p class="panel-subtitle">El día a día de la obra. El cliente lo ve en su panel, en orden, y no puede escribir acá.</p>

<div class="panel-card">
    <?php if (!empty($errorNovedad)): ?>
        <p class="panel-alert panel-alert--error"><?= e($errorNovedad) ?></p>
    <?php endif; ?>
    <form method="post" action="#direccion" class="panel-form" enctype="multipart/form-data">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_novedad">
        <label>Comentario
            <textarea name="texto" rows="3" placeholder="Hoy se hormigonó la losa del primer piso."><?= e((string)($reenvioNovedad['texto'] ?? '')) ?></textarea>
        </label>
        <label>Foto (opcional)
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">
        </label>
        <button type="submit">Publicar novedad</button>
    </form>
</div>

<?php if (!$novedades): ?>
    <p class="panel-vacio">Todavía no hay novedades cargadas.</p>
<?php else: ?>
    <ul class="panel-novedades">
        <?php foreach ($novedades as $novedad): ?>
            <li class="panel-novedad" id="novedad-<?= (int)$novedad['id'] ?>">
                <div class="panel-novedad__cabecera">
                    <span class="panel-novedad__fecha"><?= e(formatear_fecha(substr((string)$novedad['created_at'], 0, 10))) ?></span>
                    <?php if ($novedad['autor_nombre']): ?>
                        <span class="panel-novedad__autor"><?= e($novedad['autor_nombre']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($novedad['archivo']): ?>
                    <a class="panel-novedad__foto" href="<?= e($raiz . ruta_publica_foto((int)$obra['id'], $novedad['archivo'])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e($raiz . ruta_publica_foto((int)$obra['id'], $novedad['archivo'])) ?>" alt="" loading="lazy">
                    </a>
                <?php endif; ?>

                <?php if ($novedad['texto']): ?>
                    <p class="panel-novedad__texto"><?= nl2br(e($novedad['texto'])) ?></p>
                <?php endif; ?>

                <form method="post" action="#direccion" onsubmit="return confirm('¿Eliminar esta novedad?');">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="eliminar_novedad">
                    <input type="hidden" name="novedad_id" value="<?= (int)$novedad['id'] ?>">
                    <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
