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
    <?php include __DIR__ . '/../_asistente_chat.php'; ?>
<?php endif; ?>
