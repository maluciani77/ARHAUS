<?php
/**
 * El asistente como cajón flotante: un botón abajo a la derecha y, al
 * tocarlo, un panel que se abre a un costado SIN irse de la página. Así
 * se puede seguir mirando la obra mientras se pregunta.
 *
 * El botón es un enlace de verdad a la página del asistente: quien no
 * tenga JavaScript llega igual al chat. Con JavaScript, js/asistente.js
 * lo convierte en el interruptor del cajón.
 *
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra, $perfil)) {
    http_response_code(404);
    exit;
}

$asistenteDisponible = asistente_disponible();
?>
<a class="cliente-asistente-flotante" href="<?= e(url_seccion('asistente')) ?>"
   data-asistente-abrir aria-expanded="false" aria-controls="asistente-cajon">
    <?= icono('asistente') ?>
    <span>Asistente</span>
</a>

<aside class="asistente-cajon" id="asistente-cajon" data-asistente-cajon aria-label="Asistente de la obra" hidden>
    <header class="asistente-cajon__barra">
        <div>
            <h2 class="asistente-cajon__titulo">Asistente</h2>
            <p class="asistente-cajon__obra"><?= e($obra['nombre']) ?></p>
        </div>
        <button type="button" class="asistente-cajon__cerrar" data-asistente-cerrar aria-label="Cerrar el asistente">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </header>

    <div class="asistente-cajon__cuerpo">
        <?php if (!$asistenteDisponible): ?>
            <p class="cliente-nada">El asistente todavía no está activado. Mientras tanto, podés escribirle al estudio desde <a href="<?= e(url_seccion('mensajes')) ?>">Mensajes</a>.</p>
        <?php else: ?>
            <?php include __DIR__ . '/_asistente_chat.php'; ?>
        <?php endif; ?>
    </div>
</aside>
