<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../config/reservas_logic.php';
require_once '../session.php';
require_auth_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$usuario_id  = (int)($_SESSION['user']['id'] ?? 0);
$fecha       = trim($data['fecha'] ?? '');
$hora_inicio = trim($data['hora_inicio'] ?? '');
$hora_fin    = trim($data['hora_fin'] ?? '');
$espacio     = strtoupper(trim($data['espacio'] ?? ''));
$requisitos  = trim($data['requisitos'] ?? '');

if ($usuario_id <= 0 || $fecha === '' || $hora_inicio === '' || $hora_fin === '' || $espacio === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
    exit;
}

if ($hora_inicio >= $hora_fin) {
    echo json_encode(['success' => false, 'message' => 'La hora inicio debe ser menor a hora fin']);
    exit;
}

try {
    $hoy = new DateTime('today');
    $max = (new DateTime('today'))->modify('+3 months');
    $fechaObj = new DateTime($fecha); // Soporta YYYY-MM-DD
    
    if ($fechaObj < $hoy || $fechaObj > $max) {
        echo json_encode(['success' => false, 'message' => 'La fecha debe estar entre hoy y los próximos 3 meses']);
        exit;
    }




    // Reutilizar lógica centralizada de conflicto de reserva
    $resultado = verificarConflictoReserva($pdo, $espacio, $fecha, $hora_inicio, $hora_fin);
    if (!$resultado['disponible']) {
        echo json_encode([
            'success' => false,
            'message' => $resultado['mensaje']
        ]);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO reservas (usuario_id, fecha, hora_inicio, hora_fin, espacio, requisitos, estado) VALUES (?, ?, ?, ?, ?, ?, 'Pendiente')");
    $result = $stmt->execute([$usuario_id, $fecha, $hora_inicio, $hora_fin, $espacio, $requisitos]);
    
    if ($result) {
        $id_reserva = (int)$pdo->lastInsertId();
        error_log("Reserva creada exitosamente: id={$id_reserva}, usuario={$usuario_id}, espacio={$espacio}, fecha={$fecha}");
        echo json_encode(['success' => true, 'message' => 'Reserva creada y pendiente de aprobación', 'id' => $id_reserva]);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Error INSERT reservas: " . json_encode($errorInfo));
        echo json_encode(['success' => false, 'message' => 'No se pudo insertar la reserva en BD']);
    }
} catch (Exception $e) {
    error_log('crear_reserva.php EXCEPTION: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al crear reserva: ' . $e->getMessage()]);
}
