<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/uploads.php';
require_once __DIR__ . '/../../lib/presupuestos.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'cliente');

$stmt = db()->prepare('SELECT * FROM obras WHERE cliente_id = ? LIMIT 1');
$stmt->execute([$usuario['id']]);
$obra = $stmt->fetch();

$etapas = [];
$fotos = [];
$presupuestos = [];
$puedeEditar = false;

if ($obra) {
    $stmtEtapas = db()->prepare('SELECT * FROM etapas WHERE obra_id = ? ORDER BY orden ASC, fecha ASC');
    $stmtEtapas->execute([$obra['id']]);
    $etapas = $stmtEtapas->fetchAll();

    $stmtFotos = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? ORDER BY created_at DESC');
    $stmtFotos->execute([$obra['id']]);
    $fotos = $stmtFotos->fetchAll();

    $presupuestos = presupuestos_de_obra((int)$obra['id']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Mi obra - ARHAUS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e($raiz) ?>css/panel.css">
</head>
<body class="panel-body">
<?php include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
<?php if (!$obra): ?>
    <h1>Todavía no tenés una obra asignada</h1>
    <p class="panel-subtitle">Cuando el estudio vincule tu cuenta a un proyecto, lo vas a ver acá.</p>
<?php else: ?>
    <h1><?= e($obra['nombre']) ?></h1>
    <p class="panel-subtitle">
        <?= e($obra['ubicacion'] ?? '') ?>
        · <span class="panel-tag panel-tag--rojo"><?= e(nombre_estado($obra['estado'])) ?></span>
    </p>

    <h2>Avance de obra</h2>
    <?php if (!$etapas): ?>
        <p class="panel-vacio">Todavía no hay etapas cargadas.</p>
    <?php else: ?>
        <ul class="panel-timeline">
            <?php foreach ($etapas as $etapa): ?>
                <li>
                    <h4><?= e($etapa['nombre']) ?></h4>
                    <?php if ($etapa['fecha']): ?><time><?= e(formatear_fecha($etapa['fecha'])) ?></time><?php endif; ?>
                    <?php if ($etapa['descripcion']): ?><p><?= nl2br(e($etapa['descripcion'])) ?></p><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php include __DIR__ . '/../_presupuestos.php'; ?>

    <h2>Fotos</h2>
    <?php if (!$fotos): ?>
        <p class="panel-vacio">Todavía no hay fotos subidas.</p>
    <?php else: ?>
        <div class="panel-galeria">
            <?php foreach ($fotos as $foto): ?>
                <a class="panel-foto" href="<?= e($raiz . ruta_publica_foto((int)$obra['id'], $foto['archivo'])) ?>" target="_blank" style="display:block;">
                    <img src="<?= e($raiz . ruta_publica_foto((int)$obra['id'], $foto['archivo'])) ?>" alt="<?= e($foto['descripcion'] ?? 'Foto de avance') ?>" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
