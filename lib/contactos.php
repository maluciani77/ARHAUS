<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/documentos.php';

/**
 * La gente de la obra: los profesionales (higiene y seguridad, gestor,
 * agrimensor), los proveedores contratados y los teléfonos útiles. Los
 * carga solo el estudio; el cliente los ve. A cada contacto se le pueden
 * adjuntar archivos (por ejemplo, el plan de higiene y seguridad).
 */

const TIPOS_CONTACTO = [
    'higiene' => 'Higiene y seguridad',
    'gestor' => 'Gestor',
    'agrimensor' => 'Agrimensor',
    'proveedor' => 'Proveedor',
    'telefono' => 'Teléfono útil',
];

const CAMPOS_CONTACTO = ['nombre', 'empresa', 'rubro', 'telefono', 'email', 'notas'];

/** El WhatsApp del estudio: el botón de Mensajes abre el chat con este número. */
const WHATSAPP_ESTUDIO = '+54 9 11 3456-4660';

/** Link de WhatsApp (wa.me) para un número, con un mensaje ya escrito. */
function enlace_whatsapp(string $numero, string $mensaje = ''): string
{
    return 'https://wa.me/' . preg_replace('/\D/', '', $numero) . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

function contactos_de_obra(int $obraId, ?string $tipo = null): array
{
    asegurar_tabla('contactos');
    if ($tipo !== null) {
        $stmt = db()->prepare('SELECT * FROM contactos WHERE obra_id = ? AND tipo = ? ORDER BY nombre ASC, id ASC');
        $stmt->execute([$obraId, $tipo]);
    } else {
        $stmt = db()->prepare('SELECT * FROM contactos WHERE obra_id = ? ORDER BY tipo ASC, nombre ASC, id ASC');
        $stmt->execute([$obraId]);
    }
    return $stmt->fetchAll();
}

/** contacto_id => lista de archivos adjuntos, de todos los contactos de la obra. */
function archivos_de_contactos(int $obraId): array
{
    asegurar_tabla('contacto_archivos');
    $stmt = db()->prepare('SELECT * FROM contacto_archivos WHERE obra_id = ? ORDER BY created_at DESC, id DESC');
    $stmt->execute([$obraId]);
    $porContacto = [];
    foreach ($stmt->fetchAll() as $archivo) {
        $porContacto[(int)$archivo['contacto_id']][] = $archivo;
    }
    return $porContacto;
}

/** Devuelve null si se guardó, o el mensaje de error. */
function agregar_contacto(int $obraId, array $datos, int $usuarioId): ?string
{
    $tipo = (string)($datos['tipo'] ?? '');
    if (!isset(TIPOS_CONTACTO[$tipo])) {
        return 'Elegí qué tipo de contacto es.';
    }

    $limpio = [];
    foreach (CAMPOS_CONTACTO as $campo) {
        $limpio[$campo] = trim((string)($datos[$campo] ?? ''));
        if (largo_texto($limpio[$campo]) > ($campo === 'notas' ? 1000 : 190)) {
            return 'El campo ' . $campo . ' es muy largo.';
        }
    }
    if ($limpio['nombre'] === '') {
        return 'El contacto necesita un nombre.';
    }
    if ($tipo === 'telefono' && $limpio['telefono'] === '') {
        return 'Un teléfono útil necesita el número.';
    }
    if ($limpio['email'] !== '' && !filter_var($limpio['email'], FILTER_VALIDATE_EMAIL)) {
        return 'El email no es válido.';
    }
    if ($limpio['telefono'] !== '' && !preg_match('/^[0-9+()\s\-.]{3,40}$/', $limpio['telefono'])) {
        return 'El teléfono solo puede tener números, espacios, guiones y el signo +.';
    }

    asegurar_tabla('contactos');
    $stmt = db()->prepare('INSERT INTO contactos (obra_id, tipo, nombre, empresa, rubro, telefono, email, notas, cargado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $obraId, $tipo,
        ...array_map(static fn (string $v): ?string => $v !== '' ? $v : null, array_values($limpio)),
        $usuarioId,
    ]);
    return null;
}

function contacto_de_obra(int $contactoId, int $obraId): ?array
{
    asegurar_tabla('contactos');
    $stmt = db()->prepare('SELECT * FROM contactos WHERE id = ? AND obra_id = ?');
    $stmt->execute([$contactoId, $obraId]);
    return $stmt->fetch() ?: null;
}

function eliminar_contacto(int $contactoId, int $obraId): void
{
    if (!contacto_de_obra($contactoId, $obraId)) {
        return;
    }
    foreach (archivos_de_contactos($obraId)[$contactoId] ?? [] as $archivo) {
        borrar_archivo_obra($obraId, $archivo['archivo']);
    }
    db()->prepare('DELETE FROM contacto_archivos WHERE contacto_id = ? AND obra_id = ?')->execute([$contactoId, $obraId]);
    db()->prepare('DELETE FROM contactos WHERE id = ? AND obra_id = ?')->execute([$contactoId, $obraId]);
}

/** Adjunta un archivo a un contacto. Devuelve null o el error. */
function adjuntar_archivo_contacto(int $contactoId, int $obraId, array $datos, array $archivo, int $usuarioId): ?string
{
    if (!contacto_de_obra($contactoId, $obraId)) {
        return 'Ese contacto no existe.';
    }
    try {
        [$nombreArchivo, $nombreOriginal] = guardar_archivo_obra($obraId, $archivo);
    } catch (RuntimeException $ex) {
        return $ex->getMessage();
    }
    $titulo = trim((string)($datos['titulo'] ?? '')) ?: pathinfo($nombreOriginal, PATHINFO_FILENAME);

    asegurar_tabla('contacto_archivos');
    db()->prepare('INSERT INTO contacto_archivos (contacto_id, obra_id, titulo, archivo, nombre_original, tamano, subido_por) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$contactoId, $obraId, recortar_texto($titulo, 190), $nombreArchivo, $nombreOriginal, (int)$archivo['size'], $usuarioId]);
    return null;
}

function eliminar_archivo_contacto(int $archivoId, int $obraId): void
{
    asegurar_tabla('contacto_archivos');
    $stmt = db()->prepare('SELECT * FROM contacto_archivos WHERE id = ? AND obra_id = ?');
    $stmt->execute([$archivoId, $obraId]);
    $archivo = $stmt->fetch();
    if (!$archivo) {
        return;
    }
    db()->prepare('DELETE FROM contacto_archivos WHERE id = ? AND obra_id = ?')->execute([$archivoId, $obraId]);
    borrar_archivo_obra($obraId, $archivo['archivo']);
}

/** Link tel: con solo los dígitos y el +. */
function enlace_telefono(string $telefono): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $telefono);
}
