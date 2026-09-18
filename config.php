<?php
/**
 * Configuración de la base de datos.
 *
 * LOCAL (tu PC, para probar antes de subir): usa SQLite, no necesita
 * instalar ni configurar ningún servidor de base de datos.
 *
 * HOSTINGER (producción): cambiá 'driver' a 'mysql' y completá host,
 * database, user y password con los datos que te da hPanel en
 * "Bases de datos > Bases de datos MySQL".
 */

return [
    'driver' => 'sqlite', // cambiar a 'mysql' antes de subir a Hostinger

    // --- Solo se usa si driver = 'sqlite' (desarrollo local) ---
    'sqlite_path' => __DIR__ . '/database/arhaus.sqlite',

    // --- Solo se usa si driver = 'mysql' (Hostinger) ---
    'host' => 'localhost',
    'database' => 'u000000000_arhaus',
    'user' => 'u000000000_usuario',
    'password' => 'CAMBIAR_ESTO',

    // --- Asistente del panel del cliente (Claude) ---
    // La clave de la API de Anthropic. Completala SOLO en el config.php del
    // servidor, nunca en el repo. También se puede usar la variable de
    // entorno ANTHROPIC_API_KEY, que tiene prioridad.
    'anthropic_api_key' => '',
];
