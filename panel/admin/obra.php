<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/uploads.php';
require_once __DIR__ . '/../../lib/presupuestos.php';
require_once __DIR__ . '/../../lib/calendario.php';
require_once __DIR__ . '/../../lib/novedades.php';
require_once __DIR__ . '/../../lib/documentos.php';
require_once __DIR__ . '/../../lib/mensajes.php';
require_once __DIR__ . '/../../lib/obra_info.php';
require_once __DIR__ . '/../../lib/propietarios.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');
asegurar_rol_director();

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
$errorEtapa = null;
$errorEvento = null;
$errorNovedad = null;
$errorDocumento = null;
$errorMensaje = null;
$errorObraInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'actualizar_obra') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $ubicacion = trim((string)($_POST['ubicacion'] ?? '')) ?: null;
        $clienteId = (int)($_POST['cliente_id'] ?? 0) ?: null;
        $arquitectoId = (int)($_POST['arquitecto_id'] ?? 0) ?: null;
        $directorId = (int)($_POST['director_id'] ?? 0) ?: null;
        $estado = (string)($_POST['estado'] ?? 'en_curso');

        if ($nombre === '') {
            $error = 'La obra necesita un nombre.';
        } else {
            $upd = db()->prepare(
                'UPDATE obras SET nombre = ?, ubicacion = ?, cliente_id = ?, arquitecto_id = ?, director_id = ?, estado = ? WHERE id = ?'
            );
            $upd->execute([$nombre, $ubicacion, $clienteId, $arquitectoId, $directorId, $estado, $obra['id']]);
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
    } elseif ($accion === 'agregar_novedad') {
        $errorNovedad = agregar_novedad((int)$obra['id'], $_POST, $_FILES['foto'] ?? [], (int)$usuario['id']);
        if ($errorNovedad === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#direccion');
        }
    } elseif ($accion === 'eliminar_novedad') {
        eliminar_novedad((int)($_POST['novedad_id'] ?? 0), (int)$obra['id']);
        redirigir('obra.php?id=' . $obra['id'] . '#direccion');
    } elseif ($accion === 'agregar_documento') {
        $errorDocumento = agregar_documento(
            (int)$obra['id'],
            (string)($_POST['categoria'] ?? ''),
            $_POST,
            $_FILES['documento'] ?? [],
            (int)$usuario['id']
        );
        if ($errorDocumento === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#archivos');
        }
    } elseif ($accion === 'eliminar_documento') {
        eliminar_documento((int)($_POST['documento_id'] ?? 0), (int)$obra['id']);
        redirigir('obra.php?id=' . $obra['id'] . '#archivos');
    } elseif ($accion === 'agregar_mensaje') {
        $errorMensaje = agregar_mensaje((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorMensaje === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#mensajes');
        }
    } elseif ($accion === 'guardar_obra_info') {
        $errorObraInfo = guardar_obra_info((int)$obra['id'], $_POST);
        if ($errorObraInfo === null) {
            redirigir('obra.php?id=' . $obra['id'] . '#obra-info');
        }
    } elseif ($accion === 'eliminar_mensaje') {
        eliminar_mensaje((int)($_POST['mensaje_id'] ?? 0), (int)$obra['id']);
        redirigir('obra.php?id=' . $obra['id'] . '#mensajes');
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

$directores = db()->query("SELECT id, nombre FROM usuarios WHERE rol = 'director' ORDER BY nombre")->fetchAll();

$stmtEtapas = db()->prepare('SELECT * FROM etapas WHERE obra_id = ? ORDER BY orden ASC, fecha ASC');
$stmtEtapas->execute([$obra['id']]);
$etapas = $stmtEtapas->fetchAll();

$stmtFotos = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? ORDER BY created_at ASC, id ASC');
$stmtFotos->execute([$obra['id']]);
$fotos = $stmtFotos->fetchAll();

$presupuestos = presupuestos_de_obra((int)$obra['id']);
$hoy = hoy_argentina();
$novedades = novedades_de_obra((int)$obra['id']);
$documentos = documentos_de_obra((int)$obra['id']);
$mensajes = mensajes_de_obra((int)$obra['id']);
$obraInfo = obra_info((int)$obra['id']);
$propietarios = propietarios_de_obra((int)$obra['id']);
$calendario = eventos_de_obra($etapas, $fotos, $presupuestos, eventos_cargados_de_obra((int)$obra['id']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($obra['nombre']) ?> - Panel admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e(recurso($raiz, 'css/panel.css')) ?>">
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

    <nav class="panel-secciones" aria-label="Secciones de la obra">
        <a href="#obra-info">Obra info</a>
        <a href="#propietarios">Propietarios</a>
        <a href="#direccion">Dirección de obra</a>
        <a href="#archivos">Archivos</a>
        <a href="#mensajes">Mensajes</a>
        <a href="#presupuestos">Presupuestos</a>
        <a href="#calendario">Calendario</a>
        <a href="#etapas">Etapas</a>
        <a href="#fotos">Fotos</a>
    </nav>

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
            <label>Director de obra
                <select name="director_id">
                    <option value="">Sin asignar</option>
                    <?php foreach ($directores as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$d['id'] === (int)($obra['director_id'] ?? 0) ? 'selected' : '' ?>><?= e($d['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$directores): ?><small>Creá un usuario con rol "Director de obra" en Usuarios para poder asignarlo.</small><?php endif; ?>
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

    <?php include __DIR__ . '/../_calendario_obra.php'; ?>

    <?php include __DIR__ . '/../_etapas.php'; ?>

    <?php include __DIR__ . '/../_galeria_etapas.php'; ?>

    <?php include __DIR__ . '/../_form_fotos.php'; ?>

    <?php include __DIR__ . '/../_obra_info.php'; ?>

    <?php include __DIR__ . '/../_propietarios.php'; ?>

    <?php include __DIR__ . '/../_novedades.php'; ?>

    <?php include __DIR__ . '/../_documentos.php'; ?>

    <?php include __DIR__ . '/../_mensajes.php'; ?>

</main>
</body>
</html>
