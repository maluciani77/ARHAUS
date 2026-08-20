<?php
declare(strict_types=1);

/**
 * Puesta en marcha inicial: crea las tablas (si no existen) y te deja
 * crear el primer usuario Admin desde un formulario, sin contraseñas
 * hardcodeadas en el código.
 *
 * Por seguridad, una vez que ya existe un admin este script deja de
 * funcionar (no se puede crear otro admin desde acá). Cuando termines
 * de probar, podés borrar este archivo del todo.
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';

function crear_tablas(): void
{
    $archivo = db_driver() === 'sqlite' ? 'schema.sqlite.sql' : 'schema.mysql.sql';
    $sql = file_get_contents(__DIR__ . '/database/' . $archivo);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
        db()->exec($sentencia);
    }
}

function ya_existe_admin(): bool
{
    $stmt = db()->query("SELECT COUNT(*) AS n FROM usuarios WHERE rol = 'admin'");
    return (int)$stmt->fetch()['n'] > 0;
}

crear_tablas();

$error = null;
$exito = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !ya_existe_admin()) {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($nombre === '' || $email === '' || $password === '') {
        $error = 'Completá todos los campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email no es válido.';
    } elseif (strlen($password) < 8) {
        $error = 'La contraseña tiene que tener al menos 8 caracteres.';
    } else {
        $stmt = db()->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), 'admin']);
        $exito = true;
    }
}

$adminYaExiste = ya_existe_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Puesta en marcha - ARHAUS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="css/panel.css">
</head>
<body class="panel-body panel-body--centrado">
  <main class="panel-card panel-card--angosta">
    <h1>Puesta en marcha</h1>

    <?php if ($exito): ?>
      <p class="panel-alert panel-alert--ok">Cuenta admin creada. Ya podés <a href="login.html">iniciar sesión</a>.</p>
    <?php elseif ($adminYaExiste): ?>
      <p class="panel-alert">Ya existe un usuario admin, así que este formulario está deshabilitado. Iniciá sesión desde <a href="login.html">login.html</a>. Si querés crear más usuarios (arquitectos o clientes), hacelo desde el panel de admin una vez logueado.</p>
    <?php else: ?>
      <p class="panel-hint">Las tablas de la base ya están creadas. Creá acá tu primer usuario administrador.</p>
      <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>
      <form method="post" class="panel-form">
        <label>Nombre
          <input type="text" name="nombre" required>
        </label>
        <label>Email
          <input type="email" name="email" required>
        </label>
        <label>Contraseña (mínimo 8 caracteres)
          <input type="password" name="password" minlength="8" required>
        </label>
        <button type="submit">Crear cuenta admin</button>
      </form>
    <?php endif; ?>
  </main>
</body>
</html>
