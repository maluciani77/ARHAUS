<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/uploads.php';
require_once __DIR__ . '/../../lib/presupuestos.php';
require_once __DIR__ . '/../../lib/calendario.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/novedades.php';
require_once __DIR__ . '/../../lib/documentos.php';
require_once __DIR__ . '/../../lib/mensajes.php';
require_once __DIR__ . '/../../lib/cuenta.php';
require_once __DIR__ . '/../../lib/asistente.php';
require_once __DIR__ . '/../../lib/obra_info.php';
require_once __DIR__ . '/../../lib/propietarios.php';
require_once __DIR__ . '/../../lib/pagos.php';
require_once __DIR__ . '/../../lib/contactos.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'cliente');

/**
 * El menú del panel, en tres niveles: botón > solapa > sección. Cuando una
 * solapa tiene más de una sección se ven como una segunda fila de
 * solapas. La clave de cada sección es el ?seccion= de la URL.
 */
const MENU_CLIENTE = [
    'reuniones' => ['Reuniones', [
        'reuniones' => ['Calendario', ['calendario' => 'Calendario']],
    ]],
    'propietarios' => ['Propietarios', [
        'prop_archivos' => ['Archivos', ['prop_archivos' => 'Archivos']],
        'datos' => ['Datos personales', ['propietarios' => 'Datos personales']],
        'contrato' => ['Contrato', ['contrato' => 'Contrato']],
        'obra_info' => ['Obra info', ['ubicacion' => 'Ubicación', 'info_obra' => 'Info obra']],
        'referentes' => ['Referentes', ['referentes' => 'Ideas']],
        'pagos' => ['Pagos', ['pagos' => 'Pagos']],
    ]],
    'proyecto' => ['Proyecto', [
        'arquitectura' => ['Planos', [
            'planos_arq' => 'Arq',
            'planos_electricos' => 'Eléctricos',
            'planos_sanitarios' => 'Sanitarios',
            'planos_carpinteria' => 'Carpintería',
            'planos_cielorrasos' => 'Cielorrasos',
            'plano_estructura' => 'Estructura',
            'planos_amoblamiento' => 'Amoblamiento',
            'planos_municipales' => 'Municipales',
            'demolicion' => 'Demolición',
            'arq_varios' => 'Varios',
        ]],
        'visuales' => ['Visuales', ['brochure' => 'Brochure', 'render' => 'Renders', 'videos' => 'Videos']],
        'obra' => ['Obra', ['computo' => 'Cómputo', 'presupuesto_proy' => 'Presupuesto', 'planificacion_gantt' => 'Planificación (Gantt)', 'obra_otros' => 'Otros']],
        'gestion' => ['Gestión', ['gestion_gestor' => 'Gestor', 'gestion_ingeniero' => 'Ingeniero', 'gestion_proveedores' => 'Proveedores', 'gestion_administracion' => 'Administración']],
    ]],
    'ejecucion' => ['Ejecución de obra', [
        'direccion' => ['Dirección de obra', ['direccion' => 'Seguimiento', 'fotos_dia' => 'Fotos día por día']],
        'profesionales' => ['Profesionales', ['higiene' => 'Higiene y seguridad', 'gestor' => 'Gestor', 'agrimensor' => 'Agrimensor']],
        'contratados' => ['Contratados', ['proveedores' => 'Proveedores']],
        'fotos' => ['Fotos', ['fotos' => 'Fotos']],
        'informes' => ['Informes', ['informes' => 'Informes']],
        'presupuestos' => ['Presupuestos', ['presupuestos' => 'Presupuestos']],
        'planificacion' => ['Planificación', ['etapas' => 'Etapa de obra', 'planificacion' => 'Gantt']],
        'ejec_archivos' => ['Archivos', ['ejec_archivos' => 'Archivos']],
    ]],
    'telefonos' => ['Teléfonos útiles', [
        'telefonos' => ['Teléfonos útiles', ['telefonos' => 'Teléfonos útiles']],
    ]],
    'mensajes' => ['Mensajes', [
        'mensajes' => ['Mensajes', ['mensajes' => 'Mensajes']],
    ]],
];

/** Nombres más cortos para la barra de abajo del celular, donde no entran los largos. */
const NOMBRES_CORTOS_GRUPOS = [
    'propietarios' => 'Dueños',
    'ejecucion' => 'Ejecución',
    'telefonos' => 'Teléfonos',
];

/** Páginas que no son un botón: Inicio (el logo), el asistente (botón flotante) y Mi cuenta (la foto). */
const SECCIONES_SUELTAS = [
    'inicio' => 'Inicio',
    'asistente' => 'Asistente',
    'cuenta' => 'Mi cuenta',
];

/** Las secciones que son una lista de contactos, con su tipo. */
const SECCIONES_CONTACTOS = [
    'higiene' => 'higiene',
    'gestor' => 'gestor',
    'agrimensor' => 'agrimensor',
    'proveedores' => 'proveedor',
    'telefonos' => 'telefono',
    // Proyecto > Gestión. El gestor y los proveedores son los mismos que
    // en Ejecución: se cargan una vez y se ven en los dos lados.
    'gestion_gestor' => 'gestor',
    'gestion_ingeniero' => 'ingeniero',
    'gestion_proveedores' => 'proveedor',
    'gestion_administracion' => 'administracion',
];

/** Todas las secciones: clave => [nombre, botón, solapa] (botón y solapa en null si es suelta). */
function secciones_cliente(): array
{
    $secciones = [];
    foreach (MENU_CLIENTE as $grupo => [, $solapas]) {
        foreach ($solapas as $solapa => [, $hojas]) {
            foreach ($hojas as $clave => $nombre) {
                $secciones[$clave] = [$nombre, $grupo, $solapa];
            }
        }
    }
    foreach (SECCIONES_SUELTAS as $clave => $nombre) {
        $secciones[$clave] = [$nombre, null, null];
    }
    return $secciones;
}

/** La primera sección de un botón: adonde lleva al tocarlo. */
function primera_seccion(array $solapas): string
{
    $primera = reset($solapas);
    return (string)array_key_first($primera[1]);
}

$seccion = $_GET['seccion'] ?? 'inicio';
if (!is_string($seccion) || !isset(secciones_cliente()[$seccion])) {
    $seccion = 'inicio';
}
[$nombreSeccion, $grupoActual, $solapaActual] = secciones_cliente()[$seccion];

$stmt = db()->prepare('SELECT * FROM obras WHERE cliente_id = ? LIMIT 1');
$stmt->execute([$usuario['id']]);
$obra = $stmt->fetch();

// La sesión no guarda la foto de perfil: se lee siempre de la base.
$perfil = usuario_por_id((int)$usuario['id']) ?? $usuario;

// Lo que el cliente puede escribir: un mensaje para el estudio, una
// pregunta para el asistente, y los datos de su propia cuenta.
$errorMensaje = null;
$errorCuenta = null;
$errorAsistente = null;
$errorPropietario = null;
$errorArchivoCliente = null;
$errorComprobante = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'agregar_mensaje' && $obra) {
        $errorMensaje = agregar_mensaje((int)$obra['id'], $_POST, (int)$usuario['id']);
        if ($errorMensaje === null) {
            redirigir('index.php?seccion=mensajes');
        }
    } elseif ($accion === 'preguntar_asistente' && $obra) {
        $resultado = preguntar_asistente($perfil, $obra, (string)($_POST['pregunta'] ?? ''));
        // El chat manda la pregunta con fetch y espera JSON; sin JavaScript, vuelve a la página.
        if (es_pedido_fetch()) {
            responder_json($resultado, isset($resultado['error']) ? 422 : 200);
        }
        if (!isset($resultado['error'])) {
            redirigir('index.php?seccion=asistente#ultima');
        }
        $errorAsistente = $resultado['error'];
    } elseif ($accion === 'borrar_asistente' && $obra) {
        borrar_historial_asistente((int)$usuario['id'], (int)$obra['id']);
        redirigir('index.php?seccion=asistente');
    } elseif ($accion === 'guardar_propietario' && $obra) {
        $errorPropietario = guardar_propietario((int)$obra['id'], (int)($_POST['propietario_id'] ?? 0), $_POST, (int)$usuario['id']);
        if ($errorPropietario === null) {
            $_SESSION['aviso_cuenta'] = 'Listo, se guardaron los datos.';
            redirigir('index.php?seccion=propietarios');
        }
    } elseif ($accion === 'eliminar_propietario' && $obra) {
        eliminar_propietario((int)($_POST['propietario_id'] ?? 0), (int)$obra['id']);
        $_SESSION['aviso_cuenta'] = 'Se quitó el propietario.';
        redirigir('index.php?seccion=propietarios');
    } elseif ($accion === 'subir_archivo_cliente' && $obra) {
        // El cliente solo sube a sus propias carpetas (sus documentos y sus ideas).
        $categoria = (string)($_POST['categoria'] ?? '');
        if (!in_array($categoria, CATEGORIAS_DEL_CLIENTE, true)) {
            http_response_code(403);
            exit('No podés subir archivos a esa sección.');
        }
        $errorArchivoCliente = agregar_documento((int)$obra['id'], $categoria, $_POST, $_FILES['documento'] ?? [], (int)$usuario['id']);
        if ($errorArchivoCliente === null) {
            $_SESSION['aviso_cuenta'] = 'Listo, se subió el archivo.';
            redirigir('index.php?seccion=' . $categoria);
        }
    } elseif ($accion === 'eliminar_archivo_cliente' && $obra) {
        // Solo lo que subió él, y solo en sus carpetas.
        $sel = db()->prepare('SELECT * FROM documentos WHERE id = ? AND obra_id = ? AND subido_por = ?');
        $sel->execute([(int)($_POST['documento_id'] ?? 0), $obra['id'], $usuario['id']]);
        $documento = $sel->fetch();
        if ($documento && in_array($documento['categoria'], CATEGORIAS_DEL_CLIENTE, true)) {
            eliminar_documento((int)$documento['id'], (int)$obra['id']);
            $_SESSION['aviso_cuenta'] = 'Se borró el archivo.';
        }
        redirigir('index.php?seccion=' . ($documento['categoria'] ?? 'prop_archivos'));
    } elseif ($accion === 'adjuntar_comprobante' && $obra) {
        $errorComprobante = adjuntar_comprobante((int)($_POST['pago_id'] ?? 0), (int)$obra['id'], $_FILES['comprobante'] ?? [], (int)$usuario['id']);
        if ($errorComprobante === null) {
            $_SESSION['aviso_cuenta'] = 'Listo, se adjuntó el comprobante.';
            redirigir('index.php?seccion=pagos');
        }
    } elseif ($accion === 'quitar_comprobante' && $obra) {
        // Solo el comprobante que subió él; los del estudio los saca el estudio.
        $pago = pago_de_obra((int)($_POST['pago_id'] ?? 0), (int)$obra['id']);
        if ($pago && (int)$pago['comprobante_subido_por'] === (int)$usuario['id']) {
            quitar_comprobante((int)$pago['id'], (int)$obra['id']);
            $_SESSION['aviso_cuenta'] = 'Se quitó el comprobante.';
        }
        redirigir('index.php?seccion=pagos');
    } elseif ($accion === 'cambiar_nombre') {
        $errorCuenta = cambiar_nombre((int)$usuario['id'], (string)($_POST['nombre'] ?? ''));
        if ($errorCuenta === null) {
            $_SESSION['aviso_cuenta'] = 'Listo, cambiaste tu nombre.';
            redirigir('index.php?seccion=cuenta');
        }
    } elseif ($accion === 'cambiar_contrasena') {
        $errorCuenta = cambiar_contrasena((int)$usuario['id'], $_POST);
        if ($errorCuenta === null) {
            $_SESSION['aviso_cuenta'] = 'Listo, cambiaste tu contraseña.';
            redirigir('index.php?seccion=cuenta');
        }
    } elseif ($accion === 'subir_foto_perfil') {
        $errorCuenta = guardar_foto_perfil((int)$usuario['id'], $_FILES['foto'] ?? []);
        if ($errorCuenta === null) {
            $_SESSION['aviso_cuenta'] = 'Listo, actualizaste tu foto.';
            redirigir('index.php?seccion=cuenta');
        }
    } elseif ($accion === 'quitar_foto_perfil') {
        quitar_foto_perfil((int)$usuario['id']);
        $_SESSION['aviso_cuenta'] = 'Sacaste tu foto de perfil.';
        redirigir('index.php?seccion=cuenta');
    }
}

// Un aviso que viene de la redirección de arriba, para mostrarlo una sola vez.
$avisoCuenta = $_SESSION['aviso_cuenta'] ?? null;
unset($_SESSION['aviso_cuenta']);

// La bienvenida se ve una vez por ingreso: el login arma una sesión nueva.
$mostrarBienvenida = empty($_SESSION['bienvenida_vista']);
$_SESSION['bienvenida_vista'] = true;

$etapas = [];
$fotos = [];
$grupos = [];
$novedades = [];
$documentos = [];
$mensajes = [];
$conversacion = [];
$obraInfo = [];
$propietarios = [];
$pagos = [];
$contactos = [];
$archivosContactos = [];
$presupuestos = [];
$eventos = [];
$hoy = hoy_argentina();

if ($obra) {
    $stmtEtapas = db()->prepare('SELECT * FROM etapas WHERE obra_id = ? ORDER BY orden ASC, fecha ASC');
    $stmtEtapas->execute([$obra['id']]);
    $etapas = $stmtEtapas->fetchAll();

    // Dentro de cada etapa las fotos se leen en orden, como un libro.
    $stmtFotos = db()->prepare('SELECT * FROM fotos WHERE obra_id = ? ORDER BY created_at ASC, id ASC');
    $stmtFotos->execute([$obra['id']]);
    $fotos = $stmtFotos->fetchAll();

    $grupos = agrupar_fotos_por_etapa($etapas, $fotos);
    $novedades = novedades_de_obra((int)$obra['id']);
    $documentos = documentos_de_obra((int)$obra['id']);
    $mensajes = mensajes_de_obra((int)$obra['id']);
    $conversacion = historial_asistente((int)$usuario['id'], (int)$obra['id']);
    $obraInfo = obra_info((int)$obra['id']);
    $propietarios = propietarios_de_obra((int)$obra['id']);
    $pagos = pagos_de_obra((int)$obra['id']);
    $contactos = contactos_de_obra((int)$obra['id']);
    $archivosContactos = archivos_de_contactos((int)$obra['id']);
    $presupuestos = presupuestos_de_obra((int)$obra['id']);
    $eventos = eventos_de_obra($etapas, $fotos, $presupuestos, eventos_cargados_de_obra((int)$obra['id']));
}

function url_foto(string $raiz, array $obra, array $foto): string
{
    return $raiz . ruta_publica_foto((int)$obra['id'], $foto['archivo']);
}

function url_seccion(string $seccion, array $extra = [], ?string $ancla = null): string
{
    $query = $seccion === 'inicio' && !$extra ? '' : '?' . http_build_query(['seccion' => $seccion] + $extra);
    return 'index.php' . $query . ($ancla ? '#' . $ancla : '');
}

/** "01", "02"... para numerar las etapas. */
function numero_capitulo(int $n): string
{
    return str_pad((string)$n, 2, '0', STR_PAD_LEFT);
}

/** Íconos de trazo simple, heredan el color del texto. */
function icono(string $nombre): string
{
    $trazos = [
        'inicio' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
        'proyecto' => '<path d="M12 3 3 8v8l9 5 9-5V8z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'datos' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><circle cx="8.5" cy="11" r="2"/><path d="M5.5 16.5c.6-1.6 1.7-2.4 3-2.4s2.4.8 3 2.4M14 10h4.5M14 13.5h3"/>',
        'municipal' => '<path d="M3 10 12 4l9 6"/><path d="M5 10v9h14v-9M9 19v-5h6v5"/>',
        'mensajes' => '<path d="M4 5h16v11H9l-5 4z"/><circle cx="9" cy="10.5" r="1"/><circle cx="12.5" cy="10.5" r="1"/><circle cx="16" cy="10.5" r="1"/>',
        'ejecucion' => '<path d="M4 17a8 8 0 0 1 16 0"/><path d="M2.5 17h19v2.5h-19zM10 9.2V6.5h4v2.7"/>',
        'reuniones' => '<rect x="3" y="5" width="18" height="16" rx="1.5"/><path d="M3 10h18M8 3v4M16 3v4"/><circle cx="12" cy="15.5" r="2.2"/>',
        'propietarios' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5c1.2-3.9 4-5.8 7.5-5.8s6.3 1.9 7.5 5.8"/>',
        'telefonos' => '<path d="M6.6 3.5h3l1.5 4.2-2.1 1.4a11 11 0 0 0 5.9 5.9l1.4-2.1 4.2 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z"/>',
        'whatsapp' => '<path d="M4 20l1.2-3.8A8 8 0 1 1 8.1 19z"/><path d="M9.2 8.6c.2 2.6 3.4 5.9 6 6.1l1.2-1.4-2-1-.9.9c-1-.4-2.3-1.7-2.7-2.7l.9-.9-1-2z"/>',
        'calendario' => '<rect x="3" y="5" width="18" height="16" rx="1.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'asistente' => '<path d="M12 3.5 13.9 8l4.6 1.9-4.6 1.9L12 16.4l-1.9-4.6L5.5 9.9 10.1 8z"/><path d="M18.5 15.5l.8 1.9 1.9.8-1.9.8-.8 1.9-.8-1.9-1.9-.8 1.9-.8z"/>',
        'salir' => '<path d="M14 4h5v16h-5"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
    ];
    return '<svg class="icono" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
        . ($trazos[$nombre] ?? '') . '</svg>';
}

/** El círculo con la foto de perfil, o con las iniciales si no subió ninguna. */
function avatar(string $raiz, array $perfil, string $clase = ''): string
{
    $foto = ruta_foto_perfil($perfil);
    $clases = trim('cliente-avatar ' . $clase);
    if ($foto) {
        return '<span class="' . e($clases) . '"><img src="' . e($raiz . $foto) . '" alt=""></span>';
    }
    return '<span class="' . e($clases) . ' cliente-avatar--iniciales" aria-hidden="true">' . e(iniciales((string)$perfil['nombre'])) . '</span>';
}

/**
 * La imagen de la bienvenida: el render más reciente (cómo va a quedar la
 * casa) o, si todavía no hay renders, la última foto de avance, que es la
 * misma de la portada. null si la obra no tiene ninguna imagen.
 */
function imagen_bienvenida(string $raiz, ?array $obra, array $documentos, array $fotos): ?string
{
    if (!$obra) {
        return null;
    }
    // Los documentos vienen del más nuevo al más viejo: el primero que sea
    // una imagen de Fotos render o de Render es el render más reciente.
    foreach ($documentos as $documento) {
        if (in_array((string)$documento['categoria'], ['fotos_render', 'render'], true) && tipo_documento($documento['archivo']) === 'Imagen') {
            return $raiz . ruta_publica_documento((int)$obra['id'], $documento['archivo']);
        }
    }
    return $fotos ? url_foto($raiz, $obra, $fotos[count($fotos) - 1]) : null;
}

$imagenBienvenida = $mostrarBienvenida ? imagen_bienvenida($raiz, $obra ?: null, $documentos, $fotos) : null;

$titulo = $obra ? ($seccion === 'inicio' ? $obra['nombre'] : $nombreSeccion . ' · ' . $obra['nombre']) : $nombreSeccion;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= e($titulo) ?> - ARHAUS</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#f5f4f0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(recurso($raiz, 'css/cliente.css')) ?>">
</head>
<body class="cliente">
<?php if ($mostrarBienvenida): ?>
    <div class="cliente-bienvenida<?= $imagenBienvenida ? ' cliente-bienvenida--imagen' : '' ?>" role="status" aria-live="polite" data-bienvenida>
        <?php if ($imagenBienvenida): ?>
            <div class="cliente-bienvenida__imagen" aria-hidden="true">
                <img src="<?= e($imagenBienvenida) ?>" alt="" fetchpriority="high">
            </div>
        <?php endif; ?>
        <div class="cliente-bienvenida__cuerpo">
            <img class="cliente-bienvenida__logo" src="<?= e($raiz) ?>images/logo-dark.svg" alt="ARHAUS">
            <p class="cliente-bienvenida__saludo">
                <span class="cliente-bienvenida__mascara"><span>Hola,</span></span>
                <span class="cliente-bienvenida__mascara"><span><?= e(nombre_de_pila((string)$perfil['nombre'])) ?></span></span>
            </p>
            <p class="cliente-bienvenida__texto">
                <span class="cliente-bienvenida__mascara"><span><?= $obra ? e($obra['nombre']) : 'Te damos la bienvenida' ?></span></span>
            </p>
        </div>
    </div>
<?php endif; ?>
<a class="cliente-saltar" href="#contenido">Saltar al contenido</a>

<div class="cliente-app">
    <aside class="cliente-lateral">
        <a class="cliente-lateral__logo" href="<?= e(url_seccion('inicio')) ?>">
            <img src="<?= e($raiz) ?>images/logo-dark.svg" alt="ARHAUS">
        </a>

        <?php if ($obra): ?>
            <div class="cliente-lateral__obra">
                <p class="cliente-etiqueta">Tu obra</p>
                <p class="cliente-lateral__nombre"><?= e($obra['nombre']) ?></p>
                <span class="cliente-estado"><?= e(nombre_estado($obra['estado'])) ?></span>
            </div>

            <nav class="cliente-nav" aria-label="Secciones">
                <?php foreach (MENU_CLIENTE as $grupo => [$nombreGrupo, $solapas]): ?>
                    <?php $activo = $grupo === $grupoActual; ?>
                    <a class="cliente-nav__item<?= $activo ? ' is-activa' : '' ?>" href="<?= e(url_seccion(primera_seccion($solapas))) ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
                        <?= icono($grupo) ?>
                        <?php if (isset(NOMBRES_CORTOS_GRUPOS[$grupo])): ?>
                            <span class="cliente-nav__largo"><?= e($nombreGrupo) ?></span>
                            <span class="cliente-nav__corto"><?= e(NOMBRES_CORTOS_GRUPOS[$grupo]) ?></span>
                        <?php else: ?>
                            <span><?= e($nombreGrupo) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="cliente-lateral__pie">
            <a class="cliente-lateral__cuenta<?= $seccion === 'cuenta' ? ' is-activa' : '' ?>" href="<?= e(url_seccion('cuenta')) ?>">
                <?= avatar($raiz, $perfil) ?>
                <span>
                    <span class="cliente-lateral__usuario"><?= e($perfil['nombre']) ?></span>
                    <span class="cliente-lateral__mi-cuenta">Mi cuenta</span>
                </span>
            </a>
            <a href="<?= e($raiz) ?>index.html">Ver sitio</a>
            <a class="cliente-lateral__salir" href="<?= e($raiz) ?>logout.php">Salir</a>
        </div>
    </aside>

    <header class="cliente-cabecera-movil">
        <a href="<?= e(url_seccion('inicio')) ?>"><img src="<?= e($raiz) ?>images/logo-dark.svg" alt="ARHAUS"></a>
        <div class="cliente-cabecera-movil__acciones">
            <a class="cliente-cabecera-movil__cuenta" href="<?= e(url_seccion('cuenta')) ?>" aria-label="Mi cuenta"><?= avatar($raiz, $perfil) ?></a>
            <a class="cliente-cabecera-movil__salir" href="<?= e($raiz) ?>logout.php"><?= icono('salir') ?><span>Salir</span></a>
        </div>
    </header>

    <main class="cliente-main" id="contenido">
        <?php if ($seccion === 'cuenta'): ?>
            <?php include __DIR__ . '/secciones/cuenta.php'; ?>
        <?php elseif (!$obra): ?>
            <div class="cliente-vacio">
                <h1>Todavía no tenés una obra asignada</h1>
                <p>Cuando el estudio vincule tu cuenta a un proyecto, lo vas a ver acá.</p>
            </div>
        <?php else: ?>
            <?php if ($grupoActual && count(MENU_CLIENTE[$grupoActual][1]) > 1): ?>
                <nav class="cliente-subnav" aria-label="<?= e(MENU_CLIENTE[$grupoActual][0]) ?>">
                    <?php foreach (MENU_CLIENTE[$grupoActual][1] as $clave => [$nombre, $hojas]): ?>
                        <?php $activa = $clave === $solapaActual; ?>
                        <a class="cliente-subnav__item<?= $activa ? ' is-activa' : '' ?>" href="<?= e(url_seccion(array_key_first($hojas))) ?>"<?= $activa ? ' aria-current="page"' : '' ?>><?= e($nombre) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if ($solapaActual && count(MENU_CLIENTE[$grupoActual][1][$solapaActual][1]) > 1): ?>
                <nav class="cliente-subnav cliente-subnav--tercer-nivel" aria-label="<?= e(MENU_CLIENTE[$grupoActual][1][$solapaActual][0]) ?>">
                    <?php foreach (MENU_CLIENTE[$grupoActual][1][$solapaActual][1] as $clave => $nombre): ?>
                        <a class="cliente-subnav__item<?= $clave === $seccion ? ' is-activa' : '' ?>" href="<?= e(url_seccion($clave)) ?>"<?= $clave === $seccion ? ' aria-current="page"' : '' ?>><?= e($nombre) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if (isset(SECCIONES_CONTACTOS[$seccion])): ?>
                <?php $tipoContacto = SECCIONES_CONTACTOS[$seccion]; include __DIR__ . '/secciones/_contactos.php'; ?>
            <?php elseif (es_categoria_documento($seccion)): ?>
                <?php $categoriaDocumento = $seccion; include __DIR__ . '/secciones/_archivos.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/secciones/' . $seccion . '.php'; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php if ($obra && $seccion !== 'asistente'): ?>
        <?php include __DIR__ . '/_asistente_flotante.php'; ?>
    <?php endif; ?>
</div>

<?php if ($obra && (in_array($seccion, ['inicio', 'fotos', 'direccion', 'fotos_dia', 'info_obra'], true) || es_categoria_documento($seccion))): ?>
    <script src="<?= e(recurso($raiz, 'js/book-visor.js')) ?>"></script>
<?php endif; ?>
<?php if ($obra): ?>
    <?php /* El chat va en todas las páginas: en el cajón flotante, y en la
             suya propia para quien llegue sin JavaScript. */ ?>
    <script src="<?= e(recurso($raiz, 'js/asistente.js')) ?>"></script>
<?php endif; ?>
<?php if ($mostrarBienvenida): ?>
    <script src="<?= e(recurso($raiz, 'js/bienvenida.js')) ?>"></script>
<?php endif; ?>
<?php if ($obra): ?>
    <script src="<?= e(recurso($raiz, 'js/barras-arrastre.js')) ?>"></script>
<?php endif; ?>
<script>
    // En el celular las solapas se deslizan: que la activa quede a la vista.
    (function () {
        document.querySelectorAll('.cliente-subnav__item.is-activa').forEach(function (activa) {
            var barra = activa.parentNode;
            if (barra.scrollWidth <= barra.clientWidth) return;
            var r = activa.getBoundingClientRect();
            var rb = barra.getBoundingClientRect();
            barra.scrollLeft += (r.left - rb.left) - (rb.width - r.width) / 2;
        });
    })();
</script>
</body>
</html>
