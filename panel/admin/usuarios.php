<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');

$error = null;
$exito = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rol = (string)($_POST['rol'] ?? '');

        if ($nombre === '' || $email === '' || $password === '') {
            $error = 'Completá todos los campos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email no es válido.';
        } elseif (strlen($password) < 8) {
            $error = 'La contraseña tiene que tener al menos 8 caracteres.';
        } elseif (!in_array($rol, ['admin', 'arquitecto', 'director', 'cliente'], true)) {
            $error = 'Rol inválido.';
        } else {
            try {
                if ($rol === 'director') {
                    asegurar_rol_director();
                }
                $stmt = db()->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)');
                $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rol]);
                redirigir('usuarios.php?ok=1');
            } catch (PDOException $e) {
                $error = str_contains($e->getMessage(), 'UNIQUE')
                    ? 'Ya existe un usuario con ese email.'
                    : 'No se pudo crear el usuario.';
            }
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id !== (int)$usuario['id']) { // no te podés borrar a vos mismo
            $stmt = db()->prepare('DELETE FROM usuarios WHERE id = ?');
            $stmt->execute([$id]);
        }
        redirigir('usuarios.php');
    }
}

if (isset($_GET['ok'])) {
    $exito = 'Usuario creado correctamente.';
}

$usuarios = db()->query('SELECT * FROM usuarios ORDER BY rol, nombre')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Usuarios - Panel admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e(recurso($raiz, 'css/panel.css')) ?>">
</head>
<body class="panel-body">
<?php $navLinks = [
    ['href' => 'index.php', 'texto' => '← Panel'],
    ['href' => 'obras.php', 'texto' => 'Obras'],
]; include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
    <h1>Usuarios</h1>
    <p class="panel-subtitle">Clientes, arquitectos y directores de obra con acceso a los paneles. Las cuentas se crean acá — nadie se registra solo.</p>

    <?php if ($exito): ?><p class="panel-alert panel-alert--ok"><?= e($exito) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <div class="panel-tabla-wrap">
        <table class="panel-tabla">
            <thead>
                <tr><th>Nombre</th><th>Email</th><th>Rol</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= e($u['nombre']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="panel-tag"><?= e(nombre_rol($u['rol'])) ?></span></td>
                        <td>
                            <?php if ((int)$u['id'] !== (int)$usuario['id']): ?>
                                <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar a <?= e(addslashes($u['nombre'])) ?>? Esta acción no se puede deshacer.');">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="panel-btn panel-btn--peligro" style="padding:4px 10px;font-size:0.78rem;">Eliminar</button>
                                </form>
                            <?php else: ?>
                                <span class="panel-hint">(vos)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Nuevo usuario</h2>
    <div class="panel-card">
        <form method="post" class="panel-form">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="crear">
            <label>Nombre
                <input type="text" name="nombre" required>
            </label>
            <label>Email
                <input type="email" name="email" required>
            </label>
            <label>Contraseña (mínimo 8 caracteres)
                <input type="password" name="password" minlength="8" required>
            </label>
            <label>Rol
                <select name="rol" required>
                    <option value="cliente">Cliente</option>
                    <option value="arquitecto">Arquitecto</option>
                    <option value="director">Director de obra (solo ve la ejecución de obra)</option>
                    <option value="admin">Administrador</option>
                </select>
            </label>
            <button type="submit">Crear usuario</button>
        </form>
    </div>
</main>
</body>
</html>
