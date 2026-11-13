<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

// Si el admin estaba viendo el panel de otro, "Salir" lo devuelve a su
// cuenta en vez de cerrar todo: lo que quería era salir de ESA cuenta,
// no de la suya, y cerrarla entera obligaría a loguearse de nuevo.
if (suplantacion_activa()) {
    require_once __DIR__ . '/lib/suplantar.php';
    suplantar_terminar();
    $ahora = usuario_actual();
    redirigir($ahora ? 'panel/admin/index.php' : 'login.html');
}

cerrar_sesion();
redirigir('login.html');
