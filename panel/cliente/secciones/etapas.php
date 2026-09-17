<?php
/**
 * Sección Etapa de obra del panel del cliente: la línea de tiempo de las
 * etapas y, arriba, el Gantt que sube el estudio como archivo.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$gantts = array_values(array_filter($documentos, static function (array $documento): bool {
    return (string)$documento['categoria'] === 'etapas';
}));

$hoyEtapas = $hoy ?? date('Y-m-d');
$etapaActual = null;
foreach ($etapas as $etapa) {
    if (!$etapa['fecha'] || $etapa['fecha'] <= $hoyEtapas) {
        $etapaActual = $etapa;
    }
}
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Etapa de obra</h1>
    <p class="cliente-cabecera__intro">En qué anda la obra y el cronograma completo.</p>
</header>

<?php if ($gantts): ?>
    <ul class="book-archivos">
        <?php foreach ($gantts as $documento): ?>
            <li class="book-archivo">
                <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo'])) ?>" target="_blank" rel="noopener">
                    <span class="book-archivo__tipo"><?= e(tipo_documento($documento['archivo'])) ?></span>
                    <span class="book-archivo__nombre"><?= e($documento['titulo']) ?></span>
                    <span class="book-archivo__dato">
                        <?= e(formatear_fecha(substr((string)$documento['created_at'], 0, 10))) ?> · <?= e(tamano_legible((int)$documento['tamano'])) ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (!$etapas): ?>
    <p class="cliente-nada">Todavía no hay etapas cargadas.</p>
<?php else: ?>
    <ol class="book-etapas">
        <?php $n = 0; foreach ($etapas as $etapa): ?>
            <?php
            $pasada = $etapa['fecha'] && $etapa['fecha'] < $hoyEtapas;
            $esActual = $etapaActual && (int)$etapa['id'] === (int)$etapaActual['id'];
            $fotosEtapa = 0;
            foreach ($fotos as $foto) {
                if ((int)($foto['etapa_id'] ?? 0) === (int)$etapa['id']) {
                    $fotosEtapa++;
                }
            }
            ?>
            <li class="book-etapa<?= $esActual ? ' is-actual' : ($pasada ? ' is-pasada' : '') ?>">
                <div class="book-etapa__marca">
                    <span class="book-etapa__num"><?= numero_capitulo(++$n) ?></span>
                </div>
                <div class="book-etapa__cuerpo">
                    <?php if ($esActual): ?>
                        <p class="cliente-etiqueta cliente-etiqueta--acento">Etapa actual</p>
                    <?php endif; ?>
                    <h2><?= e($etapa['nombre']) ?></h2>
                    <?php if ($etapa['fecha']): ?>
                        <time class="book-etapa__fecha" datetime="<?= e($etapa['fecha']) ?>"><?= e(formatear_fecha($etapa['fecha'])) ?></time>
                    <?php endif; ?>
                    <?php if ($etapa['descripcion']): ?>
                        <p class="book-etapa__texto"><?= nl2br(e($etapa['descripcion'])) ?></p>
                    <?php endif; ?>
                    <?php if ($fotosEtapa): ?>
                        <a class="book-etapa__fotos" href="<?= e(url_seccion('fotos', [], 'etapa-' . (int)$etapa['id'])) ?>">
                            Ver <?= $fotosEtapa === 1 ? 'la foto' : 'las ' . $fotosEtapa . ' fotos' ?>
                        </a>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
