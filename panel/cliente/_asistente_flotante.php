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
    <!-- El obrerito. Va dibujado acá y no en icono(), porque esa función
         arma íconos de un solo trazo y este lleva relleno y dos colores.
         Cada parte tiene su clase: el casco se levanta solo cada tanto y
         la cabeza pega el saltito, y eso se maneja desde el CSS. -->
    <svg class="obrerito" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
        <g class="obrerito__cuerpo">
            <circle class="obrerito__cabeza" cx="16" cy="20" r="7"/>
            <circle class="obrerito__ojo" cx="13.2" cy="19.4" r="1.05"/>
            <circle class="obrerito__ojo" cx="18.8" cy="19.4" r="1.05"/>
            <path class="obrerito__sonrisa" d="M13.1 22.4c1.7 1.7 4.1 1.7 5.8 0"/>
            <g class="obrerito__casco">
                <path class="obrerito__casco-copa" d="M7.6 15.1a8.4 8.4 0 0 1 16.8 0z"/>
                <rect class="obrerito__casco-ala" x="4.4" y="14.2" width="23.2" height="2.9" rx="1.45"/>
                <path class="obrerito__casco-cresta" d="M16 7.1v7.1"/>
            </g>
        </g>
    </svg>
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
