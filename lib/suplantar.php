<?php
declare(strict_types=1);

/**
 * VER COMO (suplantación de identidad)
 *
 * El problema que resuelve: con una obra por cliente y varios clientes a
 * la vez, mirar el panel de cada uno desde afuera -adivinando qué ve- se
 * mezcla. Con esto el admin entra al panel del cliente y ve exactamente
 * lo que ve él, sin contraseñas de por medio.
 *
 * Tres decisiones que hacen que esto sea seguro:
 *
 * 1. NO HAY CONTRASEÑA NUEVA. Se usa la sesión de admin que ya está
 *    abierta. Una contraseña maestra sería una sola cadena que abre todas
 *    las cuentas, viaja por WhatsApp, queda en un papel y no se puede dar
 *    de baja sin cambiarla en todas partes. Acá, si se compromete la
 *    cuenta del admin se cambia esa contraseña y listo.
 *
 * 2. ES DE SOLO LECTURA. Mientras dura, el sitio rechaza cualquier POST
 *    (ver requerir_login() en auth.php). Así el admin no puede, sin
 *    querer, contestar un mensaje o marcar un pago haciéndose pasar por
 *    el cliente: lo que queda escrito en la base lo escribió el cliente.
 *
 * 3. QUEDA REGISTRADO. Cada vez que se entra y se sale se anota en la
 *    tabla suplantaciones: quién, a quién y cuándo.
 *
 * No se puede suplantar a otro admin: así nadie usa esto para quedarse
 * con la cuenta de un socio.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** Los roles a los que se le puede entrar. Los admin no están, a propósito. */
const SUPLANTAR_ROLES = ['cliente', 'arquitecto', 'director'];

/**
 * Entra al panel de otro usuario.
 * Devuelve null si salió bien, o el motivo del rechazo.
 */
function suplantar_iniciar(array $admin, int $destinoId): ?string
{
    if (($admin['rol'] ?? '') !== 'admin') {
        return 'Solo un administrador puede hacer esto.';
    }
    if ($destinoId === (int)$admin['id']) {
        return 'Esa ya es tu cuenta.';
    }

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$destinoId]);
    $destino = $stmt->fetch();

    if (!$destino) {
        return 'Esa cuenta no existe.';
    }
    if (!in_array($destino['rol'], SUPLANTAR_ROLES, true)) {
        return 'No se puede entrar a la cuenta de otro administrador.';
    }

    iniciar_sesion_segura();

    // Si ya estaba viendo como alguien, el admin de verdad sigue siendo
    // el de la primera vez: así no se puede encadenar una suplantación
    // adentro de otra para terminar en una cuenta que no correspondía.
    $real = suplantacion_activa()['admin'] ?? [
        'id' => (int)$admin['id'],
        'nombre' => (string)$admin['nombre'],
        'rol' => (string)$admin['rol'],
    ];

    $registroId = suplantar_anotar((int)$real['id'], (int)$destino['id']);

    // session_regenerate_id() de iniciar_login() conserva los datos de la
    // sesión, así que el rastro del admin sobrevive al cambio.
    iniciar_login($destino);
    $_SESSION['suplantando'] = [
        'admin' => $real,
        'registro_id' => $registroId,
        'desde' => time(),
    ];

    return null;
}

/**
 * Vuelve a la cuenta del admin. Devuelve true si había algo que cerrar.
 * Si la cuenta del admin ya no existe, cierra la sesión por las dudas.
 */
function suplantar_terminar(): bool
{
    $activa = suplantacion_activa();
    if (!$activa) {
        return false;
    }

    suplantar_cerrar_registro((int)($activa['registro_id'] ?? 0));

    $stmt = db()->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$activa['admin']['id']]);
    $admin = $stmt->fetch();

    unset($_SESSION['suplantando']);

    if (!$admin || $admin['rol'] !== 'admin') {
        cerrar_sesion();
        return true;
    }

    iniciar_login($admin);
    return true;
}

/* ========================================
   EL REGISTRO
   ======================================== */

/** Anota que empezó una suplantación. Devuelve el id de la fila. */
function suplantar_anotar(int $adminId, int $usuarioId): int
{
    try {
        asegurar_tabla('suplantaciones');
        db()->prepare('INSERT INTO suplantaciones (admin_id, usuario_id) VALUES (?, ?)')
            ->execute([$adminId, $usuarioId]);
        return (int)db()->lastInsertId();
    } catch (PDOException $ex) {
        // El registro es para auditar, no para funcionar: si falla, se
        // entra igual. Mejor eso que dejar al admin afuera del panel.
        return 0;
    }
}

/** Marca la hora de salida. */
function suplantar_cerrar_registro(int $registroId): void
{
    if ($registroId <= 0) {
        return;
    }
    try {
        asegurar_tabla('suplantaciones');
        $ahora = date('Y-m-d H:i:s');
        db()->prepare('UPDATE suplantaciones SET termino = ? WHERE id = ? AND termino IS NULL')
            ->execute([$ahora, $registroId]);
    } catch (PDOException $ex) {
        // Ídem.
    }
}

/** Las últimas veces que se entró a una cuenta ajena, para mostrarlas. */
function listar_suplantaciones(int $limite = 30): array
{
    try {
        asegurar_tabla('suplantaciones');
        $stmt = db()->prepare('
            SELECT s.*, a.nombre AS admin_nombre, u.nombre AS usuario_nombre, u.rol AS usuario_rol
            FROM suplantaciones s
            LEFT JOIN usuarios a ON a.id = s.admin_id
            LEFT JOIN usuarios u ON u.id = s.usuario_id
            ORDER BY s.id DESC
            LIMIT ' . max(1, $limite)
        );
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $ex) {
        return [];
    }
}
