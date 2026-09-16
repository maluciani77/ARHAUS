<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $ubicacion = trim((string)($_POST['ubicacion'] ?? '')) ?: null;
        $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
        $arquitectoId = (int)($_POST['arquitecto_id'] ?? 0) ?: null;
        $estado = (string)($_POST['estado'] ?? 'en_curso');

        if ($nombre === '') {
            $error = 'La obra necesita un nombre.';
        } else {
            $stmt = db()->prepare(
                'INSERT INTO obras (nombre, ubicacion, cliente_id, arquitecto_id, estado) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$nombre, $ubicacion, $clienteId, $arquitectoId, $estado]);
            redirigir('obras.php?ok=1');
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM obras WHERE id = ?');
        $stmt->execute([$id]);

        $dirObra = __DIR__ . '/../../uploads/obras/' . $id;
        if (is_dir($dirObra)) {
            foreach (glob($dirObra . '/*') ?: [] as $archivo) {
                @unlink($archivo);
            }
            @rmdir($dirObra);
        }
        redirigir('obras.php');
    }
}

$exito = isset($_GET['ok']) ? 'Obra creada correctamente.' : null;

$obras = db()->query('
    SELECT o.*, c.nombre AS cliente_nombre, a.nombre AS arquitecto_nombre
    FROM obras o
    LEFT JOIN usuarios c ON c.id = o.cliente_id
    LEFT JOIN usuarios a ON a.id = o.arquitecto_id
    ORDER BY o.created_at DESC
')->fetchAll();

$clientes = db()->query("SELECT id, nombre FROM usuarios WHERE rol = 'cliente' ORDER BY nombre")->fetchAll();
$arquitectos = db()->query(
    // Los admin tambien pueden figurar como arquitecto de una obra: en un
    // estudio chico la misma persona dirige y administra. Van despues de
    // los arquitectos en la lista, y se muestran aclarando el rol.
    "SELECT id, nombre, rol FROM usuarios
     WHERE rol IN ('arquitecto', 'admin')
     ORDER BY (rol = 'admin'), nombre"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Obras - Panel admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e($raiz) ?>css/panel.css">
</head>
<body class="panel-body">
<?php $navLinks = [
    ['href' => 'index.php', 'texto' => '← Panel'],
    ['href' => 'usuarios.php', 'texto' => 'Usuarios'],
]; include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
    <h1>Obras</h1>
    <p class="panel-subtitle">Creá una obra y asignale un cliente y un arquitecto. Después entrá a "Gestionar" para cargar etapas y fotos.</p>

    <?php if ($exito): ?><p class="panel-alert panel-alert--ok"><?= e($exito) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <div class="panel-tabla-wrap">
        <table class="panel-tabla">
            <thead>
                <tr><th>Obra</th><th>Cliente</th><th>Arquitecto</th><th>Estado</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($obras as $obra): ?>
                    <tr>
                        <td><?= e($obra['nombre']) ?></td>
                        <td><?= e($obra['cliente_nombre'] ?? '—') ?></td>
                        <td><?= e($obra['arquitecto_nombre'] ?? '—') ?></td>
                        <td><span class="panel-tag panel-tag--rojo"><?= e(nombre_estado($obra['estado'])) ?></span></td>
                        <td style="white-space:nowrap;">
                            <a href="obra.php?id=<?= (int)$obra['id'] ?>">Gestionar</a>
                            ·
                            <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar la obra &quot;<?= e(addslashes($obra['nombre'])) ?>&quot;? Se borran también sus etapas y fotos.');">
                                <?= campo_csrf() ?>
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= (int)$obra['id'] ?>">
                                <button type="submit" style="background:none;border:none;color:#e21313;cursor:pointer;padding:0;font:inherit;">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Nueva obra</h2>
    <div class="panel-card">
        <form method="post" class="panel-form">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="crear">
            <label>Nombre
                <input type="text" name="nombre" placeholder="Ej: Alvear Chico" required>
            </label>
            <label>Ubicación
                <input type="text" name="ubicacion" placeholder="Ej: Tortuguitas, Buenos Aires">
            </label>
            <label>Cliente
                <select name="cliente_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Arquitecto
                <select name="arquitecto_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($arquitectos as $a): ?>
                        <option value="<?= (int)$a['id'] ?>"><?= e($a['nombre']) ?><?= $a['rol'] === 'admin' ? ' (admin)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Estado
                <select name="estado">
                    <option value="en_curso">En curso</option>
                    <option value="pausada">Pausada</option>
                    <option value="finalizada">Finalizada</option>
                </select>
            </label>
            <button type="submit">Crear obra</button>
        </form>
    </div>

    <?php if (!$clientes || !$arquitectos): ?>
        <p class="panel-hint" style="margin-top:14px;">
            <?php if (!$clientes): ?>Todavía no creaste ningún cliente. <?php endif; ?>
            <?php if (!$arquitectos): ?>Todavía no creaste ningún arquitecto. <?php endif; ?>
            Podés crearlos desde <a href="usuarios.php">Usuarios</a>.
        </p>
    <?php endif; ?>
</main>
</body>
</html>
