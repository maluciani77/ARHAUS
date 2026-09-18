<?php
/**
 * Formulario de la ficha de la obra (Obra info) para admin y arquitecto.
 * El cliente la ve en Datos > Obra info.
 *
 * Antes de incluirlo hay que tener definidos $obraInfo (de obra_info()) y
 * opcionalmente $errorObraInfo.
 */

$reenvioInfo = ($_POST['accion'] ?? '') === 'guardar_obra_info' ? $_POST : null;
$valorInfo = static function (string $clave) use ($reenvioInfo, $obraInfo): string {
    return (string)($reenvioInfo !== null ? ($reenvioInfo[$clave] ?? '') : ($obraInfo[$clave] ?? ''));
};
?>
<h2 id="obra-info">Obra info</h2>
<p class="panel-subtitle">La ficha de la obra. El cliente la ve en Datos &gt; Obra info. Lo que quede vacío no se muestra.</p>

<div class="panel-card">
    <?php if (!empty($errorObraInfo)): ?>
        <p class="panel-alert panel-alert--error"><?= e($errorObraInfo) ?></p>
    <?php endif; ?>
    <form method="post" action="#obra-info" class="panel-form panel-form--ficha">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar_obra_info">
        <?php foreach (CAMPOS_OBRA_INFO as $grupo => $campos): ?>
            <fieldset class="panel-ficha__grupo">
                <legend><?= e($grupo) ?></legend>
                <?php foreach ($campos as $clave => $definicion): ?>
                    <label><?= e($definicion[0]) ?><?= $definicion[1] === 'number' && !empty($definicion[2]) ? ' (' . e($definicion[2]) . ')' : '' ?>
                        <?php if ($definicion[1] === 'select'): ?>
                            <select name="<?= e($clave) ?>">
                                <option value="">—</option>
                                <?php foreach ($definicion[2] as $opcion): ?>
                                    <option value="<?= e($opcion) ?>" <?= $valorInfo($clave) === $opcion ? 'selected' : '' ?>><?= e($opcion) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input
                                type="<?= e(['date' => 'date', 'url' => 'url', 'number' => 'text'][$definicion[1]] ?? 'text') ?>"
                                name="<?= e($clave) ?>"
                                value="<?= e($valorInfo($clave)) ?>"
                                <?= $definicion[1] === 'number' ? 'inputmode="decimal"' : '' ?>
                                maxlength="<?= OBRA_INFO_LARGO_MAXIMO ?>">
                        <?php endif; ?>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php endforeach; ?>
        <button type="submit">Guardar la ficha</button>
    </form>
</div>
