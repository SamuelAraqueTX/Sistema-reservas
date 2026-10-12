<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data        = json_decode(file_get_contents('php://input'), true) ?? [];
$email       = trim($data['email']            ?? '');
$codigo      = trim($data['codigo']           ?? '');
$newPass     = $data['new_password']          ?? '';
$confirmPass = $data['confirm_password']      ?? '';

if (!$email || !$codigo || !$newPass || !$confirmPass) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit;
}

if (strlen($newPass) < 8) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres.']);
    exit;
}

if ($newPass !== $confirmPass) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden.']);
    exit;
}

try {
    // Verificar código: debe ser válido, no usado y no expirado
    $stmt = $pdo->prepare("
        SELECT id FROM codigos_recuperacion
        WHERE email = ? AND codigo = ? AND usado = 0 AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$email, $codigo]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$registro) {
        echo json_encode(['success' => false, 'message' => 'Código incorrecto o expirado. Solicita uno nuevo.']);
        exit;
    }

    // Verificar que la cuenta existe
    $stmtUser = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmtUser->execute([$email]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Cuenta no encontrada.']);
        exit;
    }

    // Marcar código como usado
    $pdo->prepare("UPDATE codigos_recuperacion SET usado = 1 WHERE id = ?")
        ->execute([$registro['id']]);

    // Actualizar contraseña
    $pdo->prepare("UPDATE usuarios SET password = ?, failed_attempts = 0, locked_until = NULL WHERE id = ?")
        ->execute([password_hash($newPass, PASSWORD_BCRYPT), $user['id']]);

    echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.']);

} catch (Throwable $e) {
    error_log('recuperar_password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno. Intenta de nuevo.']);
}
