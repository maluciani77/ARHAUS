<?php
/**
 * Formulario para subir fotos de una obra (lo usan admin y arquitecto).
 * Antes de incluirlo hay que tener definidos $raiz y $etapas.
 *
 * Se eligen muchas fotos de una. Con JavaScript, js/panel-subida.js las
 * manda de a una (con progreso por foto), así no choca con el límite de
 * tamaño total del servidor. Sin JavaScript se envían todas juntas.
 */
?>
<div class="panel-card">
    <form method="post" enctype="multipart/form-data" class="panel-form" data-subida-multiple>
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="subir_foto">

        <label>Fotos · podés elegir varias a la vez (JPG, PNG o WEBP, hasta 8 MB cada una)
            <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required>
        </label>

        <ul class="panel-subida__lista" hidden></ul>

        <label>Etapa (opcional) · escribí la que quieras
            <input type="text" name="etapa" list="etapas-existentes" maxlength="120"
                   placeholder="Ej: Losa del primer piso" autocomplete="off">
        </label>
        <datalist id="etapas-existentes">
            <?php foreach ($etapas as $etapa): ?>
                <option value="<?= e($etapa['nombre']) ?>">
            <?php endforeach; ?>
        </datalist>
        <p class="panel-hint panel-subida__ayuda">Si la etapa todavía no existe, se crea sola.</p>

        <label>Descripción (opcional, se aplica a todas las fotos)
            <input type="text" name="descripcion" maxlength="255">
        </label>

        <p class="panel-subida__resumen" role="status" hidden></p>

        <button type="submit">Subir fotos</button>
    </form>
</div>
<script src="<?= e($raiz) ?>js/panel-subida.js"></script>
