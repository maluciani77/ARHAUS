<?php
/**
 * Proyecto > 3D: la maqueta en grande, para girarla y mirarla de cerca.
 *
 * En Inicio la maqueta aparece chica, como una postal arriba de todo.
 * Acá ocupa casi toda la pantalla y el cliente la maneja: gira, acerca y
 * la mueve. Es la misma maqueta (models/obras/{id}.glb), no hay otro
 * archivo que mantener.
 *
 * La solapa no se muestra si la obra no tiene maqueta, así que llegar
 * acá sin archivo es raro; aun así se contempla.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

$maqueta = maqueta_de_obra((int)$obra['id']);
?>
<style>
    /* Todo lo de esta sección vive acá: así no hay que tocar cliente.css
       y los colores salen de las mismas variables, que es lo que hace que
       funcione igual en claro y en oscuro. */
    .m3d {
        position: relative;
        margin-top: 4px;
        border-radius: 14px;
        overflow: hidden;
        /* Un fondo con algo de profundidad: sobre un gris plano la maqueta
           flota y se ve recortada. */
        background: radial-gradient(ellipse at 50% 38%, #26262a 0%, #121214 72%);
    }

    .m3d__visor {
        display: block;
        width: 100%;
        /* Grande de verdad, que es el punto de esta sección, pero dejando
           ver que abajo siguen los botones. */
        height: clamp(380px, 68vh, 760px);
        background: transparent;
        --progress-bar-color: var(--acento, #880723);
        --progress-bar-height: 2px;
    }

    /* En pantalla completa el visor tiene que ocupar todo, no su alto fijo. */
    .m3d:fullscreen { border-radius: 0; }
    .m3d:fullscreen .m3d__visor { height: 100vh; }

    .m3d__ayuda {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 10px;
        margin: 0;
        padding: 0 16px;
        text-align: center;
        font-size: 0.74rem;
        letter-spacing: 0.02em;
        color: rgba(255, 255, 255, 0.5);
        pointer-events: none;
    }

    .m3d-acciones {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 14px;
    }

    .m3d-boton {
        padding: 9px 16px;
        border: 1px solid var(--linea);
        border-radius: var(--radio);
        background: var(--superficie);
        color: var(--texto);
        font: inherit;
        font-size: 0.9rem;
        cursor: pointer;
        /* WCAG 2.2: que se pueda tocar con el dedo sin errarle. */
        min-height: 40px;
    }

    .m3d-boton:hover { background: var(--hundido); }

    @media (max-width: 860px) {
        .m3d__visor { height: clamp(320px, 56vh, 520px); }
        .m3d-acciones { flex-direction: column; }
        .m3d-boton { width: 100%; }
    }
</style>

<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>3D</h1>
    <?php if ($maqueta): ?>
        <?php /* Corto a proposito: en el celular cada renglon de acá es
                 scroll antes de llegar a la maqueta, y como se maneja ya
                 lo dice el cartelito de abajo del visor. */ ?>
        <p class="cliente-cabecera__intro">Tu casa en tres dimensiones, para girarla y mirarla desde donde quieras.</p>
    <?php endif; ?>
</header>

<?php if (!$maqueta): ?>
    <p class="cliente-nada">Todavía no hay una maqueta 3D cargada para esta obra.</p>
<?php else: ?>
    <div class="m3d" id="m3d">
        <model-viewer
            class="m3d__visor"
            id="m3d-visor"
            src="<?= e(recurso($raiz, $maqueta)) ?>"
            alt="Maqueta 3D de <?= e($obra['nombre']) ?>"
            camera-controls
            touch-action="pan-y"
            interaction-prompt="none"
            auto-rotate
            rotation-per-second="10deg"
            auto-rotate-delay="4000"
            <?php /* El mismo encuadre y la misma luz que en Inicio, para que
                     sea claramente la misma maqueta y no otra cosa. */ ?>
            camera-orbit="auto 72deg 68%"
            field-of-view="30deg"
            shadow-intensity="1.4"
            shadow-softness="0.3"
            exposure="0.9"
            tone-mapping="aces"
            <?php /* OJO: sin recurso(). El visor elige como leer el archivo
                     mirando la terminacion, y con el ?v=fecha que agrega
                     recurso() deja de ver el .hdr, lo toma por un jpg y
                     falla: el modelo no llega a mostrarse nunca. */ ?>
            environment-image="<?= e($raiz . 'models/estudio.hdr') ?>"
            loading="eager"
            reveal="auto"></model-viewer>
        <p class="m3d__ayuda">Arrastrá para girar &middot; rueda o dos dedos para acercar</p>
    </div>

    <div class="m3d-acciones">
        <button type="button" class="m3d-boton" id="m3d-reiniciar">Volver a la vista inicial</button>
        <button type="button" class="m3d-boton" id="m3d-pantalla" hidden>Pantalla completa</button>
    </div>

    <script>
        (function () {
            var caja = document.getElementById('m3d');
            var visor = document.getElementById('m3d-visor');
            var reiniciar = document.getElementById('m3d-reiniciar');
            var pantalla = document.getElementById('m3d-pantalla');
            if (!caja || !visor) return;

            // La vista a la que vuelve el botón: la misma con la que arrancó.
            var ORBITA = visor.getAttribute('camera-orbit');
            var FOV = visor.getAttribute('field-of-view');

            reiniciar.addEventListener('click', function () {
                visor.cameraOrbit = ORBITA;
                visor.fieldOfView = FOV;
                visor.resetTurntableRotation();
                // Sin esto la cámara viaja sola hasta el destino, que queda
                // lindo; con jumpCameraToGoal iría de golpe. Se deja viajar.
            });

            // El botón de pantalla completa aparece solo si el navegador
            // lo soporta: en iPhone no existe para un div cualquiera.
            if (caja.requestFullscreen) {
                pantalla.hidden = false;
                pantalla.addEventListener('click', function () {
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    } else {
                        caja.requestFullscreen();
                    }
                });
                document.addEventListener('fullscreenchange', function () {
                    pantalla.textContent = document.fullscreenElement
                        ? 'Salir de pantalla completa'
                        : 'Pantalla completa';
                });
            }
        })();
    </script>
<?php endif; ?>
