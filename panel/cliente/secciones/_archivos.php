<?php
/**
 * Listado de archivos de una categoría. panel/cliente/index.php lo incluye
 * para cada sección que es una carpeta de archivos (Anteproyecto, Renders,
 * Planos aprobados, etc.), después de definir $categoriaDocumento.
 *
 * Las imágenes se muestran como galería; el resto (PDF, Excel, CSV) como
 * una lista para abrir o descargar. En las carpetas del propio cliente
 * (sus documentos y sus ideas) además puede subir y borrar lo suyo.
 */

if (!isset($obra, $categoriaDocumento) || !es_categoria_documento($categoriaDocumento)) {
    http_response_code(404);
    exit;
}

$introArchivos = [
    'prop_archivos' => 'Tus documentos para los trámites de la obra: DNI, constancia de CUIT y lo que te pida el estudio. Solo los ven vos y el estudio.',
    'contrato' => 'El contrato de la obra y sus anexos.',
    'referentes' => 'Tus ideas para la casa: fotos, revistas, capturas de lo que te gusta. Subilas acá para que el estudio las vea.',
    // Proyecto > Arquitectura
    'planos_arq' => 'Los planos de arquitectura: plantas, cortes, vistas y detalles.',
    'planos_electricos' => 'La instalación eléctrica: bocas, llaves, tableros y circuitos.',
    'planos_sanitarios' => 'La instalación sanitaria: agua, desagües y artefactos.',
    'planos_carpinteria' => 'Las carpinterías: puertas, ventanas y placares, con sus medidas.',
    'planos_cielorrasos' => 'Los cielorrasos: niveles, luminarias y detalles del techo.',
    'plano_estructura' => 'La estructura: fundaciones, columnas, vigas y losas.',
    'planos_amoblamiento' => 'El amoblamiento: cómo se acomodan los muebles en cada ambiente.',
    'planos_municipales' => 'Los planos que van a la municipalidad. Cada uno dice si ya está aprobado o si sigue en trámite.',
    'demolicion' => 'Qué se demuele y qué se conserva de lo que había.',
    'arq_varios' => 'Otros archivos del proyecto, incluido el anteproyecto: la primera versión, con la idea general de la casa.',
    // Proyecto > Visuales
    'brochure' => 'El brochure del proyecto, para ver o compartir.',
    'render' => 'Los renders del proyecto: cómo va a quedar la obra terminada.',
    'videos' => 'Los videos del proyecto: recorridos y animaciones.',
    // Proyecto > Obra
    'computo' => 'El cómputo de materiales del proyecto, por rubro.',
    'presupuesto_proy' => 'Los presupuestos del proyecto.',
    'planificacion_gantt' => 'La planificación del proyecto (Gantt): cuándo va cada etapa.',
    'obra_otros' => 'Otros archivos de obra del proyecto.',
    'informes' => 'Los informes de avance de la obra.',
    'planificacion' => 'El cronograma de la obra (Gantt): cuándo empieza y termina cada tarea.',
    'ejec_archivos' => 'Documentación de la obra que el estudio comparte con vos.',
][$categoriaDocumento] ?? '';

$esDelCliente = in_array($categoriaDocumento, CATEGORIAS_DEL_CLIENTE, true);

$deLaSeccion = array_values(array_filter($documentos, static function (array $documento) use ($categoriaDocumento): bool {
    return (string)$documento['categoria'] === $categoriaDocumento;
}));

$esImagen = static function (array $documento): bool {
    return tipo_documento($documento['archivo']) === 'Imagen';
};

$imagenes = array_values(array_filter($deLaSeccion, $esImagen));
$otros = array_values(array_filter($deLaSeccion, static function (array $d) use ($esImagen): bool {
    return !$esImagen($d);
}));

/**
 * El cartelito de aprobado / en trámite. Solo en los planos municipales:
 * las dos carpetas que había antes se juntaron en una, y esto es lo que
 * conserva la diferencia.
 */
$marcaAprobado = static function (array $documento) use ($categoriaDocumento): void {
    if (!categoria_lleva_aprobacion($categoriaDocumento)) {
        return;
    }
    $aprobado = !empty($documento['aprobado']);
    ?>
    <span class="book-archivo__marca book-archivo__marca--<?= $aprobado ? 'ok' : 'tramite' ?>"><?= $aprobado ? 'Aprobado' : 'En trámite' ?></span>
    <?php
};

/** El botón para borrar, solo en lo que subió el propio cliente. */
$botonBorrar = static function (array $documento) use ($esDelCliente, $usuario): void {
    if (!$esDelCliente || (int)$documento['subido_por'] !== (int)$usuario['id']) {
        return;
    }
    ?>
    <form method="post" action="<?= e(url_seccion((string)$documento['categoria'])) ?>" class="book-archivo__borrar" onsubmit="return confirm('¿Borrar este archivo?');">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="eliminar_archivo_cliente">
        <input type="hidden" name="documento_id" value="<?= (int)$documento['id'] ?>">
        <button type="submit" class="cliente-boton cliente-boton--texto">Borrar</button>
    </form>
    <?php
};
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1><?= e($nombreSeccion) ?></h1>
    <?php if ($introArchivos !== ''): ?>
        <p class="cliente-cabecera__intro"><?= e($introArchivos) ?></p>
    <?php endif; ?>
</header>

<?php if ($esDelCliente): ?>
    <?php if ($avisoCuenta): ?>
        <p class="cliente-aviso" role="status"><?= e($avisoCuenta) ?></p>
    <?php endif; ?>
    <section class="cliente-bloque cliente-bloque--subir" aria-labelledby="titulo-subir">
        <h2 id="titulo-subir"><?= $categoriaDocumento === 'referentes' ? 'Subir una idea' : 'Subir un documento' ?></h2>
        <?php if ($errorArchivoCliente): ?>
            <p class="cliente-alerta"><?= e($errorArchivoCliente) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e(url_seccion($categoriaDocumento)) ?>" enctype="multipart/form-data" class="cliente-form cliente-form--grilla">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="subir_archivo_cliente">
            <input type="hidden" name="categoria" value="<?= e($categoriaDocumento) ?>">
            <label class="cliente-campo">
                <span class="cliente-etiqueta"><?= $categoriaDocumento === 'referentes' ? 'Qué te gusta de esto' : 'Qué es' ?></span>
                <input type="text" name="titulo" maxlength="190" placeholder="<?= $categoriaDocumento === 'referentes' ? 'La cocina con isla y madera clara' : 'DNI de Laura (frente y dorso)' ?>">
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">Archivo</span>
                <input type="file" name="documento" required accept=".pdf,.jpg,.jpeg,.png,.webp<?= $categoriaDocumento === 'prop_archivos' ? ',.xls,.xlsx,.csv' : '' ?>">
            </label>
            <p class="cliente-ayuda cliente-campo--ancho">PDF o imagen (JPG, PNG, WEBP), hasta <?= (int)(DOCUMENTO_TAMANO_MAXIMO / 1048576) ?> MB.</p>
            <div class="cliente-form__acciones">
                <button type="submit" class="cliente-boton">Subir</button>
            </div>
        </form>
    </section>
<?php endif; ?>

<?php if (!$deLaSeccion): ?>
    <p class="cliente-nada"><?= $esDelCliente ? 'Todavía no subiste nada acá.' : 'Todavía no hay archivos en esta sección.' ?></p>
<?php else: ?>

    <?php if ($imagenes): ?>
        <div class="book-fotos">
            <?php foreach ($imagenes as $documento): ?>
                <figure class="book-foto">
                    <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" data-visor data-pie="<?= e($documento['titulo']) ?>">
                        <img src="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" alt="<?= e($documento['titulo']) ?>" loading="lazy">
                    </a>
                    <figcaption>
                        <?= e($documento['titulo']) ?>
                        <?php $marcaAprobado($documento); ?>
                        <?php $botonBorrar($documento); ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($otros): ?>
        <ul class="book-archivos">
            <?php foreach ($otros as $documento): ?>
                <li class="book-archivo">
                    <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" target="_blank" rel="noopener">
                        <span class="book-archivo__tipo"><?= e(tipo_documento($documento['archivo'])) ?></span>
                        <span class="book-archivo__nombre"><?= e($documento['titulo']) ?></span>
                        <span class="book-archivo__dato">
                            <?= e(formatear_fecha(substr((string)$documento['created_at'], 0, 10))) ?> · <?= e(tamano_legible((int)$documento['tamano'])) ?>
                        </span>
                    </a>
                    <?php $marcaAprobado($documento); ?>
                    <?php $botonBorrar($documento); ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

<?php endif; ?>
