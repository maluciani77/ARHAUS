<?php
/**
 * Sección Asistente del panel del cliente: un chat con Claude que solo
 * sabe de esta obra y del panel. Sin JavaScript funciona igual, con un
 * formulario común; js/asistente.js lo hace sin recargar la página.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$disponible = asistente_disponible();
$sugerencias = [
    '¿En qué etapa está mi obra?',
    '¿Qué pasó en la obra últimamente?',
    '¿Qué es un Gantt y dónde lo veo?',
    '¿Dónde encuentro los planos?',
];
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Asistente</h1>
    <p class="cliente-cabecera__intro">
        Preguntale lo que quieras sobre tu obra o sobre el panel. Responde con lo que el estudio cargó; para decisiones o consultas puntuales, escribile al estudio en <a href="<?= e(url_seccion('mensajes')) ?>">Mensajes</a>.
    </p>
</header>

<?php if (!$disponible): ?>
    <p class="cliente-nada">El asistente todavía no está activado. Mientras tanto, podés escribirle al estudio desde <a href="<?= e(url_seccion('mensajes')) ?>">Mensajes</a>.</p>
<?php else: ?>
    <div class="asistente" data-asistente>
        <ul class="asistente__conversacion" data-conversacion aria-live="polite">
            <?php if (!$conversacion): ?>
                <li class="asistente__mensaje asistente__mensaje--asistente">
                    <p>Hola, <?= e(nombre_de_pila((string)$perfil['nombre'])) ?>. Soy el asistente de tu obra. ¿Qué querés saber?</p>
                </li>
            <?php endif; ?>
            <?php foreach ($conversacion as $i => $turno): ?>
                <?php $esUltimo = $i === array_key_last($conversacion); ?>
                <li class="asistente__mensaje asistente__mensaje--<?= $turno['rol'] === 'assistant' ? 'asistente' : 'cliente' ?>"<?= $esUltimo ? ' id="ultima"' : '' ?>>
                    <p><?= nl2br(e($turno['texto'])) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$conversacion): ?>
            <div class="asistente__sugerencias" data-sugerencias>
                <?php foreach ($sugerencias as $sugerencia): ?>
                    <button type="button" class="asistente__sugerencia" data-sugerencia><?= e($sugerencia) ?></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="cliente-alerta" data-error<?= $errorAsistente ? '' : ' hidden' ?>><?= e((string)$errorAsistente) ?></p>

        <form class="asistente__form" method="post" action="<?= e(url_seccion('asistente')) ?>" data-formulario>
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="preguntar_asistente">
            <label class="visually-hidden" for="pregunta-asistente">Tu pregunta</label>
            <textarea id="pregunta-asistente" name="pregunta" rows="2" maxlength="<?= ASISTENTE_LARGO_MAXIMO ?>" required placeholder="Escribí tu pregunta..."><?= e((string)($_POST['pregunta'] ?? '')) ?></textarea>
            <button type="submit" class="cliente-boton" data-enviar>Preguntar</button>
        </form>

        <?php if ($conversacion): ?>
            <form method="post" action="<?= e(url_seccion('asistente')) ?>" class="asistente__borrar" onsubmit="return confirm('¿Borrar toda la conversación con el asistente?');">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="borrar_asistente">
                <button type="submit" class="cliente-boton cliente-boton--texto">Empezar de nuevo</button>
            </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
