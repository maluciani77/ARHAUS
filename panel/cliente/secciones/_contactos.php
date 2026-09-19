<?php
/**
 * Una lista de contactos de la obra, de un tipo: los profesionales
 * (higiene y seguridad, gestor, agrimensor), los proveedores o los
 * teléfonos útiles. Los carga el estudio; el cliente los ve, los llama o
 * les escribe desde acá, y abre los archivos que tengan adjuntos.
 * panel/cliente/index.php lo incluye después de definir $tipoContacto.
 */

if (!isset($obra, $tipoContacto) || !isset(TIPOS_CONTACTO[$tipoContacto])) {
    http_response_code(404);
    exit;
}

$lista = array_values(array_filter($contactos, static fn (array $c): bool => $c['tipo'] === $tipoContacto));

$intro = [
    'higiene' => 'El profesional de higiene y seguridad de la obra, y su documentación.',
    'gestor' => 'Quién hace los trámites de la obra.',
    'agrimensor' => 'El agrimensor de la obra, con la mensura y los planos que haya presentado.',
    'proveedor' => 'Las empresas y los proveedores contratados para la obra.',
    'telefono' => 'Los teléfonos que te pueden hacer falta. Desde el celular, tocá el número para llamar.',
][$tipoContacto];
?>
<header class="cliente-cabecera">
    <p class="cliente-etiqueta"><?= e($obra['nombre']) ?></p>
    <h1><?= e($nombreSeccion) ?></h1>
    <p class="cliente-cabecera__intro"><?= e($intro) ?></p>
</header>

<?php if (!$lista): ?>
    <p class="cliente-nada">El estudio todavía no cargó <?= $tipoContacto === 'telefono' ? 'teléfonos' : 'contactos' ?> acá.</p>
<?php else: ?>
    <ul class="contactos<?= $tipoContacto === 'telefono' ? ' contactos--telefonos' : '' ?>">
        <?php foreach ($lista as $contacto): ?>
            <li class="contacto">
                <div class="contacto__cabecera">
                    <h2><?= e($contacto['nombre']) ?></h2>
                    <?php if ($contacto['empresa'] || $contacto['rubro']): ?>
                        <p class="contacto__rol"><?= e(implode(' · ', array_filter([$contacto['rubro'], $contacto['empresa']]))) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($contacto['notas']): ?>
                    <p class="contacto__notas"><?= nl2br(e($contacto['notas'])) ?></p>
                <?php endif; ?>

                <div class="contacto__acciones">
                    <?php if ($contacto['telefono']): ?>
                        <a class="cliente-boton" href="<?= e(enlace_telefono($contacto['telefono'])) ?>">
                            <?= icono('telefonos') ?> <?= e($contacto['telefono']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if ($contacto['email']): ?>
                        <a class="cliente-boton cliente-boton--secundario" href="mailto:<?= e($contacto['email']) ?>"><?= e($contacto['email']) ?></a>
                    <?php endif; ?>
                </div>

                <?php if (!empty($archivosContactos[(int)$contacto['id']])): ?>
                    <ul class="book-archivos contacto__archivos">
                        <?php foreach ($archivosContactos[(int)$contacto['id']] as $archivo): ?>
                            <li class="book-archivo">
                                <a href="<?= e($raiz . ruta_publica_documento((int)$obra['id'], $archivo['archivo'])) ?>" target="_blank" rel="noopener">
                                    <span class="book-archivo__tipo"><?= e(tipo_documento($archivo['archivo'])) ?></span>
                                    <span class="book-archivo__nombre"><?= e($archivo['titulo']) ?></span>
                                    <span class="book-archivo__dato"><?= e(tamano_legible((int)$archivo['tamano'])) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
