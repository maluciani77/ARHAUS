<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

/**
 * Los propietarios de la obra (puede haber más de uno). Los carga el
 * cliente desde Datos > Propietarios y el estudio los ve en la página de
 * la obra, para los trámites.
 *
 * Son datos personales (DNI, fecha de nacimiento): solo los ven el
 * cliente de la obra, el admin y el arquitecto asignado. No se le pasan
 * al asistente.
 */

const CAMPOS_PROPIETARIO = [
    'nombre' => 'Nombre',
    'apellido' => 'Apellido',
    'dni' => 'DNI',
    'cuit' => 'CUIT / CUIL',
    'fecha_nacimiento' => 'Fecha de nacimiento',
    'nacionalidad' => 'Nacionalidad',
    'estado_civil' => 'Estado civil',
    'domicilio' => 'Domicilio real',
    'telefono' => 'Teléfono',
    'email' => 'Email',
];

const ESTADOS_CIVILES = ['Soltero/a', 'Casado/a', 'Unión convivencial', 'Divorciado/a', 'Viudo/a'];

function propietarios_de_obra(int $obraId): array
{
    asegurar_tabla('propietarios');
    $stmt = db()->prepare('SELECT * FROM propietarios WHERE obra_id = ? ORDER BY id ASC');
    $stmt->execute([$obraId]);
    return $stmt->fetchAll();
}

/** Solo dígitos: "20.123.456" -> "20123456". */
function solo_digitos(string $texto): string
{
    return (string)preg_replace('/\D+/', '', $texto);
}

/** Valida el dígito verificador de un CUIT/CUIL de 11 dígitos. */
function cuit_valido(string $cuit): bool
{
    if (!preg_match('/^\d{11}$/', $cuit)) {
        return false;
    }
    $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    foreach ($pesos as $i => $peso) {
        $suma += (int)$cuit[$i] * $peso;
    }
    $verificador = 11 - ($suma % 11);
    $verificador = $verificador === 11 ? 0 : ($verificador === 10 ? 9 : $verificador);
    return $verificador === (int)$cuit[10];
}

/** "20123456789" -> "20-12345678-9". */
function formatear_cuit(string $cuit): string
{
    return strlen($cuit) === 11 ? substr($cuit, 0, 2) . '-' . substr($cuit, 2, 8) . '-' . substr($cuit, 10) : $cuit;
}

/** "20123456" -> "20.123.456". */
function formatear_dni(string $dni): string
{
    return $dni !== '' ? number_format((int)$dni, 0, ',', '.') : '';
}

/**
 * Valida y normaliza el formulario. Devuelve [datos limpios, null] o
 * [null, mensaje de error].
 */
function validar_propietario(array $datos): array
{
    $limpio = [];
    foreach (array_keys(CAMPOS_PROPIETARIO) as $campo) {
        $limpio[$campo] = trim((string)($datos[$campo] ?? ''));
        if (largo_texto($limpio[$campo]) > 190) {
            return [null, CAMPOS_PROPIETARIO[$campo] . ': es demasiado largo.'];
        }
    }

    if ($limpio['nombre'] === '' || $limpio['apellido'] === '') {
        return [null, 'Completá el nombre y el apellido.'];
    }

    $limpio['dni'] = solo_digitos($limpio['dni']);
    if ($limpio['dni'] === '') {
        return [null, 'Completá el DNI.'];
    }
    if (!preg_match('/^\d{7,8}$/', $limpio['dni'])) {
        return [null, 'El DNI tiene que tener 7 u 8 números.'];
    }

    if ($limpio['cuit'] !== '') {
        $limpio['cuit'] = solo_digitos($limpio['cuit']);
        if (!cuit_valido($limpio['cuit'])) {
            return [null, 'El CUIT / CUIL no es válido. Revisá los 11 números.'];
        }
    }

    if ($limpio['fecha_nacimiento'] !== '') {
        $fecha = DateTime::createFromFormat('!Y-m-d', $limpio['fecha_nacimiento']);
        if (!$fecha || $fecha->format('Y-m-d') !== $limpio['fecha_nacimiento'] || $fecha > new DateTime('today') || (int)$fecha->format('Y') < 1900) {
            return [null, 'La fecha de nacimiento no es válida.'];
        }
    }

    if ($limpio['estado_civil'] !== '' && !in_array($limpio['estado_civil'], ESTADOS_CIVILES, true)) {
        return [null, 'Elegí un estado civil de la lista.'];
    }

    if ($limpio['email'] !== '' && !filter_var($limpio['email'], FILTER_VALIDATE_EMAIL)) {
        return [null, 'El email no es válido.'];
    }

    foreach ($limpio as $campo => $valor) {
        $limpio[$campo] = $valor !== '' ? $valor : null;
    }
    return [$limpio, null];
}

/** Agrega ($propietarioId = 0) o edita uno. Devuelve null o el error. */
function guardar_propietario(int $obraId, int $propietarioId, array $datos, int $usuarioId): ?string
{
    [$limpio, $error] = validar_propietario($datos);
    if ($error !== null) {
        return $error;
    }

    asegurar_tabla('propietarios');
    $campos = array_keys(CAMPOS_PROPIETARIO);

    if ($propietarioId > 0) {
        $asignaciones = implode(', ', array_map(static fn (string $c): string => $c . ' = ?', $campos));
        $stmt = db()->prepare('UPDATE propietarios SET ' . $asignaciones . ', actualizado_por = ?, updated_at = ? WHERE id = ? AND obra_id = ?');
        $stmt->execute([...array_values($limpio), $usuarioId, gmdate('Y-m-d H:i:s'), $propietarioId, $obraId]);
        return null;
    }

    $stmt = db()->prepare(
        'INSERT INTO propietarios (obra_id, ' . implode(', ', $campos) . ', actualizado_por, updated_at)
         VALUES (?, ' . implode(', ', array_fill(0, count($campos), '?')) . ', ?, ?)'
    );
    $stmt->execute([$obraId, ...array_values($limpio), $usuarioId, gmdate('Y-m-d H:i:s')]);
    return null;
}

function eliminar_propietario(int $propietarioId, int $obraId): void
{
    asegurar_tabla('propietarios');
    $stmt = db()->prepare('DELETE FROM propietarios WHERE id = ? AND obra_id = ?');
    $stmt->execute([$propietarioId, $obraId]);
}
