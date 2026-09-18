<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'director');
asegurar_rol_director();

$stmt = db()->prepare('SELECT * FROM obras WHERE director_id = ? ORDER BY created_at DESC');
$stmt->execute([$usuario['id']]);
$obras = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mis obras - ARHAUS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e($raiz) ?>css/panel.css">
</head>
<body class="panel-body">
<?php include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
    <h1>Mis obras</h1>
    <p class="panel-subtitle">Las obras que dirigís. Entrá a cada una para cargar el día a día, las fotos y la planificación.</p>

    <?php if (!$obras): ?>
        <p class="panel-vacio">Todavía no tenés ninguna obra asignada como director.</p>
    <?php else: ?>
        <div class="panel-grid">
            <?php foreach ($obras as $obra): ?>
                <a class="panel-obra-card" href="obra.php?id=<?= (int)$obra['id'] ?>">
                    <h3><?= e($obra['nombre']) ?></h3>
                    <p><?= e($obra['ubicacion'] ?? '') ?></p>
                    <span class="panel-tag panel-tag--rojo"><?= e(nombre_estado($obra['estado'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
