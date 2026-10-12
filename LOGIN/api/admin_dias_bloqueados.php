<?php
header('Content-Type: application/json');
require_once '../session.php';
require_once '../config.php';
require_once './mail_helper.php';
require_auth_api();

$me = $_SESSION['user'];
// Compatibilidad: el rol nuevo es "practicante" y reemplaza a "practicante-administrativo"
$rolesPermitidos = ['administrativo', 'practicante'];
if (!in_array($me['rol'], $rolesPermitidos, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// Asegurar existencia de tabla para reglas recurrentes (bloqueos por día de semana)
$pdo->exec("CREATE TABLE IF NOT EXISTS dias_bloqueados_recurrent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia TINYINT NOT NULL,
    motivo VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (dia)
)");

// Asegurar que la tabla `dias_bloqueados` puede referenciar una regla recurrente
$pdo->exec("ALTER TABLE dias_bloqueados ADD COLUMN IF NOT EXISTS regla_id INT DEFAULT NULL");

function cancelReservationsForDate(PDO $pdo, string $fecha, string $motivoBloqueo): int
{
    $stmt = $pdo->prepare(
        "SELECT r.id, r.espacio, r.fecha, r.hora_inicio, r.hora_fin, u.email, u.nombre
         FROM reservas r
         JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.fecha = ? AND r.estado IN ('Pendiente','Aprobada')"
    );
    $stmt->execute([$fecha]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($reservas)) {
        return 0;
    }

    $ids = array_column($reservas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE reservas SET estado = 'Cancelada' WHERE id IN ($placeholders)")
        ->execute($ids);

    foreach ($reservas as $reserva) {
        $subject = 'Reserva CANCELADA - Auditorio ' . ($reserva['espacio'] ?? '');
        $body    = build_block_cancellation_email($reserva, $motivoBloqueo);
        $mailError = null;
        send_system_email($reserva['email'], $subject, $body, $mailError);
    }

    return count($ids);
}

function cancelReservationsForWeekdays(PDO $pdo, array $dias, string $motivoBloqueo): int
{
    if (empty($dias)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($dias), '?'));
    $stmt = $pdo->prepare(
        "SELECT r.id, r.espacio, r.fecha, r.hora_inicio, r.hora_fin, u.email, u.nombre
         FROM reservas r
         JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.fecha >= CURDATE() AND r.estado IN ('Pendiente','Aprobada')
           AND DATE_FORMAT(r.fecha, '%w') IN ($placeholders)"
    );
    $stmt->execute($dias);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($reservas)) {
        return 0;
    }

    $ids = array_column($reservas, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("UPDATE reservas SET estado = 'Cancelada' WHERE id IN ($placeholders)")
        ->execute($ids);

    foreach ($reservas as $reserva) {
        $subject = 'Reserva CANCELADA - Auditorio ' . ($reserva['espacio'] ?? '');
        $body    = build_block_cancellation_email($reserva, $motivoBloqueo);
        $mailError = null;
        send_system_email($reserva['email'], $subject, $body, $mailError);
    }

    return count($ids);
}

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, fecha, motivo, created_at FROM dias_bloqueados ORDER BY fecha ASC");
    $dias = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cargar reglas recurrentes
    $stmt2 = $pdo->query("SELECT id, dia, motivo, created_at FROM dias_bloqueados_recurrent ORDER BY dia ASC");
    $recurrent = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'dias' => $dias, 'recurrent' => $recurrent]);

} elseif ($method === 'POST') {
    $fecha  = trim($body['fecha']  ?? '');
    $motivo = trim($body['motivo'] ?? '');

    // Nota: el antiguo parámetro 'semanal' (bloqueo recurrente hasta fecha_final)
    // fue removido en favor de los bloqueos indefinidos por día de semana.

    // Nuevo modo: crear regla recurrente por día(s) de la semana indefinida
    if (!empty($body['weekly']) && is_array($body['dias'])) {
        $dias = array_map('intval', $body['dias']);
        $motivo = trim($body['motivo'] ?? '');
        $force = !empty($body['force']);
        $preview = !empty($body['preview']);
        $createdRules = 0;
        $nombreDias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

        if ($preview && !empty($dias)) {
            $placeholders = implode(',', array_fill(0, count($dias), '?'));
            $stmtChk = $pdo->prepare(
                "SELECT DATE_FORMAT(fecha, '%w') as dia_semana, COUNT(*) AS total
                 FROM reservas
                 WHERE fecha >= CURDATE() AND estado IN ('Pendiente','Aprobada')
                   AND DATE_FORMAT(fecha, '%w') IN ($placeholders)
                 GROUP BY DATE_FORMAT(fecha, '%w')"
            );
            $stmtChk->execute($dias);
            $conflicts = $stmtChk->fetchAll(PDO::FETCH_ASSOC);
            $summary = [];
            if (!empty($conflicts)) {
                $summary = array_map(function ($row) use ($nombreDias) {
                    $dia = intval($row['dia_semana']);
                    $total = intval($row['total']);
                    return $nombreDias[$dia] . " ({$total})";
                }, $conflicts);
            }

            $stmtAlready = $pdo->prepare("SELECT dia FROM dias_bloqueados_recurrent WHERE dia IN ($placeholders)");
            $stmtAlready->execute($dias);
            $alreadyRows = $stmtAlready->fetchAll(PDO::FETCH_COLUMN, 0);
            $already = array_values(array_unique(array_map(function ($dia) use ($nombreDias) {
                return $nombreDias[intval($dia)];
            }, $alreadyRows)));

            echo json_encode([
                'success' => true,
                'conflicts' => $summary,
                'already' => $already
            ]);
            exit;
        }

        // Validar si existen reservas futuras en los días seleccionados
        if (!$force && !empty($dias)) {
            $placeholders = implode(',', array_fill(0, count($dias), '?'));
            $stmtChk = $pdo->prepare(
                "SELECT DATE_FORMAT(fecha, '%w') as dia_semana, COUNT(*) AS total
                 FROM reservas
                 WHERE fecha >= CURDATE() AND estado IN ('Pendiente','Aprobada')
                   AND DATE_FORMAT(fecha, '%w') IN ($placeholders)
                 GROUP BY DATE_FORMAT(fecha, '%w')"
            );
            $stmtChk->execute($dias);
            $conflicts = $stmtChk->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($conflicts)) {
                $summary = array_map(function ($row) use ($nombreDias) {
                    $dia = intval($row['dia_semana']);
                    $total = intval($row['total']);
                    return $nombreDias[$dia] . " ({$total})";
                }, $conflicts);
                echo json_encode([
                    'success' => false,
                    'confirm' => true,
                    'message' => 'Ya hay reservas futuras en los días seleccionados: ' . implode(', ', $summary) . '. ¿Deseas bloquearlos igualmente?',
                    'conflicts' => $summary
                ]);
                exit;
            }
        }

        foreach ($dias as $d) {
            if ($d < 0 || $d > 6) continue;

            $chk = $pdo->prepare("SELECT id FROM dias_bloqueados_recurrent WHERE dia = ? LIMIT 1");
            $chk->execute([$d]);
            $existsRule = $chk->fetch(PDO::FETCH_ASSOC);
            if ($existsRule) {
                continue;
            }

            $pdo->prepare("INSERT INTO dias_bloqueados_recurrent (dia, motivo) VALUES (?, ?)")
                ->execute([$d, $motivo]);
            $createdRules++;
        }

        $cancelled = 0;
        if ($force) {
            $cancelled = cancelReservationsForWeekdays($pdo, array_unique($dias), $motivo);
        }

        $msg = $createdRules > 0 ? 'Bloqueo indefinido guardado.' : 'Los días seleccionados ya estaban bloqueados indefinidamente.';
        if ($cancelled > 0) {
            $msg .= " Se cancelaron {$cancelled} reserva(s) y se notificó a los usuarios.";
        }
        echo json_encode(['success' => true, 'message' => $msg]);
        exit;
    }

    if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        echo json_encode(['success' => false, 'message' => 'Fecha inválida.']); exit;
    }

    // No bloquear fechas pasadas
    if ($fecha < date('Y-m-d')) {
        echo json_encode(['success' => false, 'message' => 'No se pueden bloquear fechas pasadas.']); exit;
    }

    // Límite máximo para bloqueo por fecha exacta: 3 meses
    $maxFecha = (new DateTime('today'))->modify('+3 months')->format('Y-m-d');
    if ($fecha > $maxFecha) {
        echo json_encode([
            'success' => false,
            'message' => "No se puede bloquear más allá de 3 meses. Límite: {$maxFecha}."
        ]); exit;
    }

    // Validar que el día no esté ya bloqueado de forma recurrente
    $nombreDias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $weekday = (int)date('w', strtotime($fecha));
    $stmtRule = $pdo->prepare("SELECT id FROM dias_bloqueados_recurrent WHERE dia = ? LIMIT 1");
    $stmtRule->execute([$weekday]);
    $ruleExists = $stmtRule->fetch(PDO::FETCH_ASSOC);
    if ($ruleExists) {
        $diaNombre = $nombreDias[$weekday] ?? 'este día';
        echo json_encode(['success' => false, 'message' => "Ese día ya está bloqueado de forma indefinida ({$diaNombre}).", 'day' => $diaNombre]);
        exit;
    }

    $force = !empty($body['force']);
    if (!$force) {
        $stmtRes = $pdo->prepare("SELECT COUNT(*) FROM reservas WHERE fecha = ? AND estado IN ('Pendiente','Aprobada')");
        $stmtRes->execute([$fecha]);
        $reservasCount = (int)$stmtRes->fetchColumn();
        if ($reservasCount > 0) {
            echo json_encode([
                'success' => false,
                'confirm' => true,
                'message' => 'Este día ya tiene reserva pendiente(s)/aprobada(s). ¿Quieres bloquearlo igualmente?',
                'reservas_count' => $reservasCount
            ]);
            exit;
        }
    }

    // El soporte antiguo de bloqueo semanal por rango se eliminó.

    // Modo manual (bloqueo exacto)
    try {
        $pdo->prepare("INSERT INTO dias_bloqueados (fecha, motivo) VALUES (?, ?)")->execute([$fecha, $motivo]);

        $cancelled = 0;
        if ($force) {
            $cancelled = cancelReservationsForDate($pdo, $fecha, $motivo);
        }

        $msg = 'Día bloqueado correctamente.';
        if ($cancelled > 0) {
            $msg .= " Se cancelaron {$cancelled} reserva(s) y se notificó a los usuarios.";
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['success' => false, 'message' => 'Ese día ya está bloqueado.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al bloquear el día.']);
        }
    }


} elseif ($method === 'DELETE') {
    // Soportar borrado de regla recurrente por regla_id(s)
    if (!empty($body['recurrent']) && !empty($body['rule_ids']) && is_array($body['rule_ids'])) {
        $rules = array_map('intval', $body['rule_ids']);
        // eliminar fechas asociadas
        $in = implode(',', array_fill(0, count($rules), '?'));
        $stmt = $pdo->prepare("DELETE FROM dias_bloqueados WHERE regla_id IN ($in)");
        $stmt->execute($rules);
        // eliminar reglas
        $stmt2 = $pdo->prepare("DELETE FROM dias_bloqueados_recurrent WHERE id IN ($in)");
        $stmt2->execute($rules);
        echo json_encode(['success' => true, 'message' => 'Reglas recurrentes y sus fechas asociadas han sido removidas.']);
        exit;
    }

    $id = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }
    $pdo->prepare("DELETE FROM dias_bloqueados WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Día desbloqueado.']);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
