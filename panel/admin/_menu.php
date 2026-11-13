<?php
declare(strict_types=1);

/**
 * El mapa del panel del estudio.
 *
 * Adentro de una obra las secciones son LAS MISMAS que ve el cliente, y
 * en el mismo orden. No es un capricho estético: cuando el cliente
 * escribe "no encuentro los planos", el que atiende se para en el mismo
 * lugar del mismo árbol y lo ve. Antes había que traducir entre dos
 * mapas distintos.
 *
 * Cada hoja dice qué bloque de panel/_*.php se dibuja y con qué filtro.
 * Los bloques no se tocaron: _documentos.php ya aceptaba una lista de
 * categorías (la usaba el director de obra), y a _contactos.php se le
 * agregó la misma idea para los tipos.
 */

/** Lo que se ve cuando no se está adentro de una obra. */
const ADMIN_MENU_GENERAL = [
    'obras' => ['Obras', 'index.php', 'obra'],
    'usuarios' => ['Usuarios', 'usuarios.php', 'personas'],
    'accesos' => ['Tarjetas NFC', 'accesos.php', 'tarjeta'],
    'maqueta' => ['Maqueta 3D', 'maqueta.php', 'cubo'],
    'tarjetas' => ['Tarjetas de renders', 'tarjetas.php', 'imagen'],
];

/**
 * Las secciones de una obra: grupo => [nombre, ícono, hojas].
 * Cada hoja: clave => [nombre, bloques, filtro].
 *   - bloques: archivos de panel/ que se incluyen, en orden.
 *   - filtro: categorías de documentos o tipos de contacto, o null.
 */
const ADMIN_MENU_OBRA = [
    'datos' => ['Datos de la obra', 'ficha', [
        'datos' => ['Datos de la obra', [], null],
    ]],
    'reuniones' => ['Reuniones', 'reuniones', [
        'calendario' => ['Calendario', ['_calendario_obra'], null],
    ]],
    'propietarios' => ['Propietarios', 'propietarios', [
        'propietarios' => ['Datos personales', ['_propietarios'], null],
        'obra_info' => ['Obra info', ['_obra_info'], null],
        'pagos' => ['Pagos', ['_pagos'], null],
        'prop_archivos' => ['Archivos', ['_documentos'],
            ['prop_archivos', 'contrato', 'referentes', 'info_varios']],
    ]],
    'proyecto' => ['Proyecto', 'proyecto', [
        'planos' => ['Planos', ['_documentos'],
            ['planos_arq', 'planos_electricos', 'planos_sanitarios', 'planos_carpinteria',
             'planos_cielorrasos', 'plano_estructura', 'planos_amoblamiento',
             'planos_municipales', 'demolicion', 'arq_varios']],
        'visuales' => ['Visuales', ['_documentos'], ['brochure', 'render', 'videos']],
        'presup_proy' => ['Presupuestos', ['_documentos'],
            ['computo', 'presupuesto_proy', 'planificacion_gantt', 'obra_otros']],
        'gestion' => ['Gestión', ['_contactos'],
            ['gestor', 'ingeniero', 'administracion', 'proveedor']],
    ]],
    'ejecucion' => ['Ejecución de obra', 'ejecucion', [
        'direccion' => ['Dirección de obra', ['_novedades'], null],
        'fotos' => ['Fotos', ['_galeria_etapas', '_form_fotos'], null],
        'etapas' => ['Etapas', ['_etapas'], null],
        'presupuestos' => ['Presupuestos', ['_presupuestos'], null],
        'ejec_archivos' => ['Archivos', ['_documentos'],
            ['informes', 'planificacion', 'ejec_archivos', 'relevamiento', 'desmalezamiento']],
        'profesionales' => ['Profesionales', ['_contactos'],
            ['higiene', 'gestor', 'agrimensor', 'proveedor']],
    ]],
    'telefonos' => ['Teléfonos útiles', 'telefonos', [
        'telefonos' => ['Teléfonos útiles', ['_contactos'], ['telefono']],
    ]],
    'mensajes' => ['Mensajes', 'mensajes', [
        'mensajes' => ['Mensajes', ['_mensajes'], null],
    ]],
];

/** Todas las hojas aplanadas: clave => [nombre, grupo, bloques, filtro]. */
function admin_hojas(): array
{
    static $hojas = null;
    if ($hojas !== null) {
        return $hojas;
    }
    $hojas = [];
    foreach (ADMIN_MENU_OBRA as $grupo => [, , $suyas]) {
        foreach ($suyas as $clave => [$nombre, $bloques, $filtro]) {
            $hojas[$clave] = [$nombre, $grupo, $bloques, $filtro];
        }
    }
    return $hojas;
}

/** Los mismos íconos de trazo que usa el panel del cliente. */
function icono_admin(string $nombre): string
{
    $trazos = [
        'obra' => '<path d="M3 10 12 4l9 6"/><path d="M5 10v9h14v-9M9 19v-5h6v5"/>',
        'personas' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c.9-3.2 3.1-4.8 6-4.8s5.1 1.6 6 4.8"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6M18.5 20c-.3-1.3-.8-2.4-1.5-3.2"/>',
        'tarjeta' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M3 10h18M7 14.5h4"/>',
        'cubo' => '<path d="M12 3 3 8v8l9 5 9-5V8z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'imagen' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><circle cx="8.5" cy="10" r="1.6"/><path d="m4 17 5-4 4 3 3-2.5 4 3.5"/>',
        'ficha' => '<rect x="4" y="3" width="16" height="18" rx="1.5"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'reuniones' => '<rect x="3" y="5" width="18" height="16" rx="1.5"/><path d="M3 10h18M8 3v4M16 3v4"/><circle cx="12" cy="15.5" r="2.2"/>',
        'propietarios' => '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5c1.2-3.9 4-5.8 7.5-5.8s6.3 1.9 7.5 5.8"/>',
        'proyecto' => '<path d="M12 3 3 8v8l9 5 9-5V8z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'ejecucion' => '<path d="M4 17a8 8 0 0 1 16 0"/><path d="M2.5 17h19v2.5h-19zM10 9.2V6.5h4v2.7"/>',
        'telefonos' => '<path d="M6.6 3.5h3l1.5 4.2-2.1 1.4a11 11 0 0 0 5.9 5.9l1.4-2.1 4.2 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.6 5.7a2 2 0 0 1 2-2.2z"/>',
        'mensajes' => '<path d="M4 5h16v11H9l-5 4z"/><circle cx="9" cy="10.5" r="1"/><circle cx="12.5" cy="10.5" r="1"/><circle cx="16" cy="10.5" r="1"/>',
        'volver' => '<path d="M19 12H5.5M11.5 6 5.5 12l6 6"/>',
        'ojo' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12"/><circle cx="12" cy="12" r="2.8"/>',
    ];

    return '<svg class="icono" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($trazos[$nombre] ?? '') . '</svg>';
}
