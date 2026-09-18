<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

/**
 * La cuenta de cada usuario: el nombre, la contraseña y la foto de perfil.
 *
 * La foto se recorta al cuadrado del medio y se guarda chica (400 px) en
 * uploads/perfiles/, con un nombre inventado. El .htaccess de uploads/
 * ya impide ejecutar PHP ahí adentro.
 */

const FOTO_PERFIL_LADO = 400;
const FOTO_PERFIL_TAMANO_MAXIMO = 8 * 1024 * 1024; // 8 MB
const CONTRASENA_LARGO_MINIMO = 8;

function asegurar_columna_foto(): void
{
    asegurar_columna('usuarios', 'foto', db_driver() === 'sqlite' ? 'TEXT' : 'VARCHAR(255) NULL');
}

/** La fila completa del usuario, leída de la base (la sesión no guarda la foto). */
function usuario_por_id(int $usuarioId): ?array
{
    asegurar_columna_foto();
    $stmt = db()->prepare('SELECT id, nombre, email, rol, foto FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    return $stmt->fetch() ?: null;
}

function directorio_perfiles(): string
{
    return __DIR__ . '/../uploads/perfiles';
}

/** Ruta relativa a la raíz del sitio, o null si no tiene foto. */
function ruta_foto_perfil(array $usuario): ?string
{
    $foto = (string)($usuario['foto'] ?? '');
    return $foto !== '' ? 'uploads/perfiles/' . $foto : null;
}

/** El nombre de pila: "Ana María López" -> "Ana". */
function nombre_de_pila(string $nombre): string
{
    $partes = preg_split('/\s+/u', trim($nombre)) ?: [];
    return $partes[0] ?? $nombre;
}

/** Hasta dos iniciales, para el círculo cuando no hay foto: "Ana López" -> "AL". */
function iniciales(string $nombre): string
{
    $iniciales = '';
    foreach (array_slice(preg_split('/\s+/u', trim($nombre)) ?: [], 0, 2) as $parte) {
        $iniciales .= function_exists('mb_substr') ? mb_substr($parte, 0, 1, 'UTF-8') : substr($parte, 0, 1);
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($iniciales, 'UTF-8') : strtoupper($iniciales);
}

/** El nombre que ve el estudio y que sale en el saludo. Devuelve null o el error. */
function cambiar_nombre(int $usuarioId, string $nombre): ?string
{
    $nombre = trim((string)preg_replace('/\s+/u', ' ', $nombre));
    if ($nombre === '') {
        return 'Escribí tu nombre.';
    }
    if (largo_texto($nombre) > 150) {
        return 'El nombre es muy largo (máximo 150 caracteres).';
    }

    $upd = db()->prepare('UPDATE usuarios SET nombre = ? WHERE id = ?');
    $upd->execute([$nombre, $usuarioId]);

    // La sesión guarda una copia del nombre: la barra de arriba del estudio la usa.
    iniciar_sesion_segura();
    if (isset($_SESSION['usuario'])) {
        $_SESSION['usuario']['nombre'] = $nombre;
    }
    return null;
}

/** Devuelve null si se cambió, o el mensaje de error. */
function cambiar_contrasena(int $usuarioId, array $datos): ?string
{
    $actual = (string)($datos['actual'] ?? '');
    $nueva = (string)($datos['nueva'] ?? '');
    $repetida = (string)($datos['repetida'] ?? '');

    if ($actual === '' || $nueva === '' || $repetida === '') {
        return 'Completá los tres campos.';
    }

    $stmt = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ? LIMIT 1');
    $stmt->execute([$usuarioId]);
    $fila = $stmt->fetch();
    if (!$fila || !password_verify($actual, $fila['password_hash'])) {
        return 'La contraseña actual no es correcta.';
    }
    if (strlen($nueva) < CONTRASENA_LARGO_MINIMO) {
        return 'La contraseña nueva tiene que tener al menos ' . CONTRASENA_LARGO_MINIMO . ' caracteres.';
    }
    if ($nueva !== $repetida) {
        return 'Las dos contraseñas nuevas no coinciden.';
    }
    if (password_verify($nueva, $fila['password_hash'])) {
        return 'La contraseña nueva es igual a la actual.';
    }

    $upd = db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
    $upd->execute([password_hash($nueva, PASSWORD_DEFAULT), $usuarioId]);

    // Otro id de sesión: si alguien tenía la sesión vieja, deja de servirle.
    iniciar_sesion_segura();
    session_regenerate_id(true);

    return null;
}

/**
 * Guarda la foto de perfil ($_FILES['foto']). Devuelve null si salió
 * bien, o el mensaje de error.
 */
function guardar_foto_perfil(int $usuarioId, array $archivo): ?string
{
    if (!isset($archivo['error']) || is_array($archivo['error']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
        return 'Elegí una foto.';
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'La foto es demasiado pesada.'
            : 'No se pudo subir la foto (código ' . $archivo['error'] . ').';
    }
    if ($archivo['size'] > FOTO_PERFIL_TAMANO_MAXIMO) {
        return 'La foto supera el límite de 8 MB.';
    }

    $info = @getimagesize($archivo['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if ($info === false || !isset($tipos[$info[2]])) {
        return 'La foto tiene que ser JPG, PNG o WEBP.';
    }
    if (!function_exists($tipos[$info[2]]) || !function_exists('imagecreatetruecolor')) {
        return 'El servidor no puede procesar imágenes (falta GD).';
    }

    $original = @($tipos[$info[2]])($archivo['tmp_name']);
    if (!$original) {
        return 'No se pudo leer la foto.';
    }

    // Las fotos del celular vienen giradas: la orientación está en el EXIF.
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($archivo['tmp_name']);
        $giro = [3 => 180, 6 => -90, 8 => 90][(int)($exif['Orientation'] ?? 1)] ?? 0;
        if ($giro !== 0) {
            $girada = imagerotate($original, $giro, 0);
            if ($girada) {
                imagedestroy($original);
                $original = $girada;
            }
        }
    }

    // Recorte cuadrado del centro, achicado a FOTO_PERFIL_LADO.
    $ancho = imagesx($original);
    $alto = imagesy($original);
    $lado = min($ancho, $alto);
    $cuadrada = imagecreatetruecolor(FOTO_PERFIL_LADO, FOTO_PERFIL_LADO);
    imagefill($cuadrada, 0, 0, imagecolorallocate($cuadrada, 255, 255, 255));
    imagecopyresampled(
        $cuadrada, $original,
        0, 0, (int)(($ancho - $lado) / 2), (int)(($alto - $lado) / 2),
        FOTO_PERFIL_LADO, FOTO_PERFIL_LADO, $lado, $lado
    );
    imagedestroy($original);

    $directorio = directorio_perfiles();
    if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
        imagedestroy($cuadrada);
        return 'No se pudo crear la carpeta de fotos de perfil.';
    }

    $nombreArchivo = bin2hex(random_bytes(16)) . '.jpg';
    $guardada = imagejpeg($cuadrada, $directorio . '/' . $nombreArchivo, 86);
    imagedestroy($cuadrada);
    if (!$guardada) {
        return 'No se pudo guardar la foto.';
    }

    $anterior = usuario_por_id($usuarioId)['foto'] ?? null;

    $upd = db()->prepare('UPDATE usuarios SET foto = ? WHERE id = ?');
    $upd->execute([$nombreArchivo, $usuarioId]);

    borrar_archivo_perfil($anterior);
    return null;
}

function quitar_foto_perfil(int $usuarioId): void
{
    $anterior = usuario_por_id($usuarioId)['foto'] ?? null;

    $upd = db()->prepare('UPDATE usuarios SET foto = NULL WHERE id = ?');
    $upd->execute([$usuarioId]);

    borrar_archivo_perfil($anterior);
}

function borrar_archivo_perfil(?string $archivo): void
{
    // basename(): el nombre sale de la base, pero nunca está de más.
    if ($archivo && is_file(directorio_perfiles() . '/' . basename($archivo))) {
        unlink(directorio_perfiles() . '/' . basename($archivo));
    }
}
