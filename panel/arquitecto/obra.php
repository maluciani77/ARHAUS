<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/uploads.php';
require_once __DIR__ . '/../../lib/presupuestos.php';
require_once __DIR__ . '/../../lib/calendario.php';

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
$errorPresupuesto = null;
$errorEtapa = null;
$errorEvento = null;

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
    } elseif ($accion === 'editar_etapa') {
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $errorEtapa = actualizar_etapa($etapaId, (int)$obra['id'], $_POST);
        if ($errorEtapa === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#etapa-' . $etapaId);
        }
    } elseif ($accion === 'agregar_evento') {
        $errorEvento = agregar_evento((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorEvento === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#calendario');
        }
    } elseif ($accion === 'eliminar_evento') {
        eliminar_evento((int)($_POST['evento_id'] ?? 0), (int)$obra['id']);
        redirigir('obra.php?id=' . $obra['id'] . '#calendario');
    } elseif ($accion === 'agregar_presupuesto') {
        $errorPresupuesto = agregar_presupuesto((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorPresupuesto === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#presupuestos');
        }
    } elseif ($accion === 'alternar_monto_oculto') {
        alternar_monto_oculto((int)($_POST['presupuesto_id'] ?? 0), (int)$obra['id']);
        redirigir('obra.php?id=' . $obra['id'] . '#presupuestos');
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

$stmtFotos = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? ORDER BY created_at ASC, id ASC');
$stmtFotos->execute([$obra['id']]);
$fotos = $stmtFotos->fetchAll();

$presupuestos = presupuestos_de_obra((int)$obra['id']);
$hoy = hoy_argentina();
$calendario = eventos_de_obra($etapas, $fotos, $presupuestos, eventos_cargados_de_obra((int)$obra['id']));
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

    <nav class="panel-secciones" aria-label="Secciones de la obra">
        <a href="#presupuestos">Presupuestos</a>
        <a href="#calendario">Calendario</a>
        <a href="#etapas">Etapas</a>
        <a href="#fotos">Fotos</a>
    </nav>

    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <?php include __DIR__ . '/../_presupuestos.php'; ?>

    <?php include __DIR__ . '/../_calendario_obra.php'; ?>

    <?php include __DIR__ . '/../_etapas.php'; ?>

    <?php include __DIR__ . '/../_galeria_etapas.php'; ?>

    <?php include __DIR__ . '/../_form_fotos.php'; ?>
</main>
</body>
</html>
