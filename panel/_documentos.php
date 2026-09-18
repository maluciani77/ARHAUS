<?php
/**
 * Archivos de la obra para el estudio, ordenados en los mismos grupos y
 * solapas que ve el cliente (Proyecto, Datos, Municipal, Ejecución).
 *
 * Antes de incluirlo hay que tener definidos $obra, $documentos (de
 * documentos_de_obra()) y opcionalmente $errorDocumento y
 * $categoriasPermitidas (lista de claves; el director de obra solo maneja
 * la planificación). Si no se define, se muestran todas.
 */

$reenvioDoc = ($_POST['accion'] ?? '') === 'agregar_documento' ? $_POST : [];
$categoriasPermitidas = $categoriasPermitidas ?? array_keys(CATEGORIAS_DOCUMENTO);

$porCategoria = array_fill_keys(array_keys(CATEGORIAS_DOCUMENTO), []);
foreach ($documentos as $documento) {
    $categoria = (string)$documento['categoria'];
    if (isset($porCategoria[$categoria])) {
        $porCategoria[$categoria][] = $documento;
    }
}
?>
<h2 id="archivos">Archivos</h2>
<p class="panel-subtitle">
    Lo que el cliente puede abrir y descargar desde su panel. Se aceptan PDF, Excel, CSV e imágenes, hasta <?= (int)(DOCUMENTO_TAMANO_MAXIMO / 1048576) ?> MB por archivo.
</p>

<div class="panel-card">
    <?php if (!empty($errorDocumento)): ?>
        <p class="panel-alert panel-alert--error"><?= e($errorDocumento) ?></p>
    <?php endif; ?>
    <form method="post" action="#archivos" class="panel-form" enctype="multipart/form-data">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_documento">
        <label>Sección
            <select name="categoria" required>
                <?php foreach (GRUPOS_DOCUMENTO as $grupo => $claves): ?>
                    <?php $claves = array_values(array_intersect($claves, $categoriasPermitidas)); ?>
                    <?php if (!$claves) continue; ?>
                    <optgroup label="<?= e($grupo) ?>">
                        <?php foreach ($claves as $clave): ?>
                            <option value="<?= e($clave) ?>" <?= ($reenvioDoc['categoria'] ?? '') === $clave ? 'selected' : '' ?>><?= e(CATEGORIAS_DOCUMENTO[$clave]) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Nombre que ve el cliente
            <input type="text" name="titulo" value="<?= e((string)($reenvioDoc['titulo'] ?? '')) ?>" placeholder="Si lo dejás vacío, usa el nombre del archivo">
        </label>
        <label>Archivo
            <input type="file" name="documento" required accept=".pdf,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.webp">
        </label>
        <button type="submit">Subir archivo</button>
    </form>
</div>

<?php foreach (GRUPOS_DOCUMENTO as $grupo => $clavesGrupo): ?>
<?php foreach (array_intersect($clavesGrupo, $categoriasPermitidas) as $clave): ?>
    <?php $nombre = CATEGORIAS_DOCUMENTO[$clave]; ?>
    <h3 class="panel-docs__titulo">
        <span class="panel-docs__grupo"><?= e($grupo) ?> ·</span> <?= e($nombre) ?>
        <span class="panel-tag"><?= count($porCategoria[$clave]) ?></span>
    </h3>

    <?php if (!$porCategoria[$clave]): ?>
        <p class="panel-vacio">Todavía no hay archivos acá.</p>
    <?php else: ?>
        <div class="panel-tabla-wrap">
            <table class="panel-tabla">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tipo</th>
                        <th>Subido</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($porCategoria[$clave] as $documento): ?>
                        <tr>
                            <td>
                                <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" target="_blank" rel="noopener"><?= e($documento['titulo']) ?></a>
                                <span class="panel-docs__peso"><?= e(tamano_legible((int)$documento['tamano'])) ?></span>
                            </td>
                            <td><span class="panel-tag"><?= e(tipo_documento($documento['archivo'])) ?></span></td>
                            <td><?= e(formatear_fecha(substr((string)$documento['created_at'], 0, 10))) ?></td>
                            <td>
                                <?php if (!isset($puedeEliminarDocumento) || $puedeEliminarDocumento($documento)): ?>
                                    <form method="post" action="#archivos" onsubmit="return confirm('¿Eliminar este archivo?');">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar_documento">
                                        <input type="hidden" name="documento_id" value="<?= (int)$documento['id'] ?>">
                                        <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>
