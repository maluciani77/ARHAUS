<?php
/**
 * Etapas de la obra para admin y arquitecto: línea de tiempo con editar y
 * eliminar, y el formulario para agregar. Antes de incluirlo hay que
 * tener definido $etapas y opcionalmente $errorEtapa.
 */
?>
<h2 id="etapas">Etapas</h2>
<p class="panel-hint">La fecha de cada etapa aparece en el calendario del cliente.</p>
<?php if (!empty($errorEtapa)): ?><p class="panel-alert panel-alert--error"><?= e($errorEtapa) ?></p><?php endif; ?>

<?php if ($etapas): ?>
    <ul class="panel-timeline">
        <?php foreach ($etapas as $etapa): ?>
            <li id="etapa-<?= (int)$etapa['id'] ?>">
                <h4><?= e($etapa['nombre']) ?></h4>
                <?php if ($etapa['fecha']): ?>
                    <time><?= e(formatear_fecha($etapa['fecha'])) ?></time>
                <?php else: ?>
                    <time class="panel-sin-fecha">Sin fecha</time>
                <?php endif; ?>
                <?php if ($etapa['descripcion']): ?><p><?= nl2br(e($etapa['descripcion'])) ?></p><?php endif; ?>

                <div class="panel-etapa__acciones">
                    <details class="panel-editar">
                        <summary class="panel-btn panel-btn--secundario panel-btn--chico">Editar</summary>
                        <form method="post" class="panel-form" action="#etapa-<?= (int)$etapa['id'] ?>">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="editar_etapa">
                            <input type="hidden" name="etapa_id" value="<?= (int)$etapa['id'] ?>">
                            <label>Nombre
                                <input type="text" name="nombre" value="<?= e($etapa['nombre']) ?>" maxlength="255" required>
                            </label>
                            <label>Fecha (opcional)
                                <input type="date" name="fecha" value="<?= e($etapa['fecha'] ?? '') ?>">
                            </label>
                            <label>Descripción (opcional)
                                <textarea name="descripcion" rows="3"><?= e($etapa['descripcion'] ?? '') ?></textarea>
                            </label>
                            <button type="submit">Guardar cambios</button>
                        </form>
                    </details>
                    <?php if ($puedeEliminarEtapas ?? true): ?>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta etapa? Sus fotos no se borran: quedan sin etapa.');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="eliminar_etapa">
                            <input type="hidden" name="etapa_id" value="<?= (int)$etapa['id'] ?>">
                            <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p class="panel-vacio">Todavía no hay etapas cargadas.</p>
<?php endif; ?>

<div class="panel-card">
    <form method="post" class="panel-form" action="#etapas">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_etapa">
        <label>Nombre de la etapa
            <input type="text" name="nombre" placeholder="Ej: Cimientos" required>
        </label>
        <label>Fecha (opcional)
            <input type="date" name="fecha">
        </label>
        <label>Descripción (opcional)
            <textarea name="descripcion" rows="2"></textarea>
        </label>
        <button type="submit">Agregar etapa</button>
    </form>
</div>
