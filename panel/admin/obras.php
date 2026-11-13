<?php
declare(strict_types=1);

/**
 * Obras se juntó con la portada: eran dos pantallas que hacían casi lo
 * mismo. Esto queda para que no se rompan los enlaces viejos ni los
 * favoritos del navegador.
 */

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';

requerir_rol('../../', 'admin');
redirigir('index.php');
