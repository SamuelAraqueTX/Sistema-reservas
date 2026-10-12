<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../session.php';
require_once '../config.php';
require_auth_api();

$me = $_SESSION['user'];

// Amortiguar warnings/notices que rompan JSON
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar recursos.',
        'detail'  => (is_local_request() ? $e->getMessage() : null),
    ]);
    exit;
});

// Compatibilidad: el rol nuevo es "practicante" y reemplaza a "practicante-administrativo"
$rolesAdminRecursos = ['administrativo', 'practicante'];

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// GET: Todos los roles pueden leer recursos
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, nombre, descripcion, cantidad, created_at FROM recursos ORDER BY nombre ASC");
    echo json_encode(['success' => true, 'recursos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// POST/PUT/DELETE: Solo administrativo y practicante pueden modificar
if (!in_array($me['rol'], $rolesAdminRecursos, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($method === 'POST') {
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $cantidad    = intval($body['cantidad']  ?? 0);

    // Límites razonables de UX/DB/validación
    $MAX_NOMBRE = 20;
    $MAX_DESC   = 100;

    if (!$nombre) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']); exit;
    }
    if (mb_strlen($nombre, 'UTF-8') > $MAX_NOMBRE) {
        echo json_encode(['success' => false, 'message' => "El nombre no puede exceder {$MAX_NOMBRE} caracteres."]); exit;
    }
    if (mb_strlen($descripcion, 'UTF-8') > $MAX_DESC) {
        echo json_encode(['success' => false, 'message' => "La descripción no puede exceder {$MAX_DESC} caracteres."]); exit;
    }
    if ($cantidad < 0) {
        echo json_encode(['success' => false, 'message' => 'La cantidad no puede ser negativa.']); exit;
    }

    $stmt = $pdo->prepare("INSERT INTO recursos (nombre, descripcion, cantidad) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $descripcion, $cantidad]);
    echo json_encode(['success' => true, 'message' => 'Recurso creado.', 'id' => (int)$pdo->lastInsertId()]);

} elseif ($method === 'PUT') {
    $id          = intval($body['id']        ?? 0);
    $nombre      = trim($body['nombre']      ?? '');
    $descripcion = trim($body['descripcion'] ?? '');
    $cantidad    = intval($body['cantidad']  ?? 0);

    // Límites razonables de UX/DB/validación
    $MAX_NOMBRE = 20;
    $MAX_DESC   = 100;

    if (!$id || !$nombre) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']); exit;
    }
    if (mb_strlen($nombre, 'UTF-8') > $MAX_NOMBRE) {
        echo json_encode(['success' => false, 'message' => "El nombre no puede exceder {$MAX_NOMBRE} caracteres."]); exit;
    }
    if (mb_strlen($descripcion, 'UTF-8') > $MAX_DESC) {
        echo json_encode(['success' => false, 'message' => "La descripción no puede exceder {$MAX_DESC} caracteres."]); exit;
    }
    if ($cantidad < 0) {
        echo json_encode(['success' => false, 'message' => 'La cantidad no puede ser negativa.']); exit;
    }

    $stmt = $pdo->prepare("UPDATE recursos SET nombre = ?, descripcion = ?, cantidad = ? WHERE id = ?");
    $stmt->execute([$nombre, $descripcion, $cantidad, $id]);
    echo json_encode(['success' => true, 'message' => 'Recurso actualizado.']);

} elseif ($method === 'DELETE') {
    $id = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }

    // Prevent: no permitir borrar un recurso que esté referenciado por reservas
    $stmtUsed = $pdo->prepare("SELECT COUNT(*) FROM reserva_recursos WHERE recurso_id = ?");
    $stmtUsed->execute([$id]);
    $usedCount = (int)$stmtUsed->fetchColumn();

    if ($usedCount > 0) {
        echo json_encode([
            'success' => false,
            'message' => "No se puede eliminar: el recurso está siendo usado en reservas."
        ]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM recursos WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Recurso eliminado.']);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
