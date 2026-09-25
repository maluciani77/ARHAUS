<?php
declare(strict_types=1);

/**
 * Acá cae el botón "Entrar con Google" de login.html.
 *
 * Google manda el token directamente a esta dirección (por eso está
 * cargada como URI de redireccionamiento en la credencial), junto con
 * una cookie y un campo que tienen que coincidir. Esa coincidencia es la
 * que prueba que el envío salió del navegador de la persona y no de otro
 * sitio; Google lo llama double submit cookie.
 */

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/google.php';

/** Vuelve al login con un cartel. Los textos están en login.html. */
function volver_al_login(string $motivo): void
{
    redirigir('login.html?google=' . urlencode($motivo));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('login.html');
}

// La cookie y el campo los pone Google; si no coinciden, el envío no
// salió de la página de login.
$cookie = (string)($_COOKIE['g_csrf_token'] ?? '');
$campo = (string)($_POST['g_csrf_token'] ?? '');
if ($cookie === '' || $campo === '' || !hash_equals($cookie, $campo)) {
    volver_al_login('csrf');
}

$token = (string)($_POST['credential'] ?? '');
if ($token === '') {
    volver_al_login('sin-token');
}

$datos = google_consultar_token($token);
if ($datos === null) {
    volver_al_login('sin-google');
}

$revision = google_datos_validos($datos);
if (isset($revision['error'])) {
    volver_al_login('invalido');
}

$usuario = google_buscar_usuario($revision['email'], $revision['google_id']);
if (!$usuario) {
    // No se crean cuentas: las da de alta el estudio.
    volver_al_login('sin-cuenta');
}

google_enganchar((int)$usuario['id'], $revision['google_id']);
iniciar_login($usuario);

// Entrar con Google deja el acceso guardado sin preguntar: es lo que se
// espera de un botón así, y Google ya pidió permiso de su lado.
recordar_este_dispositivo((int)$usuario['id']);

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
