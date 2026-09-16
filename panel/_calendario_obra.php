<?php
/**
 * Calendario de la obra para admin y arquitecto: las fechas que ve el
 * cliente (próximas y pasadas) y el formulario para agregar eventos
 * propios, como visitas o reuniones. Antes de incluirlo hay que tener
 * definidos $calendario (de eventos_de_obra()), $hoy y opcionalmente
 * $errorEvento.
 */

// Si el formulario volvió con error, no perder lo que ya se escribió.
$reenvioEvento = ($_POST['accion'] ?? '') === 'agregar_evento' ? $_POST : [];
$valorEvento = function (string $campo, string $defecto = '') use ($reenvioEvento): string {
    return (string)($reenvioEvento[$campo] ?? $defecto);
};

$tiposCalendario = ['etapa' => 'Etapa', 'evento' => 'Evento', 'presupuesto' => 'Presupuesto', 'fotos' => 'Fotos'];

// Próximas: lo que todavía no pasó. Los días con fotos siempre son pasado.
$proximas = [];
$pasadas = [];
foreach ($calendario as $fecha => $delDia) {
    foreach ($delDia as $item) {
        $item['fecha'] = $fecha;
        if ($fecha >= $hoy && $item['tipo'] !== 'fotos') {
            $proximas[] = $item;
        } else {
            $pasadas[] = $item;
        }
    }
}
$pasadas = array_reverse($pasadas);

$filaAgenda = function (array $item) use ($tiposCalendario): void {
    ?>
    <li class="panel-agenda__item panel-agenda__item--<?= e($item['tipo']) ?>">
        <div class="panel-agenda__fecha">
            <?= e(formatear_fecha($item['fecha'])) ?>
            <?php if ($item['hora']): ?><span><?= e($item['hora']) ?> hs</span><?php endif; ?>
        </div>
        <div class="panel-agenda__cuerpo">
            <span class="panel-agenda__tipo"><?= e($tiposCalendario[$item['tipo']]) ?></span>
            <strong><?= e($item['titulo']) ?></strong>
            <?php if ($item['detalle']): ?><p><?= nl2br(e($item['detalle'])) ?></p><?php endif; ?>
        </div>
        <?php if ($item['tipo'] === 'evento'): ?>
            <form method="post" action="#calendario" onsubmit="return confirm('¿Eliminar este evento del calendario?');">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="eliminar_evento">
                <input type="hidden" name="evento_id" value="<?= (int)$item['id'] ?>">
                <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar</button>
            </form>
        <?php endif; ?>
    </li>
    <?php
};
?>
<h2 id="calendario">Calendario</h2>
<p class="panel-hint">El cliente ve en su calendario los eventos que cargues acá, junto con las fechas de las etapas, los presupuestos y los días en que se subieron fotos.</p>

<h3 class="panel-agenda__titulo">Próximas fechas</h3>
<?php if ($proximas): ?>
    <ul class="panel-agenda">
        <?php foreach ($proximas as $item) { $filaAgenda($item); } ?>
    </ul>
<?php else: ?>
    <p class="panel-vacio">No hay fechas próximas.</p>
<?php endif; ?>

<?php if ($pasadas): ?>
    <details class="panel-agenda__pasadas">
        <summary>Fechas pasadas (<?= count($pasadas) ?>)</summary>
        <ul class="panel-agenda">
            <?php foreach ($pasadas as $item) { $filaAgenda($item); } ?>
        </ul>
    </details>
<?php endif; ?>

<?php if (!empty($errorEvento)): ?><p class="panel-alert panel-alert--error"><?= e($errorEvento) ?></p><?php endif; ?>
<div class="panel-card">
    <form method="post" class="panel-form" action="#calendario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_evento">
        <label>Título
            <input type="text" name="titulo" maxlength="190" placeholder="Ej: Visita a obra con el cliente" value="<?= e($valorEvento('titulo')) ?>" required>
        </label>
        <div class="panel-form__fila panel-form__fila--dos">
            <label>Fecha
                <input type="date" name="fecha" value="<?= e($valorEvento('fecha')) ?>" required>
            </label>
            <label>Hora (opcional)
                <input type="time" name="hora" value="<?= e($valorEvento('hora')) ?>">
            </label>
        </div>
        <label>Detalle (opcional)
            <textarea name="detalle" rows="2" placeholder="Dónde, con quién, qué hay que llevar..."><?= e($valorEvento('detalle')) ?></textarea>
        </label>
        <button type="submit">Agregar al calendario</button>
    </form>
</div>
