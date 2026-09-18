<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('login.html');
}

$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

$usuario = null;

if ($email !== '' && $password !== '') {
    $stmt = db()->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $fila = $stmt->fetch();

    if ($fila && password_verify($password, $fila['password_hash'])) {
        $usuario = $fila;
    }
}

if (!$usuario) {
    redirigir('login.html?error=1');
}

iniciar_login($usuario);

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
