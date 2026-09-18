<?php
/**
 * Sección Mi cuenta del panel del cliente: la foto de perfil y el cambio
 * de contraseña. Funciona aunque todavía no tenga una obra asignada.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($perfil)) {
    http_response_code(404);
    exit;
}

$accionEnviada = (string)($_POST['accion'] ?? '');
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($perfil['email']) ?></p>
    <h1>Mi cuenta</h1>
    <p class="cliente-cabecera__intro">Tu foto y tu contraseña para entrar al panel.</p>
</header>

<?php if ($avisoCuenta): ?>
    <p class="cliente-aviso" role="status"><?= e($avisoCuenta) ?></p>
<?php endif; ?>

<section class="cliente-bloque" aria-labelledby="titulo-foto">
    <h2 id="titulo-foto">Foto de perfil</h2>

    <?php if ($errorCuenta && in_array($accionEnviada, ['subir_foto_perfil', 'quitar_foto_perfil'], true)): ?>
        <p class="cliente-alerta"><?= e($errorCuenta) ?></p>
    <?php endif; ?>

    <div class="cliente-perfil">
        <?= avatar($raiz, $perfil, 'cliente-avatar--grande') ?>

        <div class="cliente-perfil__acciones">
            <form method="post" action="<?= e(url_seccion('cuenta')) ?>" enctype="multipart/form-data" class="cliente-form">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="subir_foto_perfil">
                <label class="cliente-campo">
                    <span class="cliente-etiqueta"><?= ruta_foto_perfil($perfil) ? 'Cambiar la foto' : 'Elegí una foto' ?></span>
                    <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required>
                </label>
                <p class="cliente-ayuda">JPG, PNG o WEBP, hasta 8 MB. Se recorta cuadrada.</p>
                <button type="submit" class="cliente-boton">Guardar foto</button>
            </form>

            <?php if (ruta_foto_perfil($perfil)): ?>
                <form method="post" action="<?= e(url_seccion('cuenta')) ?>" onsubmit="return confirm('¿Sacar tu foto de perfil?');">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="quitar_foto_perfil">
                    <button type="submit" class="cliente-boton cliente-boton--secundario">Sacar la foto</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="cliente-bloque" aria-labelledby="titulo-contrasena">
    <h2 id="titulo-contrasena">Contraseña</h2>

    <?php if ($errorCuenta && $accionEnviada === 'cambiar_contrasena'): ?>
        <p class="cliente-alerta"><?= e($errorCuenta) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url_seccion('cuenta')) ?>" class="cliente-form cliente-form--angosto">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="cambiar_contrasena">
        <label class="cliente-campo">
            <span class="cliente-etiqueta">Contraseña actual</span>
            <input type="password" name="actual" required autocomplete="current-password">
        </label>
        <label class="cliente-campo">
            <span class="cliente-etiqueta">Contraseña nueva</span>
            <input type="password" name="nueva" required minlength="<?= CONTRASENA_LARGO_MINIMO ?>" autocomplete="new-password">
        </label>
        <label class="cliente-campo">
            <span class="cliente-etiqueta">Repetí la contraseña nueva</span>
            <input type="password" name="repetida" required minlength="<?= CONTRASENA_LARGO_MINIMO ?>" autocomplete="new-password">
        </label>
        <p class="cliente-ayuda">Mínimo <?= CONTRASENA_LARGO_MINIMO ?> caracteres.</p>
        <button type="submit" class="cliente-boton">Cambiar contraseña</button>
    </form>
</section>
