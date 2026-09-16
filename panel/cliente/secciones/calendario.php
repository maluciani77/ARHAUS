<?php
/**
 * Sección Calendario del panel del cliente: el mes con las fechas de las
 * etapas, los presupuestos y los días en que se subieron fotos, y debajo
 * la agenda de ese mes. Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$mes = mes_valido(is_string($_GET['mes'] ?? null) ? $_GET['mes'] : null) ?? mes_inicial($eventos, $hoy);
$mesesActivos = meses_con_eventos($eventos);

$eventosDelMes = array_filter($eventos, function (string $fecha) use ($mes): bool {
    return strpos($fecha, $mes) === 0;
}, ARRAY_FILTER_USE_KEY);

$tiposEvento = ['etapa' => 'Etapa', 'evento' => 'Evento', 'presupuesto' => 'Presupuesto', 'fotos' => 'Fotos'];
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Calendario</h1>
    <p class="cliente-cabecera__intro">Visitas y reuniones, fechas de las etapas, presupuestos y días en que se subieron fotos.</p>
</header>

<div class="cal">
    <div class="cal__barra">
        <a class="cal__flecha" href="<?= e(url_seccion('calendario', ['mes' => mes_relativo($mes, -1)])) ?>" aria-label="Mes anterior">&#8249;</a>
        <h2 class="cal__mes"><?= e(nombre_mes_anio($mes)) ?></h2>
        <a class="cal__flecha" href="<?= e(url_seccion('calendario', ['mes' => mes_relativo($mes, 1)])) ?>" aria-label="Mes siguiente">&#8250;</a>
    </div>

    <table class="cal__tabla">
        <thead>
            <tr>
                <?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $i => $abreviatura): ?>
                    <th scope="col" abbr="<?= e(NOMBRES_DIA[$i + 1]) ?>"><?= $abreviatura ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach (semanas_del_mes($mes) as $semana): ?>
                <tr>
                    <?php foreach ($semana as $fecha): ?>
                        <?php
                        $delDia = $eventos[$fecha] ?? [];
                        $clases = 'cal__dia';
                        if (substr($fecha, 0, 7) !== $mes) $clases .= ' cal__dia--fuera';
                        if ($fecha === $hoy) $clases .= ' cal__dia--hoy';
                        if ($delDia) $clases .= ' cal__dia--con-eventos';
                        $numero = (int)substr($fecha, 8, 2);
                        ?>
                        <td class="<?= $clases ?>">
                            <?php if ($delDia && substr($fecha, 0, 7) === $mes): ?>
                                <a class="cal__celda" href="#dia-<?= e($fecha) ?>" aria-label="<?= e(nombre_dia($fecha)) ?>: <?= count($delDia) ?> <?= count($delDia) === 1 ? 'novedad' : 'novedades' ?>">
                                    <span class="cal__num"><?= $numero ?></span>
                                    <span class="cal__marcas">
                                        <?php foreach ($delDia as $evento): ?>
                                            <span class="cal__marca cal__marca--<?= e($evento['tipo']) ?>"><span><?= e($evento['titulo']) ?></span></span>
                                        <?php endforeach; ?>
                                    </span>
                                </a>
                            <?php else: ?>
                                <div class="cal__celda"><span class="cal__num"><?= $numero ?></span></div>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <ul class="cal__leyenda" aria-label="Referencias">
        <?php foreach ($tiposEvento as $tipo => $nombreTipo): ?>
            <li class="cal__leyenda-item cal__leyenda-item--<?= $tipo ?>"><?= e($nombreTipo) ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<?php if ($mesesActivos): ?>
    <nav class="cal__meses" aria-label="Meses con novedades">
        <span class="cliente-etiqueta">Meses con novedades</span>
        <div class="cal__meses-lista">
            <?php foreach ($mesesActivos as $mesActivo => $cantidad): ?>
                <a class="cal__mes-chip<?= $mesActivo === $mes ? ' is-activo' : '' ?>" href="<?= e(url_seccion('calendario', ['mes' => $mesActivo])) ?>"<?= $mesActivo === $mes ? ' aria-current="true"' : '' ?>>
                    <?= e(nombre_mes_anio($mesActivo)) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
<?php endif; ?>

<section class="cliente-bloque" aria-labelledby="agenda-titulo">
    <header class="cliente-bloque__cabecera">
        <h2 id="agenda-titulo">Agenda de <?= e(NOMBRES_MES[(int)substr($mes, 5, 2)]) ?></h2>
    </header>
    <?php if (!$eventosDelMes): ?>
        <p class="cliente-nada">No hay novedades en este mes.</p>
    <?php else: ?>
        <ol class="agenda">
            <?php foreach ($eventosDelMes as $fecha => $delDia): ?>
                <li class="agenda__dia<?= $fecha === $hoy ? ' agenda__dia--hoy' : '' ?>" id="dia-<?= e($fecha) ?>">
                    <time class="agenda__fecha" datetime="<?= e($fecha) ?>">
                        <span class="agenda__numero"><?= (int)substr($fecha, 8, 2) ?></span>
                        <span class="agenda__semana"><?= e(NOMBRES_DIA[(int)(new DateTimeImmutable($fecha))->format('N')]) ?></span>
                    </time>
                    <ul class="cliente-eventos">
                        <?php foreach ($delDia as $evento): ?>
                            <li class="cliente-evento cliente-evento--<?= e($evento['tipo']) ?>">
                                <span class="cliente-evento__tipo"><?= e($tiposEvento[$evento['tipo']]) ?><?= $evento['hora'] ? ' · ' . e($evento['hora']) . ' hs' : '' ?></span>
                                <?php if ($evento['tipo'] === 'evento'): ?>
                                    <strong class="cliente-evento__titulo"><?= e($evento['titulo']) ?></strong>
                                <?php else: ?>
                                    <a href="<?= e(url_seccion($evento['seccion'], $evento['extra'], $evento['ancla'])) ?>"><?= e($evento['titulo']) ?></a>
                                <?php endif; ?>
                                <?php if ($evento['detalle']): ?><span class="cliente-evento__detalle"><?= e($evento['detalle']) ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</section>
