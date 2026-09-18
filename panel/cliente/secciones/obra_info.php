<?php
/**
 * Sección Datos > Obra info del panel del cliente: la ficha de la obra
 * (ubicación, catastro, superficies, trámites, profesionales). La carga
 * el estudio; el cliente la ve.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Obra info</h1>
    <p class="cliente-cabecera__intro">La ficha de tu obra. La completa el estudio; si ves algo que no está bien, avisales por <a href="<?= e(url_seccion('mensajes')) ?>">Mensajes</a>.</p>
</header>

<?php if (!$obraInfo): ?>
    <p class="cliente-nada">El estudio todavía no cargó los datos de la obra.</p>
<?php else: ?>
    <div class="ficha">
        <?php foreach (CAMPOS_OBRA_INFO as $grupo => $campos): ?>
            <?php $cargados = array_intersect_key($campos, $obraInfo); ?>
            <?php if (!$cargados) continue; ?>
            <section class="ficha__grupo">
                <h2><?= e($grupo) ?></h2>
                <dl>
                    <?php foreach ($cargados as $clave => $definicion): ?>
                        <div class="ficha__dato">
                            <dt><?= e($definicion[0]) ?></dt>
                            <dd>
                                <?php if ($definicion[1] === 'url'): ?>
                                    <a href="<?= e($obraInfo[$clave]) ?>" target="_blank" rel="noopener">Abrir el mapa</a>
                                <?php else: ?>
                                    <?= e(valor_obra_info_legible($clave, $obraInfo[$clave])) ?>
                                <?php endif; ?>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
