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
        'message' => 'Error al obtener historial de cobros.',
        'detail'  => (is_local_request() ? $e->getMessage() : null),
    ]);
    exit;
});

$me = $_SESSION['user'] ?? null;
$rolesPermitidos = ['administrativo', 'practicante'];
if (!$me || !in_array($me['rol'] ?? '', $rolesPermitidos, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$limit = (int)($_GET['limit'] ?? 50);
if ($limit < 1 || $limit > 200) $limit = 50;

try {
    $stmt = $pdo->prepare("
        SELECT
            espacio,
            precio_anterior,
            precio_nuevo,
            created_at,
            usuario_nombre
        FROM cobros_historial
        ORDER BY created_at DESC, id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'historial' => array_map(function ($r) {
            return [
                'espacio' => $r['espacio'],
                'precio_anterior' => (int)round((float)$r['precio_anterior']),
                'precio_nuevo' => (int)round((float)$r['precio_nuevo']),
                'created_at' => $r['created_at'],
                'usuario_nombre' => $r['usuario_nombre'] ?? null,
            ];
        }, $rows)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar historial.', 'detail' => (is_local_request() ? $e->getMessage() : null)]);
}
