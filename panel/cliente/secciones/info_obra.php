<?php
/**
 * Propietarios > Obra info > Info obra: la ficha de la obra (catastro,
 * superficies, trámites, profesionales, servicios), los planos ya
 * registrados, la foto más reciente de la obra y los archivos varios.
 * La carga el estudio; el cliente la ve.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$gruposFicha = CAMPOS_OBRA_INFO;
unset($gruposFicha['Ubicación']);

$delaCategoria = static function (string $categoria) use ($documentos): array {
    return array_values(array_filter($documentos, static fn (array $d): bool => (string)$d['categoria'] === $categoria));
};
$planosRegistrados = $delaCategoria('planos_aprobados');
$archivosVarios = $delaCategoria('info_varios');
$fotoHoy = $fotos ? $fotos[count($fotos) - 1] : null;

$listaArchivos = static function (array $lista) use ($raiz, $obra): void {
    ?>
    <ul class="book-archivos">
        <?php foreach ($lista as $documento): ?>
            <li class="book-archivo">
                <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" target="_blank" rel="noopener">
                    <span class="book-archivo__tipo"><?= e(tipo_documento($documento['archivo'])) ?></span>
                    <span class="book-archivo__nombre"><?= e($documento['titulo']) ?></span>
                    <span class="book-archivo__dato"><?= e(formatear_fecha(substr((string)$documento['created_at'], 0, 10))) ?> · <?= e(tamano_legible((int)$documento['tamano'])) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php
};
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Info obra</h1>
    <p class="cliente-cabecera__intro">La ficha de tu obra. La completa el estudio; si ves algo que no está bien, avisales por <a href="<?= e(url_seccion('mensajes')) ?>">Mensajes</a>.</p>
</header>

<?php if (array_intersect_key(array_merge(...array_values($gruposFicha)), $obraInfo)): ?>
    <div class="ficha">
        <?php foreach ($gruposFicha as $grupo => $campos): ?>
            <?php $cargados = array_intersect_key($campos, $obraInfo); ?>
            <?php if (!$cargados) continue; ?>
            <section class="ficha__grupo">
                <h2><?= e($grupo) ?></h2>
                <dl>
                    <?php foreach ($cargados as $clave => $definicion): ?>
                        <div class="ficha__dato">
                            <dt><?= e($definicion[0]) ?></dt>
                            <dd><?= e(valor_obra_info_legible($clave, $obraInfo[$clave])) ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="cliente-nada">El estudio todavía no cargó los datos de la obra.</p>
<?php endif; ?>

<section class="cliente-bloque" aria-labelledby="titulo-planos-registrados">
    <h2 id="titulo-planos-registrados">Planos registrados</h2>
    <?php if ($planosRegistrados): ?>
        <?php $listaArchivos($planosRegistrados); ?>
    <?php else: ?>
        <p class="cliente-nada">Todavía no hay planos aprobados por la municipalidad.</p>
    <?php endif; ?>
</section>

<section class="cliente-bloque" aria-labelledby="titulo-foto-hoy">
    <h2 id="titulo-foto-hoy">Fotos obra hoy</h2>
    <?php if ($fotoHoy): ?>
        <figure class="info-obra__foto">
            <a href="<?= e(url_foto($raiz, $obra, $fotoHoy)) ?>" data-visor data-pie="La obra el <?= e(formatear_fecha(substr((string)$fotoHoy['created_at'], 0, 10))) ?>">
                <img src="<?= e(url_foto($raiz, $obra, $fotoHoy)) ?>" alt="La foto más reciente de la obra" loading="lazy">
            </a>
            <figcaption>La foto más reciente, del <?= e(formatear_fecha(substr((string)$fotoHoy['created_at'], 0, 10))) ?>. Todas las fotos están en <a href="<?= e(url_seccion('fotos')) ?>">Ejecución de obra &gt; Fotos</a>.</figcaption>
        </figure>
    <?php else: ?>
        <p class="cliente-nada">Todavía no hay fotos de la obra.</p>
    <?php endif; ?>
</section>

<section class="cliente-bloque" aria-labelledby="titulo-archivos-varios">
    <h2 id="titulo-archivos-varios">Archivos varios</h2>
    <?php if ($archivosVarios): ?>
        <?php $listaArchivos($archivosVarios); ?>
    <?php else: ?>
        <p class="cliente-nada">No hay archivos acá todavía.</p>
    <?php endif; ?>
</section>
