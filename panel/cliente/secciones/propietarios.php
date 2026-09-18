<?php
/**
 * Sección Datos > Propietarios del panel del cliente: el cliente carga los
 * datos de cada propietario de la obra (DNI, CUIT, fecha de nacimiento...)
 * para los trámites. Puede haber más de uno.
 * Se incluye desde panel/cliente/index.php.
 */

if (!isset($obra)) {
    http_response_code(404);
    exit;
}

// ?editar=ID abre el formulario con ese propietario; si volvió con error, se
// muestran otra vez los datos que había escrito.
$editandoId = (int)($_GET['editar'] ?? $_POST['propietario_id'] ?? 0);
$enEdicion = null;
foreach ($propietarios as $propietario) {
    if ((int)$propietario['id'] === $editandoId) {
        $enEdicion = $propietario;
    }
}
$reenvio = ($_POST['accion'] ?? '') === 'guardar_propietario' ? $_POST : null;
$valor = static function (string $campo) use ($reenvio, $enEdicion): string {
    if ($reenvio !== null) {
        return (string)($reenvio[$campo] ?? '');
    }
    return (string)($enEdicion[$campo] ?? '');
};
$mostrarFormulario = $enEdicion || $reenvio !== null || !$propietarios || isset($_GET['agregar']);
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1>Propietarios</h1>
    <p class="cliente-cabecera__intro">Los datos de quienes figuran como dueños de la obra. El estudio los necesita para los trámites. Solo los ven vos y el estudio.</p>
</header>

<?php if ($avisoCuenta): ?>
    <p class="cliente-aviso" role="status"><?= e($avisoCuenta) ?></p>
<?php endif; ?>

<?php if ($propietarios): ?>
    <ul class="propietarios">
        <?php foreach ($propietarios as $propietario): ?>
            <li class="propietario">
                <div class="propietario__cabecera">
                    <h2><?= e($propietario['nombre'] . ' ' . $propietario['apellido']) ?></h2>
                    <div class="propietario__acciones">
                        <a class="cliente-boton cliente-boton--secundario" href="<?= e(url_seccion('propietarios', ['editar' => (int)$propietario['id']], 'formulario-propietario')) ?>">Editar</a>
                        <form method="post" action="<?= e(url_seccion('propietarios')) ?>" onsubmit="return confirm('¿Quitar este propietario? Se borran sus datos.');">
                            <?= campo_csrf() ?>
                            <input type="hidden" name="accion" value="eliminar_propietario">
                            <input type="hidden" name="propietario_id" value="<?= (int)$propietario['id'] ?>">
                            <button type="submit" class="cliente-boton cliente-boton--texto">Quitar</button>
                        </form>
                    </div>
                </div>
                <dl class="ficha__lista">
                    <div class="ficha__dato"><dt>DNI</dt><dd><?= e(formatear_dni((string)$propietario['dni'])) ?></dd></div>
                    <?php if ($propietario['cuit']): ?><div class="ficha__dato"><dt>CUIT / CUIL</dt><dd><?= e(formatear_cuit((string)$propietario['cuit'])) ?></dd></div><?php endif; ?>
                    <?php if ($propietario['fecha_nacimiento']): ?><div class="ficha__dato"><dt>Fecha de nacimiento</dt><dd><?= e(formatear_fecha($propietario['fecha_nacimiento'])) ?></dd></div><?php endif; ?>
                    <?php foreach (['nacionalidad', 'estado_civil', 'domicilio', 'telefono', 'email'] as $campo): ?>
                        <?php if ($propietario[$campo]): ?>
                            <div class="ficha__dato"><dt><?= e(CAMPOS_PROPIETARIO[$campo]) ?></dt><dd><?= e($propietario[$campo]) ?></dd></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$mostrarFormulario): ?>
        <a class="cliente-boton" href="<?= e(url_seccion('propietarios', ['agregar' => 1], 'formulario-propietario')) ?>">Agregar otro propietario</a>
    <?php endif; ?>
<?php endif; ?>

<?php if ($mostrarFormulario): ?>
    <section class="cliente-bloque" id="formulario-propietario" aria-labelledby="titulo-propietario">
        <h2 id="titulo-propietario"><?= $enEdicion ? 'Editar a ' . e($enEdicion['nombre']) : ($propietarios ? 'Agregar otro propietario' : 'Cargá tus datos') ?></h2>

        <?php if ($errorPropietario): ?>
            <p class="cliente-alerta"><?= e($errorPropietario) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(url_seccion('propietarios')) ?>#formulario-propietario" class="cliente-form cliente-form--grilla">
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="guardar_propietario">
            <input type="hidden" name="propietario_id" value="<?= (int)($enEdicion['id'] ?? 0) ?>">

            <label class="cliente-campo">
                <span class="cliente-etiqueta">Nombre *</span>
                <input type="text" name="nombre" value="<?= e($valor('nombre')) ?>" required autocomplete="given-name">
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">Apellido *</span>
                <input type="text" name="apellido" value="<?= e($valor('apellido')) ?>" required autocomplete="family-name">
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">DNI *</span>
                <input type="text" name="dni" value="<?= e($valor('dni')) ?>" required inputmode="numeric" placeholder="Sin puntos">
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">CUIT / CUIL</span>
                <input type="text" name="cuit" value="<?= e($valor('cuit') !== '' && $reenvio === null ? formatear_cuit($valor('cuit')) : $valor('cuit')) ?>" inputmode="numeric" placeholder="20-12345678-9">
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">Fecha de nacimiento</span>
                <input type="date" name="fecha_nacimiento" value="<?= e($valor('fecha_nacimiento')) ?>" max="<?= e(date('Y-m-d')) ?>" autocomplete="bday">
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">Nacionalidad</span>
                <input type="text" name="nacionalidad" value="<?= e($valor('nacionalidad')) ?>" placeholder="Argentina">
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">Estado civil</span>
                <select name="estado_civil">
                    <option value="">—</option>
                    <?php foreach (ESTADOS_CIVILES as $estado): ?>
                        <option value="<?= e($estado) ?>" <?= $valor('estado_civil') === $estado ? 'selected' : '' ?>><?= e($estado) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="cliente-campo">
                <span class="cliente-etiqueta">Teléfono</span>
                <input type="tel" name="telefono" value="<?= e($valor('telefono')) ?>" autocomplete="tel">
            </label>
            <label class="cliente-campo cliente-campo--ancho">
                <span class="cliente-etiqueta">Domicilio real</span>
                <input type="text" name="domicilio" value="<?= e($valor('domicilio')) ?>" autocomplete="street-address">
            </label>
            <label class="cliente-campo cliente-campo--ancho">
                <span class="cliente-etiqueta">Email</span>
                <input type="email" name="email" value="<?= e($valor('email')) ?>" autocomplete="email">
            </label>

            <div class="cliente-form__acciones">
                <button type="submit" class="cliente-boton">Guardar</button>
                <?php if ($propietarios): ?>
                    <a class="cliente-boton cliente-boton--texto" href="<?= e(url_seccion('propietarios')) ?>">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
<?php endif; ?>
