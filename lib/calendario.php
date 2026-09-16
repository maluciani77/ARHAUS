<?php
declare(strict_types=1);

require_once __DIR__ . '/presupuestos.php';

const NOMBRES_MES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const NOMBRES_DIA = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
const ORDEN_EVENTOS = ['etapa' => 0, 'evento' => 1, 'presupuesto' => 2, 'fotos' => 3];

/** Devuelve la fecha 'Y-m-d' si es válida, o null. */
function fecha_valida(?string $fecha): ?string
{
    if (!$fecha || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
        return null;
    }
    return checkdate((int)$partes[2], (int)$partes[3], (int)$partes[1]) ? $fecha : null;
}

/** Devuelve el mes 'Y-m' si es válido, o null. */
function mes_valido(?string $mes): ?string
{
    return $mes && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes : null;
}

/** Devuelve la hora 'H:i' si es válida ('9:30', '09:30' o '09:30:00'), o null. */
function hora_valida(?string $hora): ?string
{
    if (!$hora || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $hora, $partes)) {
        return null;
    }
    return str_pad($partes[1], 2, '0', STR_PAD_LEFT) . ':' . $partes[2];
}

/** Fecha de hoy en Argentina (el servidor puede estar en otra zona horaria). */
function hoy_argentina(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('America/Argentina/Buenos_Aires')))->format('Y-m-d');
}

/** Eventos cargados a mano en el calendario de una obra, en orden de fecha y hora. */
function eventos_cargados_de_obra(int $obraId): array
{
    asegurar_tabla('eventos');
    $stmt = db()->prepare('SELECT * FROM eventos WHERE obra_id = ? ORDER BY fecha ASC, hora ASC, id ASC');
    $stmt->execute([$obraId]);
    return $stmt->fetchAll();
}

/**
 * Valida los datos del formulario y guarda el evento.
 * Devuelve null si salió bien, o el mensaje de error para mostrar.
 */
function agregar_evento(int $obraId, array $datos, int $usuarioId): ?string
{
    $titulo = trim((string)($datos['titulo'] ?? ''));
    $fecha = fecha_valida(trim((string)($datos['fecha'] ?? '')));
    $horaEscrita = trim((string)($datos['hora'] ?? ''));
    $hora = hora_valida($horaEscrita);
    $detalle = trim((string)($datos['detalle'] ?? '')) ?: null;

    if ($titulo === '') {
        return 'El evento necesita un título.';
    }
    if (preg_match_all('/./us', $titulo) > 190) {
        return 'El título es muy largo (hasta 190 caracteres). El resto va en el detalle.';
    }
    if ($fecha === null) {
        return 'El evento necesita una fecha válida.';
    }
    if ($horaEscrita !== '' && $hora === null) {
        return 'La hora no es válida. Escribila por ejemplo así: 10:30';
    }

    asegurar_tabla('eventos');
    $stmt = db()->prepare('INSERT INTO eventos (obra_id, titulo, fecha, hora, detalle, cargado_por) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$obraId, $titulo, $fecha, $hora, $detalle, $usuarioId]);
    return null;
}

function eliminar_evento(int $eventoId, int $obraId): void
{
    asegurar_tabla('eventos');
    $stmt = db()->prepare('DELETE FROM eventos WHERE id = ? AND obra_id = ?');
    $stmt->execute([$eventoId, $obraId]);
}

/**
 * Junta todo lo que tiene fecha en la obra: etapas, eventos cargados a mano,
 * presupuestos y los días en que se subieron fotos. Devuelve
 * ['2026-08-20' => [evento, ...]] ordenado por fecha. Cada evento: tipo
 * ('etapa' | 'evento' | 'presupuesto' | 'fotos'), titulo, detalle (o null),
 * hora (o null), id (el del evento cargado, o null) y seccion, extra y ancla
 * para enlazar a donde se ve en el panel del cliente.
 * Los montos ocultos al cliente no se incluyen.
 */
function eventos_de_obra(array $etapas, array $fotos, array $presupuestos, array $eventosCargados = []): array
{
    $eventos = [];
    $nombresEtapa = [];

    foreach ($etapas as $etapa) {
        $nombresEtapa[(int)$etapa['id']] = $etapa['nombre'];
        $fecha = fecha_valida($etapa['fecha'] ?? null);
        if ($fecha) {
            $eventos[$fecha][] = [
                'tipo' => 'etapa',
                'titulo' => $etapa['nombre'],
                'detalle' => $etapa['descripcion'] ?: null,
                'hora' => null,
                'id' => null,
                'seccion' => 'fotos',
                'extra' => [],
                'ancla' => 'etapa-' . (int)$etapa['id'],
            ];
        }
    }

    foreach ($eventosCargados as $cargado) {
        $fecha = fecha_valida($cargado['fecha'] ?? null);
        if ($fecha) {
            $eventos[$fecha][] = [
                'tipo' => 'evento',
                'titulo' => $cargado['titulo'],
                'detalle' => $cargado['detalle'] ?: null,
                'hora' => hora_valida($cargado['hora'] ?? null),
                'id' => (int)$cargado['id'],
                'seccion' => 'calendario',
                'extra' => ['mes' => substr($fecha, 0, 7)],
                'ancla' => 'dia-' . $fecha,
            ];
        }
    }

    foreach ($presupuestos as $presupuesto) {
        $fecha = fecha_valida($presupuesto['fecha'] ?? null);
        if ($fecha) {
            $eventos[$fecha][] = [
                'tipo' => 'presupuesto',
                'titulo' => $presupuesto['concepto'],
                'detalle' => $presupuesto['monto_oculto'] ? null : formatear_monto($presupuesto['monto'], $presupuesto['moneda']),
                'hora' => null,
                'id' => null,
                'seccion' => 'presupuestos',
                'extra' => [],
                'ancla' => null,
            ];
        }
    }

    $fotosPorDia = [];
    foreach ($fotos as $foto) {
        $fecha = fecha_valida(substr((string)$foto['created_at'], 0, 10));
        if ($fecha) {
            $fotosPorDia[$fecha][] = $foto;
        }
    }
    foreach ($fotosPorDia as $fecha => $delDia) {
        $etapaIds = array_values(array_unique(array_map(function (array $f): int {
            return (int)($f['etapa_id'] ?? 0);
        }, $delDia)));
        $nombres = [];
        foreach ($etapaIds as $id) {
            if (isset($nombresEtapa[$id])) {
                $nombres[] = $nombresEtapa[$id];
            }
        }
        $cantidad = count($delDia);
        $eventos[$fecha][] = [
            'tipo' => 'fotos',
            'titulo' => $cantidad === 1 ? '1 foto subida' : $cantidad . ' fotos subidas',
            'detalle' => $nombres ? implode(' · ', $nombres) : null,
            'hora' => null,
            'id' => null,
            'seccion' => 'fotos',
            'extra' => [],
            // Si todas son de la misma etapa, el enlace lleva directo a ella.
            'ancla' => count($etapaIds) === 1 && isset($nombresEtapa[$etapaIds[0]]) ? 'etapa-' . $etapaIds[0] : null,
        ];
    }

    ksort($eventos);
    foreach ($eventos as &$delDia) {
        usort($delDia, function (array $a, array $b): int {
            return [ORDEN_EVENTOS[$a['tipo']], (string)$a['hora']] <=> [ORDEN_EVENTOS[$b['tipo']], (string)$b['hora']];
        });
    }
    unset($delDia);

    return $eventos;
}

/** ['2026-05' => 2, '2026-08' => 3, ...]: cuántos eventos tiene cada mes. */
function meses_con_eventos(array $eventos): array
{
    $meses = [];
    foreach ($eventos as $fecha => $delDia) {
        $mes = substr($fecha, 0, 7);
        $meses[$mes] = ($meses[$mes] ?? 0) + count($delDia);
    }
    ksort($meses);
    return $meses;
}

/**
 * Mes que se muestra al entrar: el actual si tiene algo; si no, el último
 * mes con actividad antes de hoy; si todo es a futuro, el primero que venga.
 */
function mes_inicial(array $eventos, string $hoy): string
{
    $mesHoy = substr($hoy, 0, 7);
    $meses = array_keys(meses_con_eventos($eventos));
    if (!$meses || in_array($mesHoy, $meses, true)) {
        return $mesHoy;
    }
    $pasados = array_filter($meses, function (string $m) use ($mesHoy): bool {
        return $m < $mesHoy;
    });
    return $pasados ? max($pasados) : min($meses);
}

/** '2026-08' + 1 = '2026-09'. */
function mes_relativo(string $mes, int $desplazamiento): string
{
    return (new DateTimeImmutable($mes . '-01'))->modify(($desplazamiento >= 0 ? '+' : '') . $desplazamiento . ' months')->format('Y-m');
}

/** 'Agosto 2026'. */
function nombre_mes_anio(string $mes): string
{
    [$anio, $numero] = explode('-', $mes);
    return ucfirst(NOMBRES_MES[(int)$numero]) . ' ' . $anio;
}

/** 'jueves 20'. */
function nombre_dia(string $fecha): string
{
    $dia = new DateTimeImmutable($fecha);
    return NOMBRES_DIA[(int)$dia->format('N')] . ' ' . $dia->format('j');
}

/**
 * Semanas completas (de lunes a domingo) que cubren el mes, como listas de
 * 7 fechas 'Y-m-d'. Incluye los días del mes anterior y siguiente que
 * completan la primera y la última semana.
 */
function semanas_del_mes(string $mes): array
{
    $primero = new DateTimeImmutable($mes . '-01');
    $dia = $primero->modify('-' . ((int)$primero->format('N') - 1) . ' days');
    $ultimo = $primero->modify('last day of this month');
    $fin = $ultimo->modify('+' . (7 - (int)$ultimo->format('N')) . ' days');

    $semanas = [];
    while ($dia <= $fin) {
        $semana = [];
        for ($i = 0; $i < 7; $i++) {
            $semana[] = $dia->format('Y-m-d');
            $dia = $dia->modify('+1 day');
        }
        $semanas[] = $semana;
    }
    return $semanas;
}
