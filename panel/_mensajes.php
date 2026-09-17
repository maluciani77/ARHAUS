<?php
/**
 * Chat con el cliente, para admin y arquitecto. El cliente escribe desde
 * su panel y acá se le responde, en la misma conversación.
 *
 * Antes de incluirlo hay que tener definidos $obra, $mensajes (de
 * mensajes_de_obra()), $usuario y opcionalmente $errorMensaje.
 */
?>
<h2 id="mensajes">Mensajes</h2>
<p class="panel-subtitle">
    <?php if (!$obra['cliente_id']): ?>
        Esta obra todavía no tiene un cliente asignado, así que nadie va a ver lo que escribas acá.
    <?php else: ?>
        Lo que escribas lo ve el cliente de esta obra en su panel.
    <?php endif; ?>
</p>

<div class="panel-card">
    <?php if (!$mensajes): ?>
        <p class="panel-vacio">Todavía no hay mensajes.</p>
    <?php else: ?>
        <ul class="panel-chat">
            <?php foreach ($mensajes as $mensaje): ?>
                <?php $delEstudio = ($mensaje['autor_rol'] ?? '') !== 'cliente'; ?>
                <li class="panel-chat__mensaje <?= $delEstudio ? 'panel-chat__mensaje--estudio' : 'panel-chat__mensaje--cliente' ?>">
                    <div class="panel-chat__meta">
                        <strong><?= e($mensaje['autor_nombre'] ?? 'Usuario eliminado') ?></strong>
                        <span><?= e(fecha_mensaje($mensaje['created_at'])) ?></span>
                    </div>
                    <p><?= nl2br(e($mensaje['texto'])) ?></p>
                    <form method="post" action="#mensajes" onsubmit="return confirm('¿Eliminar este mensaje?');">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="eliminar_mensaje">
                        <input type="hidden" name="mensaje_id" value="<?= (int)$mensaje['id'] ?>">
                        <button type="submit" class="panel-btn panel-btn--peligro panel-btn--chico">Eliminar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!empty($errorMensaje)): ?>
        <p class="panel-alert panel-alert--error"><?= e($errorMensaje) ?></p>
    <?php endif; ?>

    <form method="post" action="#mensajes" class="panel-form">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="agregar_mensaje">
        <label>Responder
            <textarea name="texto" rows="3" maxlength="<?= MENSAJE_LARGO_MAXIMO ?>" required placeholder="Escribile al cliente..."></textarea>
        </label>
        <button type="submit">Enviar</button>
    </form>
</div>
