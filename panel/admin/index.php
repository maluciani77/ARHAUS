<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');

$obras = db()->query('
    SELECT o.*, c.nombre AS cliente_nombre, a.nombre AS arquitecto_nombre
    FROM obras o
    LEFT JOIN usuarios c ON c.id = o.cliente_id
    LEFT JOIN usuarios a ON a.id = o.arquitecto_id
    ORDER BY o.created_at DESC
')->fetchAll();

$totales = db()->query("
    SELECT
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente') AS clientes,
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'arquitecto') AS arquitectos,
        (SELECT COUNT(*) FROM obras) AS obras
")->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel admin - ARHAUS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e($raiz) ?>css/panel.css">
</head>
<body class="panel-body">
<?php $navLinks = [
    ['href' => 'usuarios.php', 'texto' => 'Usuarios'],
    ['href' => 'obras.php', 'texto' => 'Obras'],
]; include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
    <h1>Panel de administración</h1>
    <p class="panel-subtitle">
        <?= (int)$totales['obras'] ?> obras · <?= (int)$totales['clientes'] ?> clientes · <?= (int)$totales['arquitectos'] ?> arquitectos
    </p>

    <div class="panel-actions">
        <a class="panel-btn" href="obras.php">+ Nueva obra</a>
        <a class="panel-btn panel-btn--secundario" href="usuarios.php">+ Nuevo usuario</a>
    </div>

    <h2>Obras</h2>
    <?php if (!$obras): ?>
        <p class="panel-vacio">Todavía no hay obras cargadas.</p>
    <?php else: ?>
        <div class="panel-tabla-wrap">
            <table class="panel-tabla">
                <thead>
                    <tr>
                        <th>Obra</th>
                        <th>Cliente</th>
                        <th>Arquitecto</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($obras as $obra): ?>
                        <tr>
                            <td><?= e($obra['nombre']) ?></td>
                            <td><?= e($obra['cliente_nombre'] ?? '—') ?></td>
                            <td><?= e($obra['arquitecto_nombre'] ?? '—') ?></td>
                            <td><span class="panel-tag panel-tag--rojo"><?= e(nombre_estado($obra['estado'])) ?></span></td>
                            <td><a href="obra.php?id=<?= (int)$obra['id'] ?>">Gestionar →</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
