<?php
/**
 * Los propietarios que cargó el cliente, para admin y arquitecto. Solo
 * lectura: los datos los mantiene el cliente desde Datos > Propietarios.
 *
 * Antes de incluirlo hay que tener definido $propietarios (de
 * propietarios_de_obra()).
 */
?>
<h2 id="propietarios">Propietarios</h2>
<p class="panel-subtitle">Los carga el cliente desde su panel (Datos &gt; Propietarios). Son datos personales: usalos solo para los trámites de la obra.</p>

<?php if (!$propietarios): ?>
    <p class="panel-vacio">El cliente todavía no cargó los datos de los propietarios.</p>
<?php else: ?>
    <div class="panel-tabla-wrap">
        <table class="panel-tabla">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>DNI</th>
                    <th>CUIT / CUIL</th>
                    <th>Nacimiento</th>
                    <th>Estado civil</th>
                    <th>Contacto</th>
                    <th>Domicilio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($propietarios as $propietario): ?>
                    <tr>
                        <td><?= e($propietario['apellido'] . ', ' . $propietario['nombre']) ?><?php if ($propietario['nacionalidad']): ?><br><span class="panel-docs__peso"><?= e($propietario['nacionalidad']) ?></span><?php endif; ?></td>
                        <td><?= e(formatear_dni((string)$propietario['dni'])) ?></td>
                        <td><?= e($propietario['cuit'] ? formatear_cuit((string)$propietario['cuit']) : '—') ?></td>
                        <td><?= e($propietario['fecha_nacimiento'] ? formatear_fecha($propietario['fecha_nacimiento']) : '—') ?></td>
                        <td><?= e($propietario['estado_civil'] ?? '—') ?></td>
                        <td>
                            <?= e($propietario['telefono'] ?? '') ?>
                            <?php if ($propietario['email']): ?><br><a href="mailto:<?= e($propietario['email']) ?>"><?= e($propietario['email']) ?></a><?php endif; ?>
                        </td>
                        <td><?= e($propietario['domicilio'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
