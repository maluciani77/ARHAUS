<?php
declare(strict_types=1);

/**
 * Tarjetas NFC: los links que entran a una cuenta sin contraseña.
 * Acá se arman, se ven y se dan de baja. El link completo se muestra una
 * sola vez, cuando se crea: después en la base queda solo su hash.
 */

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/acceso_directo.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $usuarioId = (int)($_POST['usuario_id'] ?? 0);
        $nota = (string)($_POST['nota'] ?? '');
        $expira = trim((string)($_POST['expira'] ?? ''));

        $stmt = db()->prepare('SELECT id FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$usuarioId]);

        if (!$stmt->fetch()) {
            $error = 'Elegí a quién le pertenece la tarjeta.';
        } else {
            $token = crear_acceso_directo(
                $usuarioId,
                $nota,
                $expira !== '' ? $expira . ' 23:59:59' : null,
                (int)$usuario['id']
            );
            // El link va por la sesión y no por la dirección: así no
            // queda en el historial del navegador ni en los registros
            // del servidor.
            iniciar_sesion_segura();
            $_SESSION['acceso_nuevo'] = acceso_directo_url($token);
            redirigir('accesos.php');
        }
    } elseif ($accion === 'revocar') {
        revocar_acceso_directo((int)($_POST['id'] ?? 0));
        redirigir('accesos.php');
    } elseif ($accion === 'eliminar') {
        eliminar_acceso_directo((int)($_POST['id'] ?? 0));
        redirigir('accesos.php');
    }
}

// El link recién creado, una sola vez.
iniciar_sesion_segura();
$linkNuevo = $_SESSION['acceso_nuevo'] ?? null;
unset($_SESSION['acceso_nuevo']);

$accesos = listar_accesos_directos();
$personas = db()->query('SELECT id, nombre, email, rol FROM usuarios ORDER BY rol, nombre')->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Tarjetas NFC - Panel admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="<?= e(recurso($raiz, 'css/panel.css')) ?>">
<style>
    /* El link recién creado: grande, monoespaciado y seleccionable de un
       toque. Es lo único que hay que copiar de esta página. */
    .nfc-link {
        display: block;
        width: 100%;
        margin: 10px 0 0;
        padding: 12px 14px;
        font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
        font-size: 0.92rem;
        line-height: 1.5;
        word-break: break-all;
        border: 1px solid #c9c4b8;
        border-radius: 4px;
        background: #fff;
        color: #2b2b2b;
    }
    .nfc-estado { font-size: 0.78rem; letter-spacing: 0.04em; text-transform: uppercase; }
    .nfc-estado--activo { color: #1C6E3C; }
    .nfc-estado--revocado,
    .nfc-estado--vencido { color: #880723; }
    .nfc-acciones { display: flex; gap: 8px; }
    .nfc-acciones button { padding: 4px 10px; font-size: 0.78rem; }
</style>
</head>
<body class="panel-body">
<?php $navLinks = [
    ['href' => 'index.php', 'texto' => '← Panel'],
    ['href' => 'usuarios.php', 'texto' => 'Usuarios'],
    ['href' => 'obras.php', 'texto' => 'Obras'],
]; include __DIR__ . '/../_topbar.php'; ?>

<main class="panel-main">
    <h1>Tarjetas NFC</h1>
    <p class="panel-subtitle">
        Cada tarjeta lleva grabado un link que entra directo a la cuenta, sin contraseña.
        El que tenga la tarjeta en la mano entra: es como una llave. Si se pierde, se da de baja acá y el link deja de servir en el acto.
    </p>

    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <?php if ($linkNuevo): ?>
        <div class="panel-card" style="border-color:#1C6E3C;">
            <h2 style="margin-top:0;">Grabá esto en la tarjeta</h2>
            <p class="panel-hint" style="margin:0;">
                Es la única vez que se muestra. En la base queda nada más que su huella, así que no hay forma de volver a verlo:
                si lo perdés, dale de baja a esta tarjeta y hacé una nueva.
            </p>
            <input class="nfc-link" type="text" value="<?= e($linkNuevo) ?>" readonly onclick="this.select();">
            <p class="panel-hint">
                Con NFC Tools (Android o iPhone): <strong>Escribir → Agregar un registro → URL</strong>, pegás esto y acercás la tarjeta.
                Después conviene bloquear el chip para que nadie lo regrabe.
            </p>
        </div>
    <?php endif; ?>

    <div class="panel-tabla-wrap">
        <table class="panel-tabla">
            <thead>
                <tr><th>Dueño</th><th>Tarjeta</th><th>Estado</th><th>Usos</th><th>Último uso</th><th></th></tr>
            </thead>
            <tbody>
                <?php if (!$accesos): ?>
                    <tr><td colspan="6" class="panel-hint">Todavía no hay ninguna tarjeta.</td></tr>
                <?php endif; ?>
                <?php foreach ($accesos as $a): ?>
                    <?php $estado = estado_acceso_directo($a); ?>
                    <tr>
                        <td>
                            <?= e($a['usuario_nombre']) ?><br>
                            <span class="panel-hint"><?= e($a['usuario_email']) ?> · <?= e(nombre_rol($a['usuario_rol'])) ?></span>
                        </td>
                        <td>
                            <?= $a['nota'] !== '' ? e($a['nota']) : '<span class="panel-hint">Sin nota</span>' ?><br>
                            <span class="panel-hint">
                                Creada el <?= e(formatear_fecha(substr((string)$a['created_at'], 0, 10))) ?>
                                <?php if (!empty($a['expira'])): ?>
                                    · vence el <?= e(formatear_fecha(substr((string)$a['expira'], 0, 10))) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td><span class="nfc-estado nfc-estado--<?= e($estado) ?>"><?= e($estado) ?></span></td>
                        <td><?= (int)$a['usos'] ?></td>
                        <td>
                            <?= $a['ultimo_uso']
                                ? e(formatear_fecha(substr((string)$a['ultimo_uso'], 0, 10)) . ' · ' . substr((string)$a['ultimo_uso'], 11, 5))
                                : '<span class="panel-hint">Nunca</span>' ?>
                        </td>
                        <td>
                            <div class="nfc-acciones">
                                <?php if ($estado === 'activo'): ?>
                                    <form method="post" onsubmit="return confirm('¿Dar de baja esta tarjeta? El link deja de funcionar en el acto.');">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="revocar">
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="panel-btn panel-btn--peligro">Dar de baja</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" onsubmit="return confirm('¿Borrar el registro de esta tarjeta?');">
                                        <?= campo_csrf() ?>
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                                        <button type="submit" class="panel-btn">Borrar</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>Nueva tarjeta</h2>
    <div class="panel-card">
        <form method="post" class="panel-form">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="crear">
            <label>¿De quién es?
                <select name="usuario_id" required>
                    <option value="">Elegí una persona</option>
                    <?php foreach ($personas as $p): ?>
                        <option value="<?= (int)$p['id'] ?>">
                            <?= e($p['nombre']) ?> — <?= e(nombre_rol($p['rol'])) ?> (<?= e($p['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Nota (para reconocerla después)
                <input type="text" name="nota" maxlength="190" placeholder="Tarjeta de bolsillo — Martín Simois">
            </label>
            <label>Vence el (opcional, si se deja vacío no vence)
                <input type="date" name="expira">
            </label>
            <button type="submit">Crear y mostrar el link</button>
        </form>
    </div>
</main>
</body>
</html>
