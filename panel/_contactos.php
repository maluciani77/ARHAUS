<?php
/**
 * Contactos de la obra para admin y arquitecto: profesionales (higiene y
 * seguridad, gestor, agrimensor), proveedores y teléfonos útiles, con sus
 * archivos adjuntos. El cliente los ve en Ejecución de obra >
 * Profesionales / Contratados y en Teléfonos útiles.
 *
 * Antes de incluirlo hay que tener definidos $obra, $contactos (de
 * contactos_de_obra()), $archivosContactos (de archivos_de_contactos()) y
 * opcionalmente $errorContacto.
 */

$reenvioContacto = ($_POST['accion'] ?? '') === 'agregar_contacto' ? $_POST : [];
$porTipo = array_fill_keys(array_keys(TIPOS_CONTACTO), []);
foreach ($contactos as $contacto) {
    if (isset($porTipo[$contacto['tipo']])) {
        $porTipo[$contacto['tipo']][] = $contacto;
    }
}
?>
<h2 id="contactos">Profesionales, proveedores y teléfonos</h2>
<p class="panel-subtitle">La gente de la obra. El cliente los ve y los puede llamar o escribir desde su panel.</p>

<div class="panel-card">
    <?php if (!empty($errorContacto)): ?>
        <p class="panel-alert panel-alert--error"><?= e($errorContacto) ?></p>
    <?php endif; ?>
    <form method="post" action="#contactos" class="panel-form">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_contacto">
        <label>Tipo
            <select name="tipo" required>
                <?php foreach (TIPOS_CONTACTO as $clave => $nombre): ?>
                    <option value="<?= e($clave) ?>" <?= ($reenvioContacto['tipo'] ?? '') === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre
            <input type="text" name="nombre" maxlength="190" value="<?= e((string)($reenvioContacto['nombre'] ?? '')) ?>" placeholder="Juan Pérez / Guardia del barrio" required>
        </label>
        <label>Empresa (opcional)
            <input type="text" name="empresa" maxlength="190" value="<?= e((string)($reenvioContacto['empresa'] ?? '')) ?>">
        </label>
        <label>Rubro o función (opcional)
            <input type="text" name="rubro" maxlength="190" value="<?= e((string)($reenvioContacto['rubro'] ?? '')) ?>" placeholder="Aberturas de aluminio / Emergencias">
        </label>
        <label>Teléfono
            <input type="tel" name="telefono" maxlength="40" value="<?= e((string)($reenvioContacto['telefono'] ?? '')) ?>" placeholder="+54 9 11 1234-5678">
        </label>
        <label>Email (opcional)
            <input type="email" name="email" maxlength="190" value="<?= e((string)($reenvioContacto['email'] ?? '')) ?>">
        </label>
        <label>Notas (opcional)
            <textarea name="notas" rows="2"><?= e((string)($reenvioContacto['notas'] ?? '')) ?></textarea>
        </label>
        <button type="submit">Agregar</button>
    </form>
</div>

<?php foreach (TIPOS_CONTACTO as $tipo => $nombreTipo): ?>
    <h3 class="panel-docs__titulo"><?= e($nombreTipo) ?> <span class="panel-tag"><?= count($porTipo[$tipo]) ?></span></h3>
    <?php if (!$porTipo[$tipo]): ?>
        <p class="panel-vacio">Sin cargar.</p>
        <?php continue; ?>
    <?php endif; ?>
    <ul class="panel-novedades">
        <?php foreach ($porTipo[$tipo] as $contacto): ?>
            <li class="panel-novedad">
                <div class="panel-novedad__cabecera">
                    <strong><?= e($contacto['nombre']) ?></strong>
                    <span class="panel-novedad__autor"><?= e(implode(' · ', array_filter([$contacto['rubro'], $contacto['empresa'], $contacto['telefono'], $contacto['email']]))) ?></span>
                </div>
                <?php if ($contacto['notas']): ?><p class="panel-novedad__texto"><?= nl2br(e($contacto['notas'])) ?></p><?php endif; ?>

                <?php foreach ($archivosContactos[(int)$contacto['id']] ?? [] as $archivo): ?>
                    <div class="panel-novedad__texto">
                        <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $archivo['archivo'])) ?>" target="_blank" rel="noopener"><?= e($archivo['titulo']) ?></a>
                        <span class="panel-docs__peso"><?= e(tamano_legible((int)$archivo['tamano'])) ?></span>
                        <form method="post" action="#contactos" style="display:inline" onsubmit="return confirm('¿Eliminar este archivo?');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="eliminar_archivo_contacto">
                            <input type="hidden" name="archivo_id" value="<?= (int)$archivo['id'] ?>">
                            <button type="submit" class="panel-btn panel-btn--chico">Quitar</button>
                        </form>
                    </div>
                <?php endforeach; ?>

                <form method="post" action="#contactos" enctype="multipart/form-data" class="panel-inline">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="adjuntar_archivo_contacto">
                    <input type="hidden" name="contacto_id" value="<?= (int)$contacto['id'] ?>">
                    <input type="text" name="titulo" maxlength="190" placeholder="Nombre del archivo (opcional)">
                    <input type="file" name="documento" required accept=".pdf,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp">
                    <button type="submit" class="panel-btn panel-btn--chico">Adjuntar</button>
                </form>

                <form method="post" action="#contactos" onsubmit="return confirm('¿Eliminar este contacto y sus archivos?');">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="eliminar_contacto">
                    <input type="hidden" name="contacto_id" value="<?= (int)$contacto['id'] ?>">
                    <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar contacto</button>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endforeach; ?>
