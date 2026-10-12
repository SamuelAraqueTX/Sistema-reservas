<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';
require_auth_api();

if (!in_array($_SESSION['user']['rol'], ['administrador', 'administrativo'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

try {
    if ($accion === 'listar') {
        // Obtener todas las preguntas
        $estado = $_GET['estado'] ?? 'todas';
        
        $sql = "SELECT id, nombre, email, mensaje, categoria, estado, created_at, respuesta
                FROM contactos";
        
        if ($estado !== 'todas') {
            $sql .= " WHERE estado = ?";
            $stmt = $pdo->prepare($sql . " ORDER BY created_at DESC LIMIT 100");
            $stmt->execute([$estado]);
        } else {
            $stmt = $pdo->prepare($sql . " ORDER BY created_at DESC LIMIT 100");
            $stmt->execute();
        }
        
        $contactos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'contactos' => $contactos
        ]);

    } elseif ($accion === 'marcar-leido') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE contactos SET estado = 'leído' WHERE id = ? AND estado = 'nuevo'");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Marcado como leído']);

    } elseif ($accion === 'responder') {
        $id = (int)($_POST['id'] ?? 0);
        $respuesta = trim($_POST['respuesta'] ?? '');
        
        if ($id <= 0 || empty($respuesta)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE contactos SET respuesta = ?, estado = 'respondido' WHERE id = ?");
        $stmt->execute([$respuesta, $id]);
        
        echo json_encode(['success' => true, 'message' => 'Respuesta guardada']);

    } elseif ($accion === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        
        $stmt = $pdo->prepare("DELETE FROM contactos WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode(['success' => true, 'message' => 'Contacto eliminado']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al procesar']);
    error_log("admin_contactos ERROR: " . $e->getMessage());
}
