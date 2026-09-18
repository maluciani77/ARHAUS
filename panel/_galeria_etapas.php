<?php
/**
 * Fotos de la obra agrupadas por etapa, con botón para eliminar cada una
 * (lo usan admin y arquitecto). Antes de incluirlo hay que tener
 * definidos $raiz, $obra, $etapas y $fotos.
 */
$gruposConFotos = array_filter(
    agrupar_fotos_por_etapa($etapas, $fotos),
    function (array $grupo): bool { return (bool)$grupo['fotos']; }
);
?>
<h2 id="fotos">Fotos</h2>
<?php if (!$gruposConFotos): ?>
    <p class="panel-vacio">Todavía no hay fotos subidas.</p>
<?php endif; ?>
<?php foreach ($gruposConFotos as $grupo): ?>
    <h3 class="panel-galeria__etapa">
        <?= $grupo['etapa'] ? e($grupo['etapa']['nombre']) : 'Sin etapa' ?>
        <span><?= count($grupo['fotos']) ?> <?= count($grupo['fotos']) === 1 ? 'foto' : 'fotos' ?></span>
    </h3>
    <div class="panel-galeria">
        <?php foreach ($grupo['fotos'] as $foto): ?>
            <div class="panel-foto">
                <img src="<?= e($raiz . ruta_publica_foto((int)$obra['id'], $foto['archivo'])) ?>" alt="<?= e($foto['descripcion'] ?? 'Foto de avance') ?>" loading="lazy">
                <?php if (!isset($puedeEliminarFoto) || $puedeEliminarFoto($foto)): ?>
                    <form method="post" onsubmit="return confirm('¿Eliminar esta foto?');">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="eliminar_foto">
                        <input type="hidden" name="foto_id" value="<?= (int)$foto['id'] ?>">
                        <button type="submit" title="Eliminar">×</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
