<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';
require_auth_api();

$fecha = $_GET['fecha'] ?? date('Y-m-d');

try {
    // Verificar si el día está bloqueado por administración
    $stmtBlq = $pdo->prepare("SELECT motivo FROM dias_bloqueados WHERE fecha = ? LIMIT 1");
    $stmtBlq->execute([$fecha]);
    $diaBloqueado = $stmtBlq->fetch(PDO::FETCH_ASSOC);

    if ($diaBloqueado !== false) {
        echo json_encode([
            'success'         => true,
            'dia_bloqueado'   => true,
            'motivo_bloqueo'  => $diaBloqueado['motivo'] ?? null,
            'reservas'        => []
        ]);
        exit;
    }

    // Verificar reglas recurrentes (bloqueos indefinidos por día de la semana)
    $dt = new DateTime($fecha);
    $dow = (int)$dt->format('w'); // 0 domingo .. 6 sábado
    $stmtSem = $pdo->prepare("SELECT motivo FROM dias_bloqueados_recurrent WHERE dia = ? LIMIT 1");
    $stmtSem->execute([$dow]);
    $bloqSem = $stmtSem->fetch(PDO::FETCH_ASSOC);
    if ($bloqSem !== false) {
        echo json_encode([
            'success'         => true,
            'dia_bloqueado'   => true,
            'motivo_bloqueo'  => $bloqSem['motivo'] ?? null,
            'reservas'        => []
        ]);
        exit;
    }

    // Consultamos todas las reservas para esa fecha que no estén rechazadas
    $stmtCampo = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'tipo_ocupacion'");
    $columnaExiste = $stmtCampo->fetch(PDO::FETCH_ASSOC) !== false;

    $sql = "SELECT r.espacio, r.fecha, r.hora_inicio, r.hora_fin, r.id, r.estado, u.nombre AS usuario_nombre";
    if ($columnaExiste) {
        $sql .= ", r.tipo_ocupacion";
    } else {
        $sql .= ", NULL AS tipo_ocupacion";
    }
    $sql .= " FROM reservas r
            LEFT JOIN usuarios u ON u.id = r.usuario_id
            WHERE r.fecha = ? AND r.estado IN ('Pendiente', 'Aprobada')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fecha]);
    $reservasArray = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reservaIds = array_values(array_filter(array_map(function($r) {
        return isset($r['id']) ? (int)$r['id'] : 0;
    }, $reservasArray)));

    $recursosPorReserva = [];
    if (!empty($reservaIds)) {
        $placeholders = implode(',', array_fill(0, count($reservaIds), '?'));
        $stmtRec = $pdo->prepare(
            "SELECT rr.reserva_id, rec.nombre, rr.cantidad
             FROM reserva_recursos rr
             JOIN recursos rec ON rec.id = rr.recurso_id
             WHERE rr.reserva_id IN ($placeholders)"
        );
        $stmtRec->execute($reservaIds);
        while ($row = $stmtRec->fetch(PDO::FETCH_ASSOC)) {
            $resId = (int)$row['reserva_id'];
            $recursosPorReserva[$resId][] = [
                'nombre'   => $row['nombre'],
                'cantidad' => $row['cantidad'],
            ];
        }
    }

    // B1 o B2 ocupados bloquean B3, y B3 ocupado bloquea B1 y B2
    $reservasCompletas = [];

    foreach ($reservasArray as $reserva) {
        $reservasCompletas[] = array_merge($reserva, [
            'tipo' => 'ocupado',
            'usuario_nombre' => $reserva['usuario_nombre'] ?? 'Usuario desconocido',
            'recursos' => $recursosPorReserva[$reserva['id']] ?? []
        ]);

        if ($reserva['espacio'] === 'B1' || $reserva['espacio'] === 'B2') {
            // B1 o B2 ocupado → B3 queda bloqueado
            $reservasCompletas[] = [
                'espacio'     => 'B3',
                'fecha'       => $reserva['fecha'],
                'hora_inicio' => $reserva['hora_inicio'],
                'hora_fin'    => $reserva['hora_fin'],
                'tipo'        => 'bloqueado',
                'usuario_nombre' => 'Bloqueado',
                'tipo_ocupacion' => null
            ];
        } elseif ($reserva['espacio'] === 'B3') {
            // B3 ocupado → B1 y B2 quedan bloqueados
            $reservasCompletas[] = [
                'espacio'     => 'B1',
                'fecha'       => $reserva['fecha'],
                'hora_inicio' => $reserva['hora_inicio'],
                'hora_fin'    => $reserva['hora_fin'],
                'tipo'        => 'bloqueado',
                'usuario_nombre' => 'Bloqueado',
                'tipo_ocupacion' => null,
                'recursos'    => []
            ];
            $reservasCompletas[] = [
                'espacio'     => 'B2',
                'fecha'       => $reserva['fecha'],
                'hora_inicio' => $reserva['hora_inicio'],
                'hora_fin'    => $reserva['hora_fin'],
                'tipo'        => 'bloqueado',
                'usuario_nombre' => 'Bloqueado',
                'tipo_ocupacion' => null,
                'recursos'    => []
            ];
        }
    }

    echo json_encode([
        'success'       => true,
        'dia_bloqueado' => false,
        'reservas'      => $reservasCompletas
    ]);
} catch (Throwable $e) {
    error_log('disponibilidad ERROR: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener disponibilidad: ' . $e->getMessage()
    ]);
}
