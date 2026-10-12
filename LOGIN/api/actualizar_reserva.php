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
$nuevoEstado = trim($data['estado'] ?? '');

if (!$idReserva || !in_array($nuevoEstado, ['Aprobada', 'Rechazada'])) {
    echo json_encode(['success' => false, 'message' => 'Datos insuficientes']);
    exit;
}

try {
    // 1. Actualizar estado en la DB
    $stmt = $pdo->prepare("UPDATE reservas SET estado = ? WHERE id = ?");
    $stmt->execute([$nuevoEstado, $idReserva]);

    echo json_encode(['success' => true, 'message' => 'Reserva ' . $nuevoEstado]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud']);
}