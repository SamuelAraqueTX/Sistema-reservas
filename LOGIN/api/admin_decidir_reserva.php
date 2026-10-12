<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';
require_once './mail_helper.php';
require_auth_api();

if (
    !in_array(
        $_SESSION['user']['rol'],
        ['administrativo', 'practicante'],
        true
    )
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// ✅ Soportar JSON Y FormData
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    // JSON desde JavaScript fetch()
    $input = file_get_contents('php://input');
    $data  = json_decode($input, true);
    $id     = isset($data['id'])     ? (int)$data['id']     : 0;
    $accion = isset($data['accion']) ? trim($data['accion']) : '';
} else {
    // FormData tradicional (fallback)
    $id     = isset($_POST['id'])     ? (int)$_POST['id']     : 0;
    $accion = isset($_POST['accion']) ? trim($_POST['accion']) : '';
}

// Validación
if ($id <= 0 || !in_array($accion, ['aprobar', 'rechazar'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => "Datos inválidos: se requiere id (entero > 0) y accion (aprobar|rechazar). Recibido: id={$id}, accion={$accion}"
    ]);
    error_log("admin_decidir_reserva: datos inválidos - id={$id}, accion={$accion}");
    exit;
}

try {
    // Obtener datos de la reserva + usuario ANTES de actualizar
    $sqlFetch = "SELECT r.id, r.fecha, r.hora_inicio, r.hora_fin, r.espacio,
                        u.id AS usuario_id, u.nombre, u.email
                 FROM reservas r
                 JOIN usuarios u ON u.id = r.usuario_id
                 WHERE r.id = ? AND r.estado = 'Pendiente'";

    $stmtFetch = $pdo->prepare($sqlFetch);
    $stmtFetch->execute([$id]);
    $reserva = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada o ya procesada']);
        error_log("admin_decidir_reserva: reserva no encontrada - id={$id}");
        exit;
    }

    $stmtRecCol = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'recibo_numero'");
    $hasRecibos = $stmtRecCol && $stmtRecCol->fetch(PDO::FETCH_ASSOC) !== false;

    if ($accion === 'aprobar') {
        if ($hasRecibos) {
            $stmt = $pdo->prepare("UPDATE reservas SET estado = 'Aprobada', recibo_estado_pago = IF(recibo_numero IS NOT NULL, 'Pagado', recibo_estado_pago) WHERE id = ? AND estado = 'Pendiente'");
        } else {
            $stmt = $pdo->prepare("UPDATE reservas SET estado = 'Aprobada' WHERE id = ? AND estado = 'Pendiente'");
        }
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            error_log("admin_decidir_reserva: reserva aprobada id={$id} por usuario " . ($_SESSION['user']['id'] ?? 'desconocido'));
        }

        echo json_encode(['success' => true, 'message' => 'Reserva aprobada correctamente']);

    } else {
        if ($hasRecibos) {
            $stmt = $pdo->prepare("UPDATE reservas SET estado = 'Rechazada', recibo_estado_pago = IF(recibo_numero IS NOT NULL, 'No pagado', recibo_estado_pago) WHERE id = ? AND estado = 'Pendiente'");
        } else {
            $stmt = $pdo->prepare("UPDATE reservas SET estado = 'Rechazada' WHERE id = ? AND estado = 'Pendiente'");
        }
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            error_log("admin_decidir_reserva: reserva rechazada id={$id} por usuario " . ($_SESSION['user']['id'] ?? 'desconocido'));
        }

        echo json_encode(['success' => true, 'message' => 'Reserva rechazada correctamente']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno al procesar la reserva']);
    error_log("admin_decidir_reserva ERROR: " . $e->getMessage());
}
?>
