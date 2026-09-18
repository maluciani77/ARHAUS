<?php
/**
 * Sección Etapa de obra del panel del cliente: la línea de tiempo de las
 * etapas. El Gantt está en su propia solapa, Planificación.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$hayPlanificacion = (bool)array_filter($documentos, static function (array $documento): bool {
    return (string)$documento['categoria'] === 'planificacion';
});

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
    <p class="cliente-cabecera__intro">
        En qué anda la obra, etapa por etapa.<?php if ($hayPlanificacion): ?> El cronograma completo está en <a href="<?= e(url_seccion('planificacion')) ?>">Planificación</a>.<?php endif; ?>
    </p>
</header>

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
