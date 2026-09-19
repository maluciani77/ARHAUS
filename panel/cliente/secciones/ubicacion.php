<?php
/**
 * Propietarios > Obra info > Ubicación: la dirección de la obra y el mapa.
 * Los datos salen de la ficha que carga el estudio (grupo "Ubicación").
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$datosUbicacion = array_intersect_key(CAMPOS_OBRA_INFO['Ubicación'], $obraInfo);
unset($datosUbicacion['mapa']);

// El mapa se arma con la dirección; sin dirección no hay nada que mostrar.
$partesDireccion = array_filter([
    $obraInfo['direccion'] ?? '',
    $obraInfo['barrio'] ?? '',
    $obraInfo['localidad'] ?? '',
    $obraInfo['provincia'] ?? '',
    'Argentina',
]);
$hayDireccion = !empty($obraInfo['direccion']) || !empty($obraInfo['localidad']);
$mapaEmbebido = 'https://maps.google.com/maps?output=embed&z=15&q=' . rawurlencode(implode(', ', $partesDireccion));
$enlaceMapa = $obraInfo['mapa'] ?? ('https://www.google.com/maps/search/?api=1&query=' . rawurlencode(implode(', ', $partesDireccion)));
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Ubicación</h1>
    <p class="cliente-cabecera__intro">Dónde está la obra. Si algo no está bien, avisale al estudio por <a href="<?= e(url_seccion('mensajes')) ?>">Mensajes</a>.</p>
</header>

<?php if (!$hayDireccion): ?>
    <p class="cliente-nada">El estudio todavía no cargó la dirección de la obra.</p>
<?php else: ?>
    <div class="ubicacion">
        <dl class="ficha__lista">
            <?php foreach ($datosUbicacion as $clave => $definicion): ?>
                <div class="ficha__dato">
                    <dt><?= e($definicion[0]) ?></dt>
                    <dd><?= e(valor_obra_info_legible($clave, $obraInfo[$clave])) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>

        <section class="cliente-bloque" aria-labelledby="titulo-mapa">
            <h2 id="titulo-mapa">Geolocalización</h2>
            <div class="ubicacion__mapa">
                <iframe src="<?= e($mapaEmbebido) ?>" title="Mapa de la obra" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
            <a class="cliente-boton cliente-boton--secundario" href="<?= e($enlaceMapa) ?>" target="_blank" rel="noopener">Abrir en Google Maps</a>
        </section>
    </div>
<?php endif; ?>
