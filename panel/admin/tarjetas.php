<?php
declare(strict_types=1);

/**
 * Panel admin · Tarjetas
 *
 * Crea los links de solo lectura que se graban en una tarjeta NFC: el
 * cliente la acerca al telefono y se abren los renders de SU obra, sin
 * entrar a la cuenta ni ver nada mas. Se pueden dar de baja cuando sea.
 */

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/documentos.php';
require_once __DIR__ . '/../../lib/galerias.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'crear') {
        $obraId = (int)($_POST['obra_id'] ?? 0);
        $categoria = (string)($_POST['categoria'] ?? 'render');
        $stmt = db()->prepare('SELECT id FROM obras WHERE id = ? LIMIT 1');
        $stmt->execute([$obraId]);
        if ($stmt->fetch()) {
            // El token entero existe una sola vez: en la base queda solo
            // el hash. Se muestra ahora y despues ya no se puede recuperar.
            $_SESSION['tarjeta_nueva'] = crear_galeria($obraId, $categoria);
            $_SESSION['tarjeta_obra'] = $obraId;
        }
        redirigir('tarjetas.php');
    } elseif ($accion === 'revocar') {
        revocar_galeria((int)($_POST['galeria_id'] ?? 0));
        $_SESSION['tarjeta_aviso'] = 'Listo, ese link dejó de funcionar.';
        redirigir('tarjetas.php');
    }
}

$tokenNuevo = $_SESSION['tarjeta_nueva'] ?? null;
$obraDelToken = (int)($_SESSION['tarjeta_obra'] ?? 0);
$aviso = $_SESSION['tarjeta_aviso'] ?? null;
unset($_SESSION['tarjeta_nueva'], $_SESSION['tarjeta_obra'], $_SESSION['tarjeta_aviso']);

$obras = db()->query('
    SELECT o.id, o.nombre, c.nombre AS cliente
    FROM obras o
    LEFT JOIN usuarios c ON c.id = o.cliente_id
    ORDER BY o.nombre
')->fetchAll();

$porObra = [];
foreach (todas_las_galerias() as $g) {
    $porObra[(int)$g['obra_id']][] = $g;
}

/** Cuántas imágenes tiene esa carpeta: si está vacía, la tarjeta no muestra nada. */
function imagenes_en(int $obraId, string $categoria): int
{
    $n = 0;
    foreach (documentos_de_obra($obraId, $categoria) as $d) {
        if (tipo_documento((string)$d['archivo']) === 'Imagen') {
            $n++;
        }
    }
    return $n;
}

$base = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'www.arhaus.com.ar') . '/';
?>
<?php
$navActiva = 'tarjetas';
$tituloPagina = 'Tarjetas de renders';
include __DIR__ . '/_cabecera.php';
?>

    <h1>Tarjetas</h1>
    <p class="panel-subtitle">
        Links de solo lectura para grabar en una tarjeta NFC. Abren las imágenes de esa obra
        y nada más: no entran a la cuenta del cliente ni muestran pagos, documentos ni datos.
    </p>

    <?php if ($aviso): ?>
        <p class="panel-alert panel-alert--ok"><?= e($aviso) ?></p>
    <?php endif; ?>

    <?php if ($tokenNuevo): ?>
        <div class="panel-alert panel-alert--ok">
            <p><strong>Copiá este link ahora.</strong> Por seguridad no queda guardado: si lo perdés, creá uno nuevo.</p>
            <div class="panel-form panel-copiar">
                <input type="text" readonly onclick="this.select()"
                       value="<?= e($base . 'tarjeta.php?t=' . $tokenNuevo) ?>">
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$obras): ?>
        <p class="panel-vacio">Todavía no hay obras cargadas.</p>
    <?php else: ?>
        <?php foreach ($obras as $obra): ?>
            <?php $id = (int)$obra['id']; ?>
            <section class="panel-card panel-card--lista">
                <h2><?= e($obra['nombre']) ?></h2>
                <p class="panel-hint">Cliente: <?= e($obra['cliente'] ?? '—') ?></p>

                <?php if (!empty($porObra[$id])): ?>
                    <div class="panel-tabla-wrap">
                        <table class="panel-tabla">
                            <thead>
                                <tr><th>Carpeta</th><th>Visitas</th><th>Última vez</th><th></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($porObra[$id] as $g): ?>
                                    <tr>
                                        <td><?= e(GALERIA_CATEGORIAS[$g['categoria']] ?? $g['categoria']) ?></td>
                                        <td><?= (int)$g['visitas'] ?></td>
                                        <td><?= e($g['ultima_visita'] ?? '—') ?></td>
                                        <td>
                                            <form method="post" onsubmit="return confirm('El link deja de funcionar en el acto. ¿Darlo de baja?');">
                                                <?= campo_csrf() ?>
                                                <input type="hidden" name="accion" value="revocar">
                                                <input type="hidden" name="galeria_id" value="<?= (int)$g['id'] ?>">
                                                <button type="submit" class="panel-btn panel-btn--secundario">Dar de baja</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="panel-vacio">Esta obra todavía no tiene ninguna tarjeta.</p>
                <?php endif; ?>

                <form method="post" class="panel-actions">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="crear">
                    <input type="hidden" name="obra_id" value="<?= $id ?>">
                    <?php foreach (GALERIA_CATEGORIAS as $clave => $nombre): ?>
                        <?php $cuantas = imagenes_en($id, $clave); ?>
                        <button type="submit" name="categoria" value="<?= e($clave) ?>"
                                class="panel-btn<?= $clave === 'render' ? '' : ' panel-btn--secundario' ?>">
                            Crear link de <?= e($nombre) ?> (<?= $cuantas ?> <?= $cuantas === 1 ? 'imagen' : 'imágenes' ?>)
                        </button>
                    <?php endforeach; ?>
                </form>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
<?php include __DIR__ . '/_pie.php'; ?>
