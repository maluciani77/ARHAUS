<?php
/**
 * La barra que avisa que no estás en tu cuenta.
 *
 * Se incluye arriba de todo en cada panel. Si no hay suplantación en
 * curso no dibuja nada, así que se puede incluir sin condiciones.
 *
 * Antes de incluirlo hay que tener definido $raiz.
 *
 * Es a propósito que la barra sea fija, fuerte de color y diga el nombre
 * y el mail: el problema que se vino a resolver es justamente confundir
 * una cuenta con otra. Los estilos van acá adentro y no en un .css para
 * que no haya forma de que la barra aparezca sin ellos.
 */

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

$supActiva = suplantacion_activa();
if (!$supActiva) {
    return;
}

$supPerfil = usuario_actual() ?? [];
$supAlto = '46px';
?>
<style>
    :root { --sup-alto: <?= $supAlto ?>; }

    body { padding-top: var(--sup-alto); }

    .suplantando {
        position: fixed;
        inset: 0 0 auto 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px 18px;
        flex-wrap: wrap;
        min-height: var(--sup-alto);
        padding: 6px 16px;
        box-sizing: border-box;
        background: #880723;
        color: #f7f3ea;
        font-family: "Work Sans", system-ui, sans-serif;
        font-size: 0.86rem;
        line-height: 1.35;
        text-align: center;
    }

    .suplantando__texto { margin: 0; }
    .suplantando__quien { font-weight: 600; }
    .suplantando__modo { opacity: 0.75; }

    .suplantando__volver {
        display: inline-flex;
        align-items: center;
        min-height: 32px;
        padding: 4px 14px;
        border: 1px solid rgba(247, 243, 234, 0.55);
        border-radius: 3px;
        background: transparent;
        color: inherit;
        font: inherit;
        cursor: pointer;
    }

    .suplantando__volver:hover,
    .suplantando__volver:focus-visible { background: rgba(247, 243, 234, 0.16); }

    /* El panel del cliente tiene la barra lateral pegada arriba: hay que
       bajarla para que no se meta abajo de esta. Debajo de 860px esa
       lateral se vuelve la barra de abajo, así que no se toca. */
    @media (min-width: 861px) {
        .cliente-app { min-height: calc(100vh - var(--sup-alto)); }
        .cliente-lateral {
            top: var(--sup-alto);
            height: calc(100vh - var(--sup-alto));
        }
    }

    /* En pantallas angostas el texto se parte en dos o tres renglones y
       la barra crece. El alto real lo mide el script de abajo; esto es
       el colchón para el instante antes de que corra, y para si el
       navegador tiene el JavaScript apagado. */
    @media (max-width: 860px) {
        :root { --sup-alto: 92px; }
    }

    /* El mail es lo más largo: en un teléfono chico sobra con el nombre. */
    @media (max-width: 560px) {
        .suplantando__mail { display: none; }
    }

    @media print { .suplantando { position: static; } }
</style>

<div class="suplantando" role="status">
    <p class="suplantando__texto">
        Estás viendo el panel de
        <span class="suplantando__quien"><?= e((string)($supPerfil['nombre'] ?? '')) ?></span><?php
        if (!empty($supPerfil['email'])): ?><span class="suplantando__mail"> &middot; <?= e((string)$supPerfil['email']) ?></span><?php endif; ?>
        <span class="suplantando__modo">(solo lectura)</span>
    </p>
    <form method="post" action="<?= e($raiz) ?>panel/suplantar.php">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="volver">
        <button type="submit" class="suplantando__volver">
            Volver a mi cuenta<?= !empty($supActiva['admin']['nombre'])
                ? ' (' . e((string)$supActiva['admin']['nombre']) . ')' : '' ?>
        </button>
    </form>
</div>
<script>
    // El alto de la barra depende de cuánto ocupe el nombre: en un
    // teléfono se parte en varios renglones. Se mide y se le avisa al
    // CSS, así el contenido de abajo nunca queda tapado.
    (function () {
        var barra = document.querySelector('.suplantando');
        if (!barra) { return; }
        var medir = function () {
            document.documentElement.style.setProperty('--sup-alto', barra.offsetHeight + 'px');
        };
        medir();
        window.addEventListener('resize', medir);
        if (document.fonts && document.fonts.ready) {
            // La tipografía llega después y puede cambiar el alto.
            document.fonts.ready.then(medir);
        }
    })();
</script>
