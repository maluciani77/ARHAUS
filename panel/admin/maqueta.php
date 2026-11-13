<?php
declare(strict_types=1);

/**
 * Editor de la maqueta 3D del Lote 424 (San Ramiro, Pilar del Este).
 *
 * El admin retoca los colores, la luz y la vista inicial, y al guardar se
 * escribe maquetas/lote-424/config.json. La página pública del proyecto
 * (pr_3.html) lee ese archivo, así que lo que se guarda acá es lo que ve
 * el visitante. El modelo (maqueta.glb) no se modifica.
 */

require_once __DIR__ . '/../../lib/auth.php';
require_once __DIR__ . '/../../lib/helpers.php';
require_once __DIR__ . '/../../lib/csrf.php';

$raiz = '../../';
$usuario = requerir_rol($raiz, 'admin');

const MAQUETA_DIR = __DIR__ . '/../../maquetas/lote-424';
const LUCES_VALIDAS = ['maqueta' => 'Maqueta (recomendada)', 'suave' => 'Día suave', 'marcada' => 'Sol marcado', 'estudio' => 'Estudio'];
const PARTES_PALETA = [
    'revoque' => 'Muros / revoque',
    'techo' => 'Techo',
    'carpinteria' => 'Carpintería y metales',
    'madera' => 'Madera',
    'vidrio' => 'Vidrios',
];
const POR_DEFECTO = [
    'paleta' => ['revoque' => '#f2ece2', 'techo' => '#bdb9b1', 'carpinteria' => '#2c2c2c', 'madera' => '#d9b98f', 'vidrio' => '#dde5e6'],
    'luz' => 'maqueta',
    'brillo' => 1.0,
    'camara' => ['orbita' => '22deg 80deg 45%', 'fov' => '30deg'],
];

function leer_config(): array
{
    $ruta = MAQUETA_DIR . '/config.json';
    $guardado = is_file($ruta) ? json_decode((string)file_get_contents($ruta), true) : null;
    if (!is_array($guardado)) {
        return POR_DEFECTO;
    }
    return [
        'paleta' => array_merge(POR_DEFECTO['paleta'], array_filter((array)($guardado['paleta'] ?? []), 'is_string')),
        'luz' => isset(LUCES_VALIDAS[$guardado['luz'] ?? '']) ? $guardado['luz'] : POR_DEFECTO['luz'],
        'brillo' => (float)($guardado['brillo'] ?? POR_DEFECTO['brillo']),
        'camara' => array_merge(POR_DEFECTO['camara'], array_filter((array)($guardado['camara'] ?? []), 'is_string')),
        'actualizado' => $guardado['actualizado'] ?? null,
        'por' => $guardado['por'] ?? null,
    ];
}

/** Devuelve el mensaje de error, o null si guardó bien. */
function guardar_config(array $datos, string $nombreUsuario): ?string
{
    $paleta = [];
    foreach (PARTES_PALETA as $parte => $titulo) {
        $color = strtolower(trim((string)($datos['paleta'][$parte] ?? '')));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            return 'El color de "' . $titulo . '" no es válido.';
        }
        $paleta[$parte] = $color;
    }

    $luz = (string)($datos['luz'] ?? '');
    if (!isset(LUCES_VALIDAS[$luz])) {
        return 'Elegí una de las luces disponibles.';
    }

    $brillo = (float)($datos['brillo'] ?? 1);
    if ($brillo < 0.6 || $brillo > 1.5) {
        return 'El brillo tiene que estar entre 0,60 y 1,50.';
    }

    $orbita = trim((string)($datos['camara']['orbita'] ?? ''));
    $fov = trim((string)($datos['camara']['fov'] ?? ''));
    if (!preg_match('/^-?[\d.]+deg -?[\d.]+deg [\d.]+%?m?$/', $orbita) || !preg_match('/^[\d.]+deg$/', $fov)) {
        return 'La vista inicial no es válida. Acomodá la maqueta y tocá "Usar esta vista".';
    }

    if (!is_dir(MAQUETA_DIR) && !mkdir(MAQUETA_DIR, 0775, true)) {
        return 'No se pudo crear la carpeta maquetas/lote-424 en el servidor.';
    }

    $json = json_encode([
        'paleta' => $paleta,
        'luz' => $luz,
        'brillo' => round($brillo, 2),
        'camara' => ['orbita' => $orbita, 'fov' => $fov],
        'actualizado' => (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d H:i'),
        'por' => $nombreUsuario,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if (file_put_contents(MAQUETA_DIR . '/config.json', $json) === false) {
        return 'No se pudo escribir el archivo. Revisá los permisos de la carpeta maquetas/ en el servidor.';
    }
    return null;
}

$error = null;
$guardado = isset($_GET['ok']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    if (($_POST['accion'] ?? '') === 'restablecer') {
        $error = guardar_config(POR_DEFECTO, $usuario['nombre']);
    } else {
        $error = guardar_config($_POST, $usuario['nombre']);
    }
    if ($error === null) {
        redirigir('maqueta.php?ok=1');
    }
}

$config = leer_config();
$hayModelo = is_file(MAQUETA_DIR . '/maqueta.glb');
?>
<?php
$navActiva = 'maqueta';
$tituloPagina = 'Maqueta 3D';
include __DIR__ . '/_cabecera.php';
?>
<link rel="stylesheet" href="<?= e(recurso($raiz, 'css/maqueta-editor.css')) ?>

    <h1>Maqueta 3D</h1>
    <p class="panel-subtitle">
        House 424 · San Ramiro, Pilar del Este ·
        <a href="<?= e($raiz) ?>proyecto-pilar.html#maqueta" target="_blank" rel="noopener">ver la página del proyecto</a>
    </p>

    <p class="panel-hint">
        Ojo: la página del proyecto hoy muestra la <strong>versión en blanco y negro</strong>, con la luz
        de adentro prendida. De todo lo de acá abajo, lo único que le llega es <strong>la vista inicial</strong>
        (y el brillo). Los colores y la luz siguen guardándose, pero por ahora no se ven en la web.
    </p>

    <?php if ($guardado): ?><p class="panel-alert panel-alert--ok">Listo: la maqueta del proyecto quedó con estos colores.</p><?php endif; ?>
    <?php if ($error): ?><p class="panel-alert panel-alert--error"><?= e($error) ?></p><?php endif; ?>

    <?php if (!$hayModelo): ?>
        <p class="panel-alert panel-alert--error">
            Falta subir el modelo en <code>maquetas/lote-424/maqueta.glb</code>. Hasta que esté, no se puede previsualizar.
        </p>
    <?php else: ?>
        <div class="maqueta-editor">
            <div class="maqueta-editor__visor">
                <model-viewer
                    id="visor"
                    src="<?= e(recurso($raiz, 'maquetas/lote-424/maqueta.glb')) ?>"
                    alt="Maqueta 3D de House 424"
                    camera-controls
                    touch-action="pan-y"
                    interaction-prompt="none"
                    environment-image="neutral"
                    tone-mapping="neutral"
                    shadow-intensity="1"
                    shadow-softness="0.85"
                    exposure="1"
                    min-field-of-view="15deg"
                    max-field-of-view="45deg">
                    <div class="maqueta-cargando" slot="progress-bar">Cargando la maqueta…</div>
                </model-viewer>
            </div>

            <form method="post" class="panel-form maqueta-editor__form" id="formulario">
                <?= campo_csrf() ?>

                <p class="panel-hint">Los cambios se ven al instante en la maqueta. Recién quedan publicados cuando tocás <b>Guardar</b>.</p>

                <div>
                    <p class="panel-etiqueta-chica">Combinaciones listas</p>
                    <div class="maqueta-presets" id="presets"></div>
                </div>

                <?php foreach (PARTES_PALETA as $parte => $titulo): ?>
                    <label class="maqueta-color">
                        <span><?= e($titulo) ?></span>
                        <input type="color" name="paleta[<?= $parte ?>]" value="<?= e($config['paleta'][$parte]) ?>" data-parte="<?= $parte ?>">
                    </label>
                <?php endforeach; ?>

                <label>Luz
                    <select name="luz" id="luz">
                        <?php foreach (LUCES_VALIDAS as $clave => $nombre): ?>
                            <option value="<?= $clave ?>" <?= $clave === $config['luz'] ? 'selected' : '' ?>><?= e($nombre) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Brillo: <b id="valorBrillo"><?= number_format($config['brillo'], 2) ?></b>
                    <input type="range" name="brillo" id="brillo" min="0.6" max="1.5" step="0.05" value="<?= e((string)$config['brillo']) ?>">
                </label>

                <div>
                    <p class="panel-etiqueta-chica">Vista inicial</p>
                    <p class="panel-hint">Girá y acercá la maqueta hasta donde querés que arranque, y tocá el botón.</p>
                    <button type="button" class="panel-btn panel-btn--secundario" id="btnVista">Usar esta vista</button>
                    <input type="hidden" name="camara[orbita]" id="orbita" value="<?= e($config['camara']['orbita']) ?>">
                    <input type="hidden" name="camara[fov]" id="fov" value="<?= e($config['camara']['fov']) ?>">
                    <p class="panel-hint" id="vistaActual"><?= e($config['camara']['orbita']) ?> · <?= e($config['camara']['fov']) ?></p>
                </div>

                <div class="panel-actions">
                    <button type="submit">Guardar</button>
                    <button type="submit" name="accion" value="restablecer" class="panel-btn panel-btn--secundario"
                            onclick="return confirm('¿Volver a los colores originales?');">Restablecer</button>
                </div>

                <?php if (!empty($config['actualizado'])): ?>
                    <p class="panel-hint">Última vez guardado: <?= e($config['actualizado']) ?><?= $config['por'] ? ' por ' . e($config['por']) : '' ?>.</p>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>
</main>

<?php if ($hayModelo): ?>
<script type="module" src="https://cdn.jsdelivr.net/npm/@google/model-viewer@3.5.0/dist/model-viewer.min.js"></script>
<script src="<?= e(recurso($raiz, 'js/maqueta-opciones.js')) ?>"></script>
<script>
(function () {
	var visor = document.getElementById('visor');
	var formulario = document.getElementById('formulario');
	var materiales = null;

	function configActual() {
		var paleta = {};
		formulario.querySelectorAll('input[data-parte]').forEach(function (i) { paleta[i.dataset.parte] = i.value; });
		return {
			paleta: paleta,
			luz: document.getElementById('luz').value,
			brillo: parseFloat(document.getElementById('brillo').value)
		};
	}

	function refrescar() {
		if (!materiales) return;
		Maqueta3D.aplicar(visor, materiales, configActual());
	}

	visor.addEventListener('load', function () {
		materiales = Maqueta3D.leerMateriales(visor);
		Maqueta3D.ubicarCamara(visor, { camara: { orbita: document.getElementById('orbita').value, fov: document.getElementById('fov').value } });
		refrescar();
	});

	formulario.addEventListener('input', function (e) {
		if (e.target.id === 'brillo') {
			document.getElementById('valorBrillo').textContent = parseFloat(e.target.value).toFixed(2);
		}
		refrescar();
	});

	// Combinaciones listas: cargan los colores en los selectores
	var presets = document.getElementById('presets');
	Maqueta3D.PALETAS.forEach(function (p) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'maqueta-preset';
		b.title = p.nombre;
		b.innerHTML = '<span class="maqueta-preset__tiras">' + [p.revoque, p.techo, p.carpinteria, p.madera].map(function (c) {
			return '<span style="background:' + c + '"></span>';
		}).join('') + '</span><em>' + p.nombre + '</em>';
		b.addEventListener('click', function () {
			formulario.querySelectorAll('input[data-parte]').forEach(function (i) { i.value = p[i.dataset.parte]; });
			refrescar();
		});
		presets.appendChild(b);
	});

	document.getElementById('btnVista').addEventListener('click', function () {
		var o = visor.getCameraOrbit();
		var redondo = function (n) { return Math.round(n * 10) / 10; };
		var orbita = redondo(o.theta * 180 / Math.PI) + 'deg ' + redondo(o.phi * 180 / Math.PI) + 'deg ' + redondo(o.radius) + 'm';
		var fov = redondo(visor.getFieldOfView()) + 'deg';
		document.getElementById('orbita').value = orbita;
		document.getElementById('fov').value = fov;
		document.getElementById('vistaActual').textContent = orbita + ' · ' + fov;
	});
})();
</script>
<?php endif; ?>
</body>
</html>
