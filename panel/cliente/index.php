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

$raiz = '../../';
$usuario = requerir_rol($raiz, 'cliente');

/**
 * Los botones del panel: cada grupo tiene sus solapas, que se ven arriba
 * de la página cuando el grupo tiene más de una. La clave de cada solapa
 * es el ?seccion= de la URL.
 */
const GRUPOS_CLIENTE = [
    'proyecto' => ['Proyecto', [
        'anteproyecto' => 'Anteproyecto',
        'render' => 'Render',
        'fotos_render' => 'Fotos render',
    ]],
    'datos' => ['Datos', [
        'archivos' => 'Archivos',
        'obra_info' => 'Obra info',
        'propietarios' => 'Propietarios',
    ]],
    'municipal' => ['Municipal', [
        'planos_aprobados' => 'Planos aprobados',
        'planos_en_proceso' => 'Planos en proceso',
    ]],
    'mensajes' => ['Mensajes', [
        'mensajes' => 'Mensajes',
    ]],
    'ejecucion' => ['Ejecución de obra', [
        'direccion' => 'Dirección de obra',
        'fotos' => 'Fotos de obra',
        'planificacion' => 'Planificación',
        'etapas' => 'Etapa de obra',
        'presupuestos' => 'Presupuestos',
    ]],
    'calendario' => ['Calendario', [
        'calendario' => 'Calendario',
    ]],
];

/** Nombres más cortos para la barra de abajo del celular, donde no entran los largos. */
const NOMBRES_CORTOS_GRUPOS = [
    'ejecucion' => 'Ejecución',
];

/** Páginas que no son un botón: Inicio (el logo), el asistente (botón flotante) y Mi cuenta (la foto). */
const SECCIONES_SUELTAS = [
    'inicio' => 'Inicio',
    'asistente' => 'Asistente',
    'cuenta' => 'Mi cuenta',
];

/** Todas las secciones: clave => [nombre, grupo o null]. */
function secciones_cliente(): array
{
    $secciones = [];
    foreach (GRUPOS_CLIENTE as $grupo => [, $solapas]) {
        foreach ($solapas as $clave => $nombre) {
            $secciones[$clave] = [$nombre, $grupo];
        }
    }
    foreach (SECCIONES_SUELTAS as $clave => $nombre) {
        $secciones[$clave] = [$nombre, null];
    }
    return $secciones;
}

$seccion = $_GET['seccion'] ?? 'inicio';
if (!is_string($seccion) || !isset(secciones_cliente()[$seccion])) {
    $seccion = 'inicio';
}
[$nombreSeccion, $grupoActual] = secciones_cliente()[$seccion];

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
                <?php foreach (GRUPOS_CLIENTE as $grupo => [$nombreGrupo, $solapas]): ?>
                    <?php $activo = $grupo === $grupoActual; ?>
                    <a class="cliente-nav__item<?= $activo ? ' is-activa' : '' ?>" href="<?= e(url_seccion(array_key_first($solapas))) ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
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
            <?php if ($grupoActual && count(GRUPOS_CLIENTE[$grupoActual][1]) > 1): ?>
                <nav class="cliente-subnav" aria-label="<?= e(GRUPOS_CLIENTE[$grupoActual][0]) ?>">
                    <?php foreach (GRUPOS_CLIENTE[$grupoActual][1] as $clave => $nombre): ?>
                        <a class="cliente-subnav__item<?= $clave === $seccion ? ' is-activa' : '' ?>" href="<?= e(url_seccion($clave)) ?>"<?= $clave === $seccion ? ' aria-current="page"' : '' ?>><?= e($nombre) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if (es_categoria_documento($seccion)): ?>
                <?php $categoriaDocumento = $seccion; include __DIR__ . '/secciones/_archivos.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/secciones/' . $seccion . '.php'; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <?php if ($obra && $seccion !== 'asistente'): ?>
        <a class="cliente-asistente-flotante" href="<?= e(url_seccion('asistente')) ?>">
            <?= icono('asistente') ?>
            <span>Asistente</span>
        </a>
    <?php endif; ?>
</div>

<?php if ($obra && (in_array($seccion, ['inicio', 'fotos', 'direccion'], true) || es_categoria_documento($seccion))): ?>
    <script src="<?= e(recurso($raiz, 'js/book-visor.js')) ?>"></script>
<?php endif; ?>
<?php if ($obra && $seccion === 'asistente'): ?>
    <script src="<?= e(recurso($raiz, 'js/asistente.js')) ?>"></script>
<?php endif; ?>
<?php if ($mostrarBienvenida): ?>
    <script src="<?= e(recurso($raiz, 'js/bienvenida.js')) ?>"></script>
<?php endif; ?>
<script>
    // En el celular las solapas se deslizan: que la activa quede a la vista.
    (function () {
        var activa = document.querySelector('.cliente-subnav__item.is-activa');
        if (!activa) return;
        var barra = activa.parentNode;
        if (barra.scrollWidth <= barra.clientWidth) return;
        var r = activa.getBoundingClientRect();
        var rb = barra.getBoundingClientRect();
        barra.scrollLeft += (r.left - rb.left) - (rb.width - r.width) / 2;
    })();
</script>
</body>
</html>
