<?php
declare(strict_types=1);

/**
 * Página de una obra para el director de obra. Solo maneja la ejecución:
 * el día a día (dirección de obra), las fotos, las etapas y los archivos
 * de planificación. Los presupuestos los ve pero no los puede tocar, y
 * no ve los datos personales de los propietarios ni el resto de los
 * archivos.
 *
 * Cada acción se controla acá, en el servidor: que el formulario no
 * muestre un botón no alcanza.
 */

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/uploads.php';
require_once __DIR__ . '/../../lib/presupuestos.php';
require_once __DIR__ . '/../../lib/novedades.php';
require_once __DIR__ . '/../../lib/documentos.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'director');
asegurar_rol_director();
$usuarioId = (int)$usuario['id'];

$obraId = (int)($_GET['id'] ?? $_POST['obra_id'] ?? 0);

$stmt = db()->prepare('SELECT * FROM obras WHERE id = ? AND director_id = ? LIMIT 1');
$stmt->execute([$obraId, $usuarioId]);
$obra = $stmt->fetch();

if (!$obra) {
    http_response_code(404);
    echo 'Obra no encontrada, o no figurás como director de esta obra.';
    exit;
}

/** Lo que el director puede borrar: solo lo que subió él. */
$esPropio = static fn (array $fila, string $columna): bool => (int)($fila[$columna] ?? 0) === $usuarioId;
$puedeEliminarNovedad = static fn (array $novedad): bool => $esPropio($novedad, 'autor_id');
$puedeEliminarFoto = static fn (array $foto): bool => $esPropio($foto, 'subido_por');
$puedeEliminarDocumento = static fn (array $documento): bool => $esPropio($documento, 'subido_por');
$puedeEliminarEtapas = false;
$categoriasPermitidas = ['planificacion'];

$error = null;
$errorNovedad = null;
$errorDocumento = null;
$errorEtapa = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = (string)($_POST['accion'] ?? '');
    $volver = 'obra.php?id=' . $obra['id'];

    if ($accion === 'agregar_novedad') {
        $errorNovedad = agregar_novedad((int)$obra['id'], $_POST, $_FILES['foto'] ?? [], $usuarioId);
        if ($errorNovedad === null) {
            redirigir($volver . '#direccion');
        }
    } elseif ($accion === 'eliminar_novedad') {
        $sel = db()->prepare('SELECT * FROM novedades WHERE id = ? AND obra_id = ?');
        $sel->execute([(int)($_POST['novedad_id'] ?? 0), $obra['id']]);
        $novedad = $sel->fetch();
        if ($novedad && $puedeEliminarNovedad($novedad)) {
            eliminar_novedad((int)$novedad['id'], (int)$obra['id']);
        }
        redirigir($volver . '#direccion');
    } elseif ($accion === 'subir_foto') {
        $resultado = subir_fotos_obra(
            (int)$obra['id'],
            $_FILES['fotos'] ?? [],
            (string)($_POST['etapa'] ?? ''),
            trim((string)($_POST['descripcion'] ?? '')) ?: null,
            $usuarioId
        );
        if (es_pedido_fetch()) {
            responder_json($resultado, $resultado['subidas'] > 0 ? 200 : 422);
        }
        if (!$resultado['errores']) {
            redirigir($volver . '#fotos');
        }
        $error = implode(' · ', $resultado['errores']);
    } elseif ($accion === 'eliminar_foto') {
        $sel = db()->prepare('SELECT * FROM fotos WHERE id = ? AND obra_id = ?');
        $sel->execute([(int)($_POST['foto_id'] ?? 0), $obra['id']]);
        $foto = $sel->fetch();
        if ($foto && $puedeEliminarFoto($foto)) {
            $ruta = __DIR__ . '/../../uploads/obras/' . $obra['id'] . '/' . $foto['archivo'];
            if (is_file($ruta)) {
                unlink($ruta);
            }
            db()->prepare('DELETE FROM fotos WHERE id = ?')->execute([$foto['id']]);
        }
        redirigir($volver . '#fotos');
    } elseif ($accion === 'agregar_etapa') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($nombre === '') {
            $error = 'La etapa necesita un nombre.';
        } else {
            $orden = (int)db()->query('SELECT COALESCE(MAX(orden), 0) AS m FROM etapas WHERE obra_id = ' . (int)$obra['id'])->fetch()['m'] + 1;
            db()->prepare('INSERT INTO etapas (obra_id, nombre, orden, fecha, descripcion) VALUES (?, ?, ?, ?, ?)')->execute([
                $obra['id'],
                $nombre,
                $orden,
                trim((string)($_POST['fecha'] ?? '')) ?: null,
                trim((string)($_POST['descripcion'] ?? '')) ?: null,
            ]);
            redirigir($volver . '#etapas');
        }
    } elseif ($accion === 'editar_etapa') {
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $errorEtapa = actualizar_etapa($etapaId, (int)$obra['id'], $_POST);
        if ($errorEtapa === null) {
            redirigir($volver . '#etapa-' . $etapaId);
        }
    } elseif ($accion === 'agregar_documento') {
        // El director solo sube a Planificación, diga lo que diga el formulario.
        $errorDocumento = agregar_documento((int)$obra['id'], 'planificacion', $_POST, $_FILES['documento'] ?? [], $usuarioId);
        if ($errorDocumento === null) {
            redirigir($volver . '#archivos');
        }
    } elseif ($accion === 'eliminar_documento') {
        $sel = db()->prepare("SELECT * FROM documentos WHERE id = ? AND obra_id = ? AND categoria = 'planificacion'");
        $sel->execute([(int)($_POST['documento_id'] ?? 0), $obra['id']]);
        $documento = $sel->fetch();
        if ($documento && $puedeEliminarDocumento($documento)) {
            eliminar_documento((int)$documento['id'], (int)$obra['id']);
        }
        redirigir($volver . '#archivos');
    } else {
        http_response_code(403);
        echo 'Como director de obra no podés hacer esa acción.';
        exit;
    }
}

$stmtEtapas = db()->prepare('SELECT * FROM etapas WHERE obra_id = ? ORDER BY orden ASC, fecha ASC');
$stmtEtapas->execute([$obra['id']]);
$etapas = $stmtEtapas->fetchAll();

$stmtFotos = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? ORDER BY created_at ASC, id ASC');
$stmtFotos->execute([$obra['id']]);
$fotos = $stmtFotos->fetchAll();

$novedades = novedades_de_obra((int)$obra['id']);
$documentos = documentos_de_obra((int)$obra['id'], 'planificacion');
$presupuestos = presupuestos_de_obra((int)$obra['id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($obra['nombre']) ?> - Director de obra</title>
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
        · Ejecución de obra
    </p>

    <nav class="panel-secciones" aria-label="Secciones de la obra">
        <a href="#direccion">Dirección de obra</a>
        <a href="#fotos">Fotos</a>
        <a href="#archivos">Planificación</a>
        <a href="#etapas">Etapas</a>
        <a href="#presupuestos">Presupuestos</a>
    </nav>

    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <?php include __DIR__ . '/../_novedades.php'; ?>

    <?php include __DIR__ . '/../_galeria_etapas.php'; ?>

    <?php include __DIR__ . '/../_form_fotos.php'; ?>

    <?php include __DIR__ . '/../_documentos.php'; ?>

    <?php include __DIR__ . '/../_etapas.php'; ?>

    <h2 id="presupuestos">Presupuestos</h2>
    <p class="panel-subtitle">Solo lectura: los carga y los modifica el estudio.</p>
    <?php if (!$presupuestos): ?>
        <p class="panel-vacio">Todavía no hay presupuestos cargados.</p>
    <?php else: ?>
        <div class="panel-tabla-wrap">
            <table class="panel-tabla panel-presupuestos">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th class="panel-presupuestos__monto">Monto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($presupuestos as $presupuesto): ?>
                        <tr>
                            <td class="panel-presupuestos__fecha"><?= e(formatear_fecha($presupuesto['fecha'])) ?></td>
                            <td>
                                <?= e($presupuesto['concepto']) ?>
                                <?php if ($presupuesto['detalle']): ?><br><span class="panel-docs__peso"><?= e($presupuesto['detalle']) ?></span><?php endif; ?>
                            </td>
                            <td class="panel-presupuestos__monto"><?= e(formatear_monto($presupuesto['monto'], $presupuesto['moneda'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
