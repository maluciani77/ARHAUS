<?php
declare(strict_types=1);

/**
 * Entrar al panel de otro usuario, y volver.
 *
 * Es la única página que puede recibir un POST mientras hay una
 * suplantación en curso: si no, no habría forma de cerrarla. Por eso
 * declara SUPLANTAR_ESCRITURA_PERMITIDA antes de cargar auth.php, y por
 * eso no hace nada más que esto.
 */

define('SUPLANTAR_ESCRITURA_PERMITIDA', true);

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/suplantar.php';

$raiz = '../';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir($raiz . 'panel/admin/index.php');
}

verificar_csrf();

$usuario = requerir_login($raiz);
$accion = $_POST['accion'] ?? '';

/** A dónde va cada rol cuando entra a su panel. */
function suplantar_destino(string $raiz, string $rol): string
{
    return $raiz . match ($rol) {
        'cliente' => 'panel/cliente/index.php',
        'arquitecto' => 'panel/arquitecto/index.php',
        'director' => 'panel/director/index.php',
        default => 'panel/admin/index.php',
    };
}

if ($accion === 'volver') {
    suplantar_terminar();
    // Si la vuelta falla porque la cuenta del admin ya no existe,
    // suplantar_terminar() cierra la sesión: el login es el lugar
    // correcto para caer.
    $ahora = usuario_actual();
    redirigir($ahora ? suplantar_destino($raiz, (string)$ahora['rol']) : $raiz . 'login.html');
}

// Entrar a una cuenta: solo un admin, y solo desde un formulario del
// panel del estudio.
$usuario = requerir_rol($raiz, 'admin');
$destinoId = (int)($_POST['usuario_id'] ?? 0);

$motivo = suplantar_iniciar($usuario, $destinoId);

if ($motivo !== null) {
    http_response_code(403);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8">'
        . '<body style="font-family:system-ui,sans-serif;max-width:34rem;margin:15vh auto;padding:0 1.5rem;color:#2b2b2b">'
        . '<h1 style="font-size:1.3rem;font-weight:500">No se pudo entrar</h1>'
        . '<p>' . e($motivo) . '</p>'
        . '<p><a href="' . e($raiz) . 'panel/admin/usuarios.php">Volver a Usuarios</a></p>'
        . '</body></html>';
    exit;
}

$ahora = usuario_actual();
redirigir(suplantar_destino($raiz, (string)($ahora['rol'] ?? 'cliente')));
