<?php
/**
 * Sección Inicio del panel del cliente: portada con la última foto,
 * datos rápidos, últimas fotos y actividad reciente.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$portada = $fotos ? $fotos[count($fotos) - 1] : null;

// Etapa actual: la última (en orden) que ya empezó, sea porque su fecha
// ya pasó o porque tiene fotos. Si ninguna, la primera que haya.
$fotosPorEtapa = [];
foreach ($grupos as $grupo) {
    if ($grupo['etapa']) {
        $fotosPorEtapa[(int)$grupo['etapa']['id']] = count($grupo['fotos']);
    }
}
$etapaActual = $etapas[0] ?? null;
foreach ($etapas as $etapa) {
    $empezo = ($etapa['fecha'] && $etapa['fecha'] <= $hoy) || !empty($fotosPorEtapa[(int)$etapa['id']]);
    if ($empezo) {
        $etapaActual = $etapa;
    }
}

// Próximo: el primer evento o etapa desde hoy en adelante.
$proximo = null;
foreach ($eventos as $fecha => $delDia) {
    if ($fecha < $hoy) {
        continue;
    }
    foreach ($delDia as $evento) {
        if ($evento['tipo'] === 'evento' || $evento['tipo'] === 'etapa') {
            $proximo = ['fecha' => $fecha, 'evento' => $evento];
            break 2;
        }
    }
}

$recientes = [];
foreach (array_reverse($eventos, true) as $fecha => $delDia) {
    if ($fecha > $hoy) {
        continue;
    }
    foreach ($delDia as $evento) {
        $recientes[] = ['fecha' => $fecha] + $evento;
    }
    if (count($recientes) >= 4) {
        break;
    }
}
$recientes = array_slice($recientes, 0, 4);

$ultimasFotos = array_slice(array_reverse($fotos), 0, 6);
$nombresEtapa = [];
foreach ($etapas as $etapa) {
    $nombresEtapa[(int)$etapa['id']] = $etapa['nombre'];
}
?>
<section class="cliente-portada<?= $portada ? ' cliente-portada--foto' : '' ?>">
    <?php if ($portada): ?>
        <img class="cliente-portada__img" src="<?= e(url_foto($raiz, $obra, $portada)) ?>" alt="">
    <?php endif; ?>
    <div class="cliente-portada__texto">
        <p class="cliente-etiqueta"><?= e(nombre_estado($obra['estado'])) ?></p>
        <h1 class="cliente-portada__titulo"><?= e($obra['nombre']) ?></h1>
        <?php if ($obra['ubicacion']): ?>
            <p class="cliente-portada__lugar"><?= e($obra['ubicacion']) ?></p>
        <?php endif; ?>
    </div>
</section>

<div class="cliente-datos">
    <a class="cliente-dato" href="<?= e(url_seccion('fotos', [], $etapaActual ? 'etapa-' . (int)$etapaActual['id'] : null)) ?>">
        <span class="cliente-etiqueta">Etapa actual</span>
        <strong><?= $etapaActual ? e($etapaActual['nombre']) : 'Sin etapas todavía' ?></strong>
        <span class="cliente-dato__pie"><?= $etapaActual && $etapaActual['fecha'] ? e(formatear_fecha($etapaActual['fecha'])) : '&nbsp;' ?></span>
    </a>
    <a class="cliente-dato" href="<?= e(url_seccion('fotos')) ?>">
        <span class="cliente-etiqueta">Fotos</span>
        <strong><?= count($fotos) ?></strong>
        <span class="cliente-dato__pie"><?= $portada ? 'Última: ' . e(formatear_fecha($portada['created_at'])) : 'Todavía no hay fotos' ?></span>
    </a>
    <?php if ($proximo): ?>
        <a class="cliente-dato" href="<?= e(url_seccion('calendario', ['mes' => substr($proximo['fecha'], 0, 7)], 'dia-' . $proximo['fecha'])) ?>">
            <span class="cliente-etiqueta"><?= $proximo['fecha'] === $hoy ? 'Hoy' : 'Próximo' ?></span>
            <strong><?= e($proximo['evento']['titulo']) ?></strong>
            <span class="cliente-dato__pie"><?= e(formatear_fecha($proximo['fecha'])) ?><?= $proximo['evento']['hora'] ? ' · ' . e($proximo['evento']['hora']) . ' hs' : '' ?></span>
        </a>
    <?php else: ?>
        <a class="cliente-dato" href="<?= e(url_seccion('presupuestos')) ?>">
            <span class="cliente-etiqueta">Presupuestos</span>
            <strong><?= count($presupuestos) ?></strong>
            <span class="cliente-dato__pie"><?= $presupuestos ? 'Último: ' . e(formatear_fecha($presupuestos[0]['fecha'])) : 'Todavía no hay presupuestos' ?></span>
        </a>
    <?php endif; ?>
</div>

<?php if ($ultimasFotos): ?>
    <section class="cliente-bloque">
        <header class="cliente-bloque__cabecera">
            <h2>Últimas fotos</h2>
            <a href="<?= e(url_seccion('fotos')) ?>">Ver todas</a>
        </header>
        <div class="cliente-ultimas">
            <?php foreach ($ultimasFotos as $foto): ?>
                <?php $etapaNombre = $nombresEtapa[(int)($foto['etapa_id'] ?? 0)] ?? 'Más fotos'; ?>
                <a href="<?= e(url_foto($raiz, $obra, $foto)) ?>" data-visor data-pie="<?= e($etapaNombre . ($foto['descripcion'] ? ' — ' . $foto['descripcion'] : '')) ?>">
                    <img src="<?= e(url_foto($raiz, $obra, $foto)) ?>" alt="<?= e($foto['descripcion'] ?: 'Foto de ' . $etapaNombre) ?>" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if ($recientes): ?>
    <section class="cliente-bloque">
        <header class="cliente-bloque__cabecera">
            <h2>Actividad reciente</h2>
            <a href="<?= e(url_seccion('calendario')) ?>">Ver calendario</a>
        </header>
        <ul class="cliente-eventos">
            <?php foreach ($recientes as $evento): ?>
                <li class="cliente-evento cliente-evento--<?= e($evento['tipo']) ?>">
                    <time datetime="<?= e($evento['fecha']) ?>"><?= e(formatear_fecha($evento['fecha'])) ?></time>
                    <a href="<?= e(url_seccion($evento['seccion'], $evento['extra'], $evento['ancla'])) ?>"><?= e($evento['titulo']) ?></a>
                    <?php if ($evento['detalle']): ?><span class="cliente-evento__detalle"><?= e($evento['detalle']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
