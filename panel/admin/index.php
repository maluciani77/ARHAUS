<?php
declare(strict_types=1);

/**
 * Portada del panel del estudio: las obras.
 *
 * Antes esto y obras.php eran casi la misma pantalla —una listaba y la
 * otra listaba y además creaba—, así que había que acordarse en cuál de
 * las dos estaba cada cosa. Quedó una sola; obras.php redirige acá.
 */

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
            redirigir('obra.php?id=' . (int)db()->lastInsertId());
        }
    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM obras WHERE id = ?')->execute([$id]);

        $dirObra = __DIR__ . '/../../uploads/obras/' . $id;
        if (is_dir($dirObra)) {
            foreach (glob($dirObra . '/*') ?: [] as $archivo) {
                @unlink($archivo);
            }
            @rmdir($dirObra);
        }
        redirigir('index.php');
    }
}

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
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'director') AS directores,
        (SELECT COUNT(*) FROM obras) AS obras
")->fetch();

$clientes = db()->query("SELECT id, nombre FROM usuarios WHERE rol = 'cliente' ORDER BY nombre")->fetchAll();
$arquitectos = db()->query(
    // Los admin también pueden figurar como arquitecto de una obra: en un
    // estudio chico la misma persona dirige y administra.
    "SELECT id, nombre, rol FROM usuarios
     WHERE rol IN ('arquitecto', 'admin')
     ORDER BY (rol = 'admin'), nombre"
)->fetchAll();

$navActiva = 'obras';
$tituloPagina = 'Obras';
include __DIR__ . '/_cabecera.php';
?>

<header class="adm-cabecera">
    <p class="adm-etiqueta">Panel del estudio</p>
    <h1>Obras</h1>
    <p class="adm-cabecera__intro">Entrá a una obra para cargar sus planos, fotos, pagos y todo lo que ve el cliente.</p>
</header>

<?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

<div class="adm-cifras">
    <div class="adm-cifra"><strong><?= (int)$totales['obras'] ?></strong><span>Obras</span></div>
    <div class="adm-cifra"><strong><?= (int)$totales['clientes'] ?></strong><span>Clientes</span></div>
    <div class="adm-cifra"><strong><?= (int)$totales['arquitectos'] ?></strong><span>Arquitectos</span></div>
    <div class="adm-cifra"><strong><?= (int)$totales['directores'] ?></strong><span>Directores</span></div>
</div>

<?php if (!$obras): ?>
    <p class="panel-vacio">Todavía no hay obras cargadas. Creá la primera acá abajo.</p>
<?php else: ?>
    <div class="adm-tarjetas">
        <?php foreach ($obras as $obraFila): ?>
            <a class="adm-tarjeta" href="obra.php?id=<?= (int)$obraFila['id'] ?>">
                <span class="adm-estado"><?= e(nombre_estado($obraFila['estado'])) ?></span>
                <p class="adm-tarjeta__nombre"><?= e($obraFila['nombre']) ?></p>
                <?php if ($obraFila['ubicacion']): ?>
                    <p class="adm-tarjeta__dato"><?= e($obraFila['ubicacion']) ?></p>
                <?php endif; ?>
                <span class="adm-tarjeta__pie">
                    <?= $obraFila['cliente_nombre'] ? e($obraFila['cliente_nombre']) : 'Sin cliente asignado' ?>
                    <?php if ($obraFila['arquitecto_nombre']): ?>
                        &middot; <?= e($obraFila['arquitecto_nombre']) ?>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>Nueva obra</h2>
<div class="panel-card">
    <form method="post" class="panel-form">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="crear">
        <label>Nombre
            <input type="text" name="nombre" placeholder="Ej: Casa Huepil" required>
        </label>
        <label>Ubicación
            <input type="text" name="ubicacion" placeholder="Ej: San Martín de los Andes, Neuquén">
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
    <?php if (!$clientes): ?>
        <p class="panel-hint">Todavía no hay clientes cargados. Podés crearlos en <a href="usuarios.php">Usuarios</a>.</p>
    <?php endif; ?>
</div>

<?php if ($obras): ?>
    <h2>Dar de baja una obra</h2>
    <div class="panel-card">
        <p class="panel-hint">Se borran también sus etapas, fotos y archivos. No se puede deshacer.</p>
        <form method="post" class="panel-form"
              onsubmit="return confirm('¿Eliminar la obra? Se borran también sus etapas, fotos y archivos.');">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="eliminar">
            <label>Obra
                <select name="id" required>
                    <option value="">Elegí cuál</option>
                    <?php foreach ($obras as $obraFila): ?>
                        <option value="<?= (int)$obraFila['id'] ?>"><?= e($obraFila['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="panel-btn panel-btn--peligro">Eliminar</button>
        </form>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/_pie.php'; ?>
