<?php
declare(strict_types=1);

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/uploads.php';
require_once __DIR__ . '/../../lib/presupuestos.php';
require_once __DIR__ . '/../../lib/calendario.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'cliente');

const SECCIONES_CLIENTE = [
    'inicio' => 'Inicio',
    'fotos' => 'Fotos',
    'calendario' => 'Calendario',
    'presupuestos' => 'Presupuestos',
];

$seccion = $_GET['seccion'] ?? 'inicio';
if (!is_string($seccion) || !isset(SECCIONES_CLIENTE[$seccion])) {
    $seccion = 'inicio';
}

$stmt = db()->prepare('SELECT * FROM obras WHERE cliente_id = ? LIMIT 1');
$stmt->execute([$usuario['id']]);
$obra = $stmt->fetch();

$etapas = [];
$fotos = [];
$grupos = [];
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
        'fotos' => '<rect x="3" y="4" width="18" height="16" rx="1.5"/><circle cx="8.5" cy="9.5" r="1.8"/><path d="m21 16-5.5-5.5L5 21"/>',
        'calendario' => '<rect x="3" y="5" width="18" height="16" rx="1.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'presupuestos' => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9.5 12.5h6M9.5 16.5h6"/>',
        'salir' => '<path d="M14 4h5v16h-5"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
    ];
    return '<svg class="icono" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">'
        . ($trazos[$nombre] ?? '') . '</svg>';
}

$titulo = $obra ? ($seccion === 'inicio' ? $obra['nombre'] : SECCIONES_CLIENTE[$seccion] . ' · ' . $obra['nombre']) : 'Mi obra';
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
<link rel="stylesheet" href="<?= e($raiz) ?>css/cliente.css">
</head>
<body class="cliente">
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
                <?php foreach (SECCIONES_CLIENTE as $clave => $nombre): ?>
                    <a class="cliente-nav__item<?= $clave === $seccion ? ' is-activa' : '' ?>" href="<?= e(url_seccion($clave)) ?>"<?= $clave === $seccion ? ' aria-current="page"' : '' ?>>
                        <?= icono($clave) ?>
                        <span><?= e($nombre) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>

        <div class="cliente-lateral__pie">
            <p class="cliente-lateral__usuario"><?= e($usuario['nombre']) ?></p>
            <a href="<?= e($raiz) ?>index.html">Ver sitio</a>
            <a class="cliente-lateral__salir" href="<?= e($raiz) ?>logout.php">Salir</a>
        </div>
    </aside>

    <header class="cliente-cabecera-movil">
        <a href="<?= e(url_seccion('inicio')) ?>"><img src="<?= e($raiz) ?>images/logo-dark.svg" alt="ARHAUS"></a>
        <a class="cliente-cabecera-movil__salir" href="<?= e($raiz) ?>logout.php"><?= icono('salir') ?><span>Salir</span></a>
    </header>

    <main class="cliente-main" id="contenido">
        <?php if (!$obra): ?>
            <div class="cliente-vacio">
                <h1>Todavía no tenés una obra asignada</h1>
                <p>Cuando el estudio vincule tu cuenta a un proyecto, lo vas a ver acá.</p>
            </div>
        <?php else: ?>
            <?php include __DIR__ . '/secciones/' . $seccion . '.php'; ?>
        <?php endif; ?>
    </main>
</div>

<?php if ($obra && ($seccion === 'inicio' || $seccion === 'fotos')): ?>
    <script src="<?= e($raiz) ?>js/book-visor.js"></script>
<?php endif; ?>
</body>
</html>
