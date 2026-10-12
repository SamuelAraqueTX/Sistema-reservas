<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';
require_auth_api();

// Solo Administrativo y Practicante pueden editar
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$reservaId = isset($data['id']) ? (int)$data['id'] : 0;
$tipoOcupacion = trim($data['tipo_ocupacion'] ?? '');

$stmtCol = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'tipo_ocupacion'");
if ($stmtCol->fetch(PDO::FETCH_ASSOC) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Columna tipo_ocupacion no encontrada en la tabla reservas.']);
    exit;
}

if ($reservaId <= 0 || !$tipoOcupacion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$tiposOcupacionValidos = ['Clases particulares', 'Evento', 'Ocupación de logística', 'Ocupación de infraestructura', 'Ocupación de medios audiovisuales'];

if (!in_array($tipoOcupacion, $tiposOcupacionValidos, true)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de ocupación inválido.']); 
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE reservas SET tipo_ocupacion = ? WHERE id = ? LIMIT 1");
    $stmt->execute([$tipoOcupacion, $reservaId]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Tipo de ocupación actualizado']);
        exit;
    }

    // Si no se actualizó nada, puede ser porque el valor ya era el mismo.
    $check = $pdo->prepare("SELECT id FROM reservas WHERE id = ? LIMIT 1");
    $check->execute([$reservaId]);
    if ($check->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode(['success' => true, 'message' => 'Tipo de ocupación guardado (sin cambios)']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
    error_log("editar_tipo_ocupacion ERROR: " . $e->getMessage());
}
?>
