<?php
function espaciosReservables(): array
{
    return ['B1', 'B2', 'B3'];
}

function esEspacioValido(string $espacio): bool
{
    return in_array($espacio, espaciosReservables(), true);
}

function getEspaciosConflicto(string $espacio): array
{
    switch ($espacio) {
        case 'B3':
            return ['B1', 'B2', 'B3'];
        case 'B1':
        case 'B2':
            return [$espacio, 'B3'];
        default:
            return [];
    }
}

function contarConflictos(PDO $pdo, string $espacio, string $fecha, string $hora_inicio, string $hora_fin, ?int $excludeId = null): int
{
    $conflictos = getEspaciosConflicto($espacio);
    if (empty($conflictos)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($conflictos), '?'));
    $sql = "SELECT COUNT(*) FROM reservas
            WHERE espacio IN ($placeholders)
              AND fecha = ?
              AND estado IN ('Pendiente','Aprobada')
              AND (? < hora_fin AND ? > hora_inicio)";
    $params = array_merge($conflictos, [$fecha, $hora_inicio, $hora_fin]);
    if ($excludeId !== null) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int)$stmt->fetchColumn();
}

function verificarConflictoReserva(PDO $pdo, string $espacio, string $fecha, string $hora_inicio, string $hora_fin, ?int $excludeId = null): array
{
    if (!esEspacioValido($espacio)) {
        return ['disponible' => false, 'mensaje' => 'Espacio inválido.'];
    }

    if ($espacio === 'B3') {
        if (contarConflictos($pdo, 'B1', $fecha, $hora_inicio, $hora_fin, $excludeId) > 0) {
            return ['disponible' => false, 'mensaje' => 'No se puede reservar B3 porque B1 ya está ocupado en ese horario.'];
        }
        if (contarConflictos($pdo, 'B2', $fecha, $hora_inicio, $hora_fin, $excludeId) > 0) {
            return ['disponible' => false, 'mensaje' => 'No se puede reservar B3 porque B2 ya está ocupado en ese horario.'];
        }
        if (contarConflictos($pdo, 'B3', $fecha, $hora_inicio, $hora_fin, $excludeId) > 0) {
            return ['disponible' => false, 'mensaje' => 'B3 ya está ocupado en ese horario.'];
        }
    } else {
        if (contarConflictos($pdo, $espacio, $fecha, $hora_inicio, $hora_fin, $excludeId) > 0) {
            return ['disponible' => false, 'mensaje' => "El espacio {$espacio} ya está ocupado en ese horario."];
        }
    }

    return ['disponible' => true, 'mensaje' => 'Disponible.'];
}
