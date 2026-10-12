<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';
require_auth_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$idReserva = (int)($data['id_reserva'] ?? 0);
$userId = $_SESSION['user']['id'];

try {
    // Verificar propiedad y que esté pendiente
    $stmt = $pdo->prepare("SELECT estado FROM reservas WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$idReserva, $userId]);
    $reserva = $stmt->fetch();

    if (!$reserva) {
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
        exit;
    }

    if ($reserva['estado'] !== 'Pendiente') {
        echo json_encode(['success' => false, 'message' => 'Solo se pueden cancelar reservas en estado Pendiente']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'Cancelada' WHERE id = ?");
    $stmt->execute([$idReserva]);

    echo json_encode(['success' => true, 'message' => 'Reserva cancelada correctamente']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar la cancelación']);
}