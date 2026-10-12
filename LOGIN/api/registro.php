<?php
header('Content-Type: application/json');
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$nombre          = trim($data['nombre']           ?? '');
$email           = trim($data['email']            ?? '');
$password        = (string)($data['password']     ?? '');
$confirmPassword = (string)($data['confirm_password'] ?? '');
$rol             = trim($data['rol']              ?? '');
$codigoVerif     = trim($data['codigo_verificacion'] ?? '');

// Normalización mínima del rol
$rol = mb_strtolower($rol, 'UTF-8');
$rol = preg_replace('/\s+/', ' ', trim($rol));

// Solo estos roles pueden auto-registrarse
$rolesPublicos = ['docente', 'externo', 'logística', 'infraestructura', 'medios audiovisuales'];

if ($nombre === '' || $email === '' || $password === '' || $confirmPassword === '' || $rol === '') {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
    exit;
}

if ($codigoVerif === '') {
    echo json_encode(['success' => false, 'message' => 'Ingresa el código de verificación enviado a tu correo.']);
    exit;
}

if (!preg_match('/^\d{6}$/', $codigoVerif)) {
    echo json_encode(['success' => false, 'message' => 'El código debe ser de 6 dígitos.']);
    exit;
}

if (!in_array($rol, $rolesPublicos, true)) {
    echo json_encode(['success' => false, 'message' => 'Ese rol no se puede registrar aquí. Contacta al administrador.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    echo json_encode(['success' => false, 'message' => 'Email no válido']);
    exit;
}

if (in_array($rol, ['docente', 'logística', 'infraestructura', 'medios audiovisuales'], true) && !is_local_request()) {
    if (!preg_match('/^[A-Za-z0-9._%+\-]+@poligran\.edu\.co$/i', $email)) {
        echo json_encode(['success' => false, 'message' => 'Los roles internos deben usar un correo @poligran.edu.co']);
        exit;
    }
}

if ($rol === 'externo' && preg_match('/@poligran\.edu\.co$/i', $email)) {
    echo json_encode(['success' => false, 'message' => 'Los usuarios @poligran.edu.co no pueden registrarse como externo. Usa otro rol.']);
    exit;
}

if (strlen($nombre) > 100) {
    echo json_encode(['success' => false, 'message' => 'El nombre no puede superar los 100 caracteres']);
    exit;
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

if (strlen($password) > 1024) {
    echo json_encode(['success' => false, 'message' => 'Contraseña demasiado larga']);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
    exit;
}

try {
    // ── Validar código de verificación ──────────────────────────────────────
    $stmtCod = $pdo->prepare("
        SELECT id FROM codigos_verificacion
        WHERE email = ? AND codigo = ? AND usado = 0 AND expires_at > NOW()
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmtCod->execute([$email, $codigoVerif]);
    $codRow = $stmtCod->fetch(PDO::FETCH_ASSOC);

    if (!$codRow) {
        echo json_encode(['success' => false, 'message' => 'Código incorrecto o expirado. Solicita uno nuevo.']);
        exit;
    }

    // ── Verificar email único ────────────────────────────────────────────────
    $stmtCheck = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
        exit;
    }

    // ── Insertar usuario y marcar código como usado (transacción) ────────────
    $pdo->beginTransaction();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password, rol, failed_attempts, locked_until)
         VALUES (?, ?, ?, ?, 0, NULL)'
    )->execute([$nombre, $email, $hash, $rol]);

    $pdo->prepare("UPDATE codigos_verificacion SET usado = 1 WHERE id = ?")
        ->execute([$codRow['id']]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Registro exitoso. Ya puedes iniciar sesión.']);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error en registro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al registrar usuario']);
}
