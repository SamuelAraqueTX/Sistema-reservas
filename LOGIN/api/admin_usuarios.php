<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

require_once '../session.php';
require_once '../config.php';
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

$roles_validos = ['administrativo', 'practicante', 'docente', 'externo', 'logística', 'infraestructura', 'medios audiovisuales'];

// ─── GET: listar usuarios ─────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $stmt    = $pdo->query("SELECT id, nombre, email, rol, created_at FROM usuarios ORDER BY created_at DESC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Asegurar UTF-8 válido para json_encode
        foreach ($usuarios as &$u) {
            foreach ($u as $k => $v) {
                if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
                    $u[$k] = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
                }
            }
        }
        unset($u);
        $json = json_encode(['success' => true, 'usuarios' => $usuarios], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new RuntimeException('json_encode falló: ' . json_last_error_msg());
        }
        echo $json;
    } catch (Throwable $e) {
        error_log('admin_usuarios GET: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al obtener usuarios: ' . $e->getMessage()]);
    }

// ─── POST: crear usuario ──────────────────────────────────────────────────────
} elseif ($method === 'POST') {
    $nombre   = trim($body['nombre']   ?? '');
    $email    = trim($body['email']    ?? '');
    $password = $body['password']      ?? '';
    $rol      = trim($body['rol']      ?? '');  // SANITIZAR espacios
    
    // Compatibilidad UI antigua: aceptar variaciones de nombres
    if ($rol === 'practicante-administrativo') {
        $rol = 'practicante';
    }

    if (!$nombre || !$email || !$password || !in_array($rol, $roles_validos, true)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido.']); exit;
    }
    if (in_array($rol, ['administrativo', 'practicante', 'docente', 'logística', 'infraestructura', 'medios audiovisuales'], true) && !preg_match('/^[A-Za-z0-9._%+\-]+@poligran\.edu\.co$/i', $email)) {
        echo json_encode(['success' => false, 'message' => 'Los roles internos deben usar un correo @poligran.edu.co.']); exit;
    }
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, password_hash($password, PASSWORD_BCRYPT), $rol]);
        error_log("POST admin_usuarios: new user {$email} created with rol={$rol}");
        echo json_encode(['success' => true, 'message' => 'Usuario creado.', 'id' => (int)$pdo->lastInsertId()]);
    } catch (PDOException $e) {
        error_log("ERROR POST admin_usuarios: " . $e->getMessage());
        $msg = $e->getCode() === '23000' ? 'El email ya está registrado.' : 'Error al crear usuario: ' . $e->getMessage();
        echo json_encode(['success' => false, 'message' => $msg]);
    }

// ─── PUT: editar usuario (con cambio opcional de contraseña) ──────────────────
} elseif ($method === 'PUT') {
    $id       = intval($body['id']       ?? 0);
    $nombre   = trim($body['nombre']     ?? '');
    $email    = trim($body['email']      ?? '');
    $rol      = trim($body['rol']        ?? '');  // SANITIZAR: eliminar espacios
    
    // Compatibilidad UI antigua: aceptar "practicante-administrativo" y convertirlo a "practicante"
    if ($rol === 'practicante-administrativo') {
        $rol = 'practicante';
    }
    $password = $body['password']        ?? '';   // vacío = no cambiar

    if (!$id || !$nombre || !$email || !in_array($rol, $roles_validos, true)) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos. rol recibido: "' . ($body['rol'] ?? 'NULL') . '", después sanitizar: "' . $rol . '"']); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido.']); exit;
    }
    if (in_array($rol, ['administrativo', 'practicante', 'docente', 'logística', 'infraestructura', 'medios audiovisuales'], true) && !preg_match('/^[A-Za-z0-9._%+\-]+@poligran\.edu\.co$/i', $email)) {
        echo json_encode(['success' => false, 'message' => 'Los roles internos deben usar un correo @poligran.edu.co.']); exit;
    }
    if ($password !== '' && strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']); exit;
    }
    // Evitar que el admin se baje de rol a sí mismo
    if ($id === intval($me['id']) && $rol !== $me['rol']) {
        echo json_encode(['success' => false, 'message' => 'No puedes cambiar tu propio rol.']); exit;
    }

    try {
        if ($password !== '') {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, password = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, password_hash($password, PASSWORD_BCRYPT), $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol, $id]);
        }
        
        // Log para debug
        $rowCount = $stmt->rowCount();
        error_log("PUT admin_usuarios: id={$id}, email={$email}, rol={$rol}, rowCount={$rowCount}");
        
        // Si no hubo error de BD, consideramos exitoso (aunque rowCount sea 0)
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado.']);
    } catch (PDOException $e) {
        error_log("ERROR PUT admin_usuarios: " . $e->getMessage());
        $msg = $e->getCode() === '23000' ? 'El email ya está en uso.' : 'Error al actualizar usuario: ' . $e->getMessage();
        echo json_encode(['success' => false, 'message' => $msg]);
    }

// ─── DELETE: eliminar usuario ─────────────────────────────────────────────────
} elseif ($method === 'DELETE') {
    $id = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }
    if ($id === intval($me['id'])) {
        echo json_encode(['success' => false, 'message' => 'No puedes eliminarte a ti mismo.']); exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado.']);
    } catch (PDOException $e) {
        // FK constraint → usuario tiene reservas
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar: el usuario tiene reservas activas.']);
    }

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
