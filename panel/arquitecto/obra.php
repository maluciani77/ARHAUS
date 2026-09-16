<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/uploads.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'arquitecto', 'admin');

$obraId = (int)($_GET['id'] ?? $_POST['obra_id'] ?? 0);

function obtener_obra_propia(int $obraId, int $arquitectoId): ?array
{
    $stmt = db()->prepare('SELECT * FROM obras WHERE id = ? AND arquitecto_id = ? LIMIT 1');
    $stmt->execute([$obraId, $arquitectoId]);
    $fila = $stmt->fetch();
    return $fila ?: null;
}

$obra = obtener_obra_propia($obraId, (int)$usuario['id']);
if (!$obra) {
    http_response_code(404);
    echo 'Obra no encontrada.';
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'agregar_etapa') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $fecha = trim((string)($_POST['fecha'] ?? '')) ?: null;
        $descripcion = trim((string)($_POST['descripcion'] ?? '')) ?: null;

        if ($nombre === '') {
            $error = 'La etapa necesita un nombre.';
        } else {
            $orden = (int)db()->query('SELECT COALESCE(MAX(orden), 0) AS m FROM etapas WHERE obra_id = ' . (int)$obra['id'])->fetch()['m'] + 1;
            $stmt = db()->prepare('INSERT INTO etapas (obra_id, nombre, orden, fecha, descripcion) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$obra['id'], $nombre, $orden, $fecha, $descripcion]);
            redirigir('obra.php?id=' . $obra['id']);
        }
    } elseif ($accion === 'eliminar_etapa') {
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $stmt = db()->prepare('DELETE FROM etapas WHERE id = ? AND obra_id = ?');
        $stmt->execute([$etapaId, $obra['id']]);
        redirigir('obra.php?id=' . $obra['id']);
    } elseif ($accion === 'subir_foto') {
        $resultado = subir_fotos_obra(
            (int)$obra['id'],
            $_FILES['fotos'] ?? [],
            (string)($_POST['etapa'] ?? ''),
            trim((string)($_POST['descripcion'] ?? '')) ?: null,
            (int)$usuario['id']
        );

        // El script de subida manda las fotos de a una y espera JSON.
        if (es_pedido_fetch()) {
            responder_json($resultado, $resultado['subidas'] > 0 ? 200 : 422);
        }

        if (!$resultado['errores']) {
            redirigir('obra.php?id=' . $obra['id']);
        }
        $error = ($resultado['subidas'] > 0
                ? 'Se subieron ' . $resultado['subidas'] . ' fotos, pero otras fallaron: '
                : '')
            . implode(' · ', $resultado['errores']);
    } elseif ($accion === 'eliminar_foto') {
        $fotoId = (int)($_POST['foto_id'] ?? 0);
        $stmt = db()->prepare('SELECT * FROM fotos WHERE id = ? AND obra_id = ?');
        $stmt->execute([$fotoId, $obra['id']]);
        $foto = $stmt->fetch();
        if ($foto) {
            $ruta = __DIR__ . '/../../uploads/obras/' . $obra['id'] . '/' . $foto['archivo'];
            if (is_file($ruta)) {
                unlink($ruta);
            }
            $del = db()->prepare('DELETE FROM fotos WHERE id = ?');
            $del->execute([$fotoId]);
        }
        redirigir('obra.php?id=' . $obra['id']);
    }
}

$stmtEtapas = db()->prepare('SELECT * FROM etapas WHERE obra_id = ? ORDER BY orden ASC, fecha ASC');
$stmtEtapas->execute([$obra['id']]);
$etapas = $stmtEtapas->fetchAll();

$stmtFotos = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? ORDER BY created_at DESC');
$stmtFotos->execute([$obra['id']]);
$fotos = $stmtFotos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($obra['nombre']) ?> - ARHAUS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e($raiz) ?>css/panel.css">
</head>
<body class="panel-body">
<?php $navLinks = [['href' => 'index.php', 'texto' => '← Mis obras']]; include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
    <h1><?= e($obra['nombre']) ?></h1>
    <p class="panel-subtitle">
        <?= e($obra['ubicacion'] ?? '') ?>
        · <span class="panel-tag panel-tag--rojo"><?= e(nombre_estado($obra['estado'])) ?></span>
    </p>

    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <h2>Etapas</h2>
    <?php if ($etapas): ?>
        <ul class="panel-timeline">
            <?php foreach ($etapas as $etapa): ?>
                <li>
                    <h4><?= e($etapa['nombre']) ?></h4>
                    <?php if ($etapa['fecha']): ?><time><?= e(formatear_fecha($etapa['fecha'])) ?></time><?php endif; ?>
                    <?php if ($etapa['descripcion']): ?><p><?= nl2br(e($etapa['descripcion'])) ?></p><?php endif; ?>
                    <form method="post" style="margin-top:6px;" onsubmit="return confirm('¿Eliminar esta etapa?');">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="eliminar_etapa">
                        <input type="hidden" name="etapa_id" value="<?= (int)$etapa['id'] ?>">
                        <button type="submit" class="panel-btn panel-btn--peligro" style="padding:4px 10px;font-size:0.75rem;">Eliminar</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="panel-vacio">Todavía no cargaste etapas.</p>
    <?php endif; ?>

    <div class="panel-card">
        <form method="post" class="panel-form">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="agregar_etapa">
            <label>Nombre de la etapa
                <input type="text" name="nombre" placeholder="Ej: Cimientos" required>
            </label>
            <label>Fecha (opcional)
                <input type="date" name="fecha">
            </label>
            <label>Descripción (opcional)
                <textarea name="descripcion" rows="2"></textarea>
            </label>
            <button type="submit">Agregar etapa</button>
        </form>
    </div>

    <h2>Fotos</h2>
    <?php if ($fotos): ?>
        <div class="panel-galeria">
            <?php foreach ($fotos as $foto): ?>
                <div class="panel-foto">
                    <img src="<?= e($raiz . ruta_publica_foto((int)$obra['id'], $foto['archivo'])) ?>" alt="<?= e($foto['descripcion'] ?? 'Foto de avance') ?>" loading="lazy">
                    <form method="post" onsubmit="return confirm('¿Eliminar esta foto?');">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="eliminar_foto">
                        <input type="hidden" name="foto_id" value="<?= (int)$foto['id'] ?>">
                        <button type="submit" title="Eliminar">×</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="panel-vacio">Todavía no subiste fotos.</p>
    <?php endif; ?>

    <?php include __DIR__ . '/../_form_fotos.php'; ?>
</main>
</body>
</html>
