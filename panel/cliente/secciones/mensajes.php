<?php
/**
 * Sección Mensajes del panel del cliente: la conversación con el estudio
 * y el formulario para dejar un mensaje nuevo. Lo que se envía acá lo ve
 * el admin y el arquitecto en la página de la obra.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Mensajes</h1>
    <p class="cliente-cabecera__intro">Dejale una consulta al estudio. Te responden acá mismo.</p>
</header>

<?php if (!$mensajes): ?>
    <p class="cliente-nada">Todavía no hay mensajes. Escribí el primero.</p>
<?php else: ?>
    <ul class="book-chat">
        <?php foreach ($mensajes as $mensaje): ?>
            <?php $propio = (int)($mensaje['autor_id'] ?? 0) === (int)$usuario['id']; ?>
            <li class="book-chat__mensaje <?= $propio ? 'book-chat__mensaje--propio' : 'book-chat__mensaje--estudio' ?>">
                <div class="book-chat__meta">
                    <strong><?= $propio ? 'Vos' : e($mensaje['autor_nombre'] ?? 'Estudio ARHAUS') ?></strong>
                    <time datetime="<?= e((string)$mensaje['created_at']) ?>"><?= e(fecha_mensaje($mensaje['created_at'])) ?></time>
                </div>
                <p><?= nl2br(e($mensaje['texto'])) ?></p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($errorMensaje): ?>
    <p class="cliente-alerta"><?= e($errorMensaje) ?></p>
<?php endif; ?>

<form class="book-chat__form" method="post" action="<?= e(url_seccion('mensajes')) ?>">
    <?= campo_csrf() ?>
    <input type="hidden" name="accion" value="agregar_mensaje">
    <label class="book-chat__campo">
        <span class="cliente-etiqueta">Tu mensaje</span>
        <textarea name="texto" rows="3" maxlength="<?= MENSAJE_LARGO_MAXIMO ?>" required placeholder="Hola, quería consultarles por..."></textarea>
    </label>
    <button type="submit">Enviar mensaje</button>
</form>
