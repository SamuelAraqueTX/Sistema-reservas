<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
require_once '../session.php';
require_auth_api();

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener el precio del cobro.',
        'detail'  => (is_local_request() ? $e->getMessage() : null),
    ]);
    exit;
});

$espacio = strtoupper(trim($_GET['espacio'] ?? ''));

// Solo los EXTERNOS pueden consultar el cobro/tarifa.
// Para otros roles, devolvemos 0 para evitar que el frontend (o un atacante) use tarifas.
$userRol = trim((string)($_SESSION['user']['rol'] ?? $_SESSION['user']['role'] ?? ''));
if ($userRol !== 'externo') {
    echo json_encode([
        'success' => true,
        'espacio' => strtoupper(trim($_GET['espacio'] ?? '')),
        'precio'  => 0,
    ]);
    exit;
}

$espaciosValidos = ['B1', 'B2', 'B3'];
if (!in_array($espacio, $espaciosValidos, true)) {
    echo json_encode(['success' => false, 'message' => 'Espacio inválido.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT precio FROM cobros_tarifas WHERE espacio = ? LIMIT 1");
    $stmt->execute([$espacio]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $precio = $row ? (float)$row['precio'] : 0.0;

    // Normalizar a entero y aplicar límites de seguridad
    $precioInt = (int)round($precio);
    if ($precioInt < 0) $precioInt = 0;
    if ($precioInt > 100000) $precioInt = 100000;

    echo json_encode([
        'success' => true,
        'espacio' => $espacio,
        'precio'  => $precioInt,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar precio.', 'detail' => (is_local_request() ? $e->getMessage() : null)]);
}
