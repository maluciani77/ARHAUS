<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/uploads.php';
require_once __DIR__ . '/../../lib/presupuestos.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');

$obraId = (int)($_GET['id'] ?? $_POST['obra_id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM obras WHERE id = ? LIMIT 1');
$stmt->execute([$obraId]);
$obra = $stmt->fetch();

if (!$obra) {
    http_response_code(404);
    echo 'Obra no encontrada.';
    exit;
}

$error = null;
$errorPresupuesto = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar_obra') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $ubicacion = trim((string)($_POST['ubicacion'] ?? '')) ?: null;
        $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
        $arquitectoId = (int)($_POST['arquitecto_id'] ?? 0) ?: null;
        $estado = (string)($_POST['estado'] ?? 'en_curso');

        if ($nombre === '') {
            $error = 'La obra necesita un nombre.';
        } else {
            $upd = db()->prepare(
                'UPDATE obras SET nombre = ?, ubicacion = ?, cliente_id = ?, arquitecto_id = ?, estado = ? WHERE id = ?'
            );
            $upd->execute([$nombre, $ubicacion, $clienteId, $arquitectoId, $estado, $obra['id']]);
            redirigir('obra.php?id=' . $obra['id']);
        }
    } elseif ($accion === 'agregar_etapa') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $fecha = trim((string)($_POST['fecha'] ?? '')) ?: null;
        $descripcion = trim((string)($_POST['descripcion'] ?? '')) ?: null;

        if ($nombre === '') {
            $error = 'La etapa necesita un nombre.';
        } else {
            $orden = (int)db()->query('SELECT COALESCE(MAX(orden), 0) AS m FROM etapas WHERE obra_id = ' . (int)$obra['id'])->fetch()['m'] + 1;
            $ins = db()->prepare('INSERT INTO etapas (obra_id, nombre, orden, fecha, descripcion) VALUES (?, ?, ?, ?, ?)');
            $ins->execute([$obra['id'], $nombre, $orden, $fecha, $descripcion]);
            redirigir('obra.php?id=' . $obra['id']);
        }
    } elseif ($accion === 'eliminar_etapa') {
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $del = db()->prepare('DELETE FROM etapas WHERE id = ? AND obra_id = ?');
        $del->execute([$etapaId, $obra['id']]);
        redirigir('obra.php?id=' . $obra['id']);
    } elseif ($accion === 'agregar_presupuesto') {
        $errorPresupuesto = agregar_presupuesto((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorPresupuesto === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#presupuestos');
        }
    } elseif ($accion === 'eliminar_presupuesto') {
        eliminar_presupuesto((int)($_POST['presupuesto_id'] ?? 0), (int)$obra['id']);
        redirigir('obra.php?id=' . $obra['id'] . '#presupuestos');
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
        $sel = db()->prepare('SELECT * FROM fotos WHERE id = ? AND obra_id = ?');
        $sel->execute([$fotoId, $obra['id']]);
        $foto = $sel->fetch();
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

    // Releer la obra por si se actualizó.
    $stmt->execute([$obraId]);
    $obra = $stmt->fetch();
}

$clientes = db()->query("SELECT id, nombre FROM usuarios WHERE rol = 'cliente' ORDER BY nombre")->fetchAll();
$arquitectos = db()->query(
    // Los admin tambien pueden figurar como arquitecto de una obra: en un
    // estudio chico la misma persona dirige y administra. Van despues de
    // los arquitectos en la lista, y se muestran aclarando el rol.
    "SELECT id, nombre, rol FROM usuarios
     WHERE rol IN ('arquitecto', 'admin')
     ORDER BY (rol = 'admin'), nombre"
)->fetchAll();

$stmtEtapas = db()->prepare('SELECT * FROM etapas WHERE obra_id = ? ORDER BY orden ASC, fecha ASC');
$stmtEtapas->execute([$obra['id']]);
$etapas = $stmtEtapas->fetchAll();

$stmtFotos = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? ORDER BY created_at DESC');
$stmtFotos->execute([$obra['id']]);
$fotos = $stmtFotos->fetchAll();

$presupuestos = presupuestos_de_obra((int)$obra['id']);
$puedeEditar = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($obra['nombre']) ?> - Panel admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e($raiz) ?>css/panel.css">
</head>
<body class="panel-body">
<?php $navLinks = [
    ['href' => 'index.php', 'texto' => '← Panel'],
    ['href' => 'obras.php', 'texto' => 'Obras'],
    ['href' => 'usuarios.php', 'texto' => 'Usuarios'],
]; include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
    <h1><?= e($obra['nombre']) ?></h1>
    <p class="panel-subtitle">
        <?= e($obra['ubicacion'] ?? '') ?>
        · <span class="panel-tag panel-tag--rojo"><?= e(nombre_estado($obra['estado'])) ?></span>
    </p>

    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <h2>Datos de la obra</h2>
    <div class="panel-card">
        <form method="post" class="panel-form">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="actualizar_obra">
            <label>Nombre
                <input type="text" name="nombre" value="<?= e($obra['nombre']) ?>" required>
            </label>
            <label>Ubicación
                <input type="text" name="ubicacion" value="<?= e($obra['ubicacion'] ?? '') ?>">
            </label>
            <label>Cliente
                <select name="cliente_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($clientes as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$obra['cliente_id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Arquitecto
                <select name="arquitecto_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($arquitectos as $a): ?>
                        <option value="<?= (int)$a['id'] ?>" <?= (int)$a['id'] === (int)$obra['arquitecto_id'] ? 'selected' : '' ?>><?= e($a['nombre']) ?><?= $a['rol'] === 'admin' ? ' (admin)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Estado
                <select name="estado">
                    <?php foreach (['en_curso', 'pausada', 'finalizada'] as $est): ?>
                        <option value="<?= $est ?>" <?= $est === $obra['estado'] ? 'selected' : '' ?>><?= e(nombre_estado($est)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Guardar cambios</button>
        </form>
    </div>

    <?php include __DIR__ . '/../_presupuestos.php'; ?>

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
        <p class="panel-vacio">Todavía no hay etapas cargadas.</p>
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
        <p class="panel-vacio">Todavía no hay fotos subidas.</p>
    <?php endif; ?>

    <?php include __DIR__ . '/../_form_fotos.php'; ?>
</main>
</body>
</html>
