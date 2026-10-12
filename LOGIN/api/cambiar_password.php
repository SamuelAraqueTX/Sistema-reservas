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

$data        = json_decode(file_get_contents('php://input'), true) ?? [];
$userId      = (int)$_SESSION['user']['id'];
$currentPass = (string)($data['current_password'] ?? '');
$newPass     = (string)($data['new_password']     ?? '');
$confirmPass = (string)($data['confirm_password'] ?? '');

if ($currentPass === '' || $newPass === '' || $confirmPass === '') {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
    exit;
}

if (strlen($newPass) < 8) {
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres']);
    exit;
}

if (strlen($newPass) > 1024) {
    echo json_encode(['success' => false, 'message' => 'Contraseña demasiado larga']);
    exit;
}

if ($newPass !== $confirmPass) {
    echo json_encode(['success' => false, 'message' => 'Las nuevas contraseñas no coinciden']);
    exit;
}

$stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión inválida']);
    exit;
}

if (!password_verify($currentPass, (string)$user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
    exit;
}

$hash = password_hash($newPass, PASSWORD_DEFAULT);
$pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?")->execute([$hash, $userId]);

echo json_encode(['success' => true, 'message' => 'Contraseña cambiada exitosamente']);
?>
