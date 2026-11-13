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
require_once __DIR__ . '/../../lib/pagos.php';
require_once __DIR__ . '/../../lib/contactos.php';

$raiz = '../../';

/**
 * A donde se vuelve despues de guardar. Antes todo caia en obra.php?id=N,
 * que ahora es la pantalla de Datos: si guardabas un pago terminabas en
 * otra seccion. Esto conserva la que estabas mirando.
 *
 * $_GET funciona aunque el pedido sea POST: PHP lo arma igual con lo que
 * viene en la direccion, y los formularios postean a la misma.
 */
function volver_a(array $obra, string $ancla = ''): string
{
    $seccion = (string)($_GET['seccion'] ?? '');
    return 'obra.php?id=' . (int)$obra['id']
        . ($seccion !== '' ? '&seccion=' . urlencode($seccion) : '')
        . ($ancla !== '' ? '#' . $ancla : '');
}
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
$errorPago = null;
$errorContacto = null;

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
            redirigir(volver_a($obra));
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
            redirigir(volver_a($obra));
        }
    } elseif ($accion === 'eliminar_etapa') {
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $del = db()->prepare('DELETE FROM etapas WHERE id = ? AND obra_id = ?');
        $del->execute([$etapaId, $obra['id']]);
        redirigir(volver_a($obra));
    } elseif ($accion === 'editar_etapa') {
        $etapaId = (int)($_POST['etapa_id'] ?? 0);
        $errorEtapa = actualizar_etapa($etapaId, (int)$obra['id'], $_POST);
        if ($errorEtapa === null) {
            redirigir(volver_a($obra, 'etapa-' . $etapaId));
        }
    } elseif ($accion === 'agregar_evento') {
        $errorEvento = agregar_evento((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorEvento === null) {
            redirigir(volver_a($obra, 'calendario'));
        }
    } elseif ($accion === 'eliminar_evento') {
        eliminar_evento((int)($_POST['evento_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'calendario'));
    } elseif ($accion === 'agregar_presupuesto') {
        $errorPresupuesto = agregar_presupuesto((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorPresupuesto === null) {
            redirigir(volver_a($obra, 'presupuestos'));
        }
    } elseif ($accion === 'alternar_monto_oculto') {
        alternar_monto_oculto((int)($_POST['presupuesto_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'presupuestos'));
    } elseif ($accion === 'eliminar_presupuesto') {
        eliminar_presupuesto((int)($_POST['presupuesto_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'presupuestos'));
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
            redirigir(volver_a($obra));
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
        redirigir(volver_a($obra));
    } elseif ($accion === 'agregar_novedad') {
        $errorNovedad = agregar_novedad((int)$obra['id'], $_POST, $_FILES['foto'] ?? [], (int)$usuario['id']);
        if ($errorNovedad === null) {
            redirigir(volver_a($obra, 'direccion'));
        }
    } elseif ($accion === 'eliminar_novedad') {
        eliminar_novedad((int)($_POST['novedad_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'direccion'));
    } elseif ($accion === 'agregar_documento') {
        $errorDocumento = agregar_documento(
            (int)$obra['id'],
            (string)($_POST['categoria'] ?? ''),
            $_POST,
            $_FILES['documento'] ?? [],
            (int)$usuario['id']
        );
        if ($errorDocumento === null) {
            redirigir(volver_a($obra, 'archivos'));
        }
    } elseif ($accion === 'eliminar_documento') {
        eliminar_documento((int)($_POST['documento_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'archivos'));
    } elseif ($accion === 'agregar_mensaje') {
        $errorMensaje = agregar_mensaje((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorMensaje === null) {
            redirigir(volver_a($obra, 'mensajes'));
        }
    } elseif ($accion === 'agregar_pago') {
        $errorPago = agregar_pago((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorPago === null) {
            redirigir(volver_a($obra, 'pagos'));
        }
    } elseif ($accion === 'eliminar_pago') {
        eliminar_pago((int)($_POST['pago_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'pagos'));
    } elseif ($accion === 'adjuntar_comprobante') {
        $errorPago = adjuntar_comprobante((int)($_POST['pago_id'] ?? 0), (int)$obra['id'], $_FILES['comprobante'] ?? [], (int)$usuario['id']);
        if ($errorPago === null) {
            redirigir(volver_a($obra, 'pagos'));
        }
    } elseif ($accion === 'quitar_comprobante') {
        quitar_comprobante((int)($_POST['pago_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'pagos'));
    } elseif ($accion === 'agregar_contacto') {
        $errorContacto = agregar_contacto((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorContacto === null) {
            redirigir(volver_a($obra, 'contactos'));
        }
    } elseif ($accion === 'eliminar_contacto') {
        eliminar_contacto((int)($_POST['contacto_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'contactos'));
    } elseif ($accion === 'adjuntar_archivo_contacto') {
        $errorContacto = adjuntar_archivo_contacto((int)($_POST['contacto_id'] ?? 0), (int)$obra['id'], $_POST, $_FILES['documento'] ?? [], (int)$usuario['id']);
        if ($errorContacto === null) {
            redirigir(volver_a($obra, 'contactos'));
        }
    } elseif ($accion === 'eliminar_archivo_contacto') {
        eliminar_archivo_contacto((int)($_POST['archivo_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'contactos'));
    } elseif ($accion === 'guardar_obra_info') {
        $errorObraInfo = guardar_obra_info((int)$obra['id'], $_POST);
        if ($errorObraInfo === null) {
            redirigir(volver_a($obra, 'obra-info'));
        }
    } elseif ($accion === 'eliminar_mensaje') {
        eliminar_mensaje((int)($_POST['mensaje_id'] ?? 0), (int)$obra['id']);
        redirigir(volver_a($obra, 'mensajes'));
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
$pagos = pagos_de_obra((int)$obra['id']);
$contactos = contactos_de_obra((int)$obra['id']);
$archivosContactos = archivos_de_contactos((int)$obra['id']);
$calendario = eventos_de_obra($etapas, $fotos, $presupuestos, eventos_cargados_de_obra((int)$obra['id']));

/* ========================================
   QUE SECCION SE ESTA MIRANDO
   Antes esta pagina dibujaba las once secciones una abajo de la otra y
   se navegaba con anclas. Ahora se abre de a una, con el mismo arbol que
   ve el cliente.
   ======================================== */
require_once __DIR__ . '/_menu.php';

$hojas = admin_hojas();
$seccion = (string)($_GET['seccion'] ?? 'datos');
if (!isset($hojas[$seccion])) {
    $seccion = 'datos';
}
[$nombreSeccion, $grupoActivo, $bloques, $filtro] = $hojas[$seccion];

/* Los numeritos de la barra lateral: cuantas cosas hay en cada hoja. Al
   estudio le sirven para ver que le falta cargar sin entrar a mirar. */
$porCategoria = [];
foreach ($documentos as $doc) {
    $porCategoria[(string)$doc['categoria']] = ($porCategoria[(string)$doc['categoria']] ?? 0) + 1;
}
$porTipoContacto = [];
foreach ($contactos as $con) {
    $porTipoContacto[(string)$con['tipo']] = ($porTipoContacto[(string)$con['tipo']] ?? 0) + 1;
}
$sueltas = [
    'calendario' => count($calendario),
    'propietarios' => count($propietarios),
    'pagos' => count($pagos),
    'direccion' => count($novedades),
    'fotos' => count($fotos),
    'etapas' => count($etapas),
    'presupuestos' => count($presupuestos),
    'mensajes' => count($mensajes),
];
$cuentas = [];
foreach ($hojas as $clave => [, , $susBloques, $suFiltro]) {
    if (isset($sueltas[$clave])) {
        $cuentas[$clave] = $sueltas[$clave];
    } elseif ($suFiltro && in_array('_documentos', $susBloques, true)) {
        $cuentas[$clave] = array_sum(array_intersect_key($porCategoria, array_flip($suFiltro)));
    } elseif ($suFiltro && in_array('_contactos', $susBloques, true)) {
        $cuentas[$clave] = array_sum(array_intersect_key($porTipoContacto, array_flip($suFiltro)));
    }
}

$tituloPagina = $obra['nombre'] . ' · ' . $nombreSeccion;
include __DIR__ . '/_cabecera.php';
?>

<?php /* Las solapas del grupo, cuando hay mas de una. */ ?>
<?php [$nombreGrupo, , $hojasDelGrupo] = ADMIN_MENU_OBRA[$grupoActivo]; ?>
<?php if (count($hojasDelGrupo) > 1): ?>
    <nav class="adm-subnav" aria-label="<?= e($nombreGrupo) ?>">
        <?php foreach ($hojasDelGrupo as $clave => [$nombreHoja]): ?>
            <a class="adm-subnav__item<?= $clave === $seccion ? ' is-activa' : '' ?>"
               href="obra.php?id=<?= (int)$obra['id'] ?>&amp;seccion=<?= e((string)$clave) ?>"
               <?= $clave === $seccion ? 'aria-current="page"' : '' ?>><?= e($nombreHoja) ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<header class="adm-cabecera">
    <p class="adm-etiqueta"><?= e($nombreGrupo) ?></p>
    <h1><?= e($nombreSeccion) ?></h1>
</header>

<?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

<?php if ($seccion === 'datos'): ?>
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

    <?php /* La maqueta 3D se activa subiendo el archivo por FTP, asi que
             aca solo se avisa si esta o no: es la pregunta que siempre
             aparece cuando el cliente no la ve. */ ?>
    <?php $hayMaqueta = is_file(__DIR__ . '/../../models/obras/' . (int)$obra['id'] . '.glb'); ?>
    <h2>Maqueta 3D</h2>
    <p class="panel-hint">
        <?php if ($hayMaqueta): ?>
            Cargada. El cliente la ve en Inicio y en Proyecto &rsaquo; 3D.
        <?php else: ?>
            Sin cargar. Subí el archivo como
            <code>models/obras/<?= (int)$obra['id'] ?>.glb</code> y aparece sola.
        <?php endif; ?>
    </p>

<?php else: ?>
    <?php
    /* El filtro que espera cada bloque. Los bloques no se tocaron: ya
       sabian recortarse solos. */
    if ($filtro && in_array('_documentos', $bloques, true)) {
        $categoriasPermitidas = $filtro;
    }
    if ($filtro && in_array('_contactos', $bloques, true)) {
        $tiposPermitidos = $filtro;
    }
    ?>
    <?php /* Cada bloque arranca con su propio <h2>, que ahora repite el
             titulo de la pantalla. Se lo esconde por CSS en vez de tocar
             los doce archivos, que los comparten el arquitecto y el
             director de obra. */ ?>
    <div class="adm-bloques">
        <?php foreach ($bloques as $bloque) {
            include __DIR__ . '/../' . $bloque . '.php';
        } ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/_pie.php'; ?>
