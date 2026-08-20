<?php
/**
 * Partial de la barra superior. Antes de incluir este archivo hay que
 * definir: $raiz (ruta relativa a la raíz del sitio, ej '../../'),
 * $usuario (array del usuario logueado) y opcionalmente $navLinks
 * (array de ['href' => ..., 'texto' => ...] específicos del panel).
 */
?>
<header class="panel-topbar">
    <div class="panel-topbar__brand">
        <img src="<?= e($raiz) ?>images/logo.svg" alt="ARHAUS">
        <span><?= e(nombre_rol($usuario['rol'])) ?></span>
    </div>
    <nav class="panel-topbar__nav">
        <?php foreach ($navLinks ?? [] as $link): ?>
            <a href="<?= e($link['href']) ?>"><?= e($link['texto']) ?></a>
        <?php endforeach; ?>
        <a href="<?= e($raiz) ?>index.html">Ver sitio</a>
        <span style="opacity:0.6;"><?= e($usuario['nombre']) ?></span>
        <a class="logout" href="<?= e($raiz) ?>logout.php">Salir</a>
    </nav>
</header>
