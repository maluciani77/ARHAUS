<?php
declare(strict_types=1);

/**
 * LA PUERTA DE LA TARJETA NFC
 *
 * Esta es la dirección que va grabada en el chip de la tarjeta:
 *   https://www.arhaus.com.ar/acceso.php?t=selector:validador
 *
 * El cliente apoya la tarjeta en el teléfono, el teléfono abre esta
 * página, y de acá sale ya adentro de su panel. No hay nada que leer ni
 * que tocar: si el token sirve, esto redirige y listo.
 *
 * Los accesos se arman y se dan de baja desde panel/admin/accesos.php.
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/acceso_directo.php';

// El token viaja en la dirección, así que esta página no tiene que
// filtrarlo. Sin Referer no se lo lleva ninguna otra, y sin caché no
// queda guardado en el teléfono ni en el CDN.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

$usuario = usar_acceso_directo((string)($_GET['t'] ?? ''));

if (!$usuario) {
    // Puede ser una tarjeta dada de baja, vencida, o un link inventado.
    // No se dice cuál: desde afuera son todos lo mismo.
    redirigir('login.html?error=nfc');
}

iniciar_login($usuario);

// El que apoya la tarjeta casi siempre es el dueño, en su propio
// teléfono. Dejarle el "recordame" puesto hace que la segunda vez entre
// aunque no tenga la tarjeta encima. Se corta solo con Salir.
recordar_este_dispositivo((int)$usuario['id']);

// El redirect es además lo que saca el token de la barra de direcciones:
// la página a la que llega ya no lo tiene.
switch ($usuario['rol']) {
    case 'admin':
        redirigir('panel/admin/index.php');
    case 'arquitecto':
        redirigir('panel/arquitecto/index.php');
    case 'director':
        redirigir('panel/director/index.php');
    default:
        redirigir('panel/cliente/index.php');
}
