<?php
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim($data['email']    ?? '');
$password = (string)($data['password'] ?? '');

if ($email === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email y contraseña requeridos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email no válido']);
    exit;
}

// Limitar longitud para evitar ataques de payload gigante
if (strlen($email) > 254 || strlen($password) > 1024) {
    echo json_encode(['success' => false, 'message' => 'Credenciales inválidas']);
    exit;
}

// Mensaje genérico: no revelar si el email existe o no
define('CREDENCIALES_INVALIDAS', 'Credenciales incorrectas.');

try {
    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, password, rol, failed_attempts, locked_until
         FROM usuarios WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Respuesta genérica si no existe (no revelar que el email no está registrado)
    if (!$user) {
        // Simular tiempo de bcrypt para evitar timing attack
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234');
        echo json_encode(['success' => false, 'message' => CREDENCIALES_INVALIDAS]);
        exit;
    }

    // Normalización mínima para tolerar variaciones y mapear roles con espacios
    $rolBruto = trim((string)($user['rol'] ?? ''));
    $rol = mb_strtolower($rolBruto, 'UTF-8');
    $rol = str_replace(['_', ' '], '-', $rol);
    $rol = preg_replace('/-+/', '-', $rol) ?: $rol;

    $aliasesRol = [
        'practicante-administrativo' => 'practicante',
        'logistica' => 'logística',
        'medios-audiovisuales' => 'medios audiovisuales'
    ];
    $rol = $aliasesRol[$rol] ?? $rol;

    // Roles permitidos (rol real en BD)
    $rolesPermitidos = [
        'administrativo',
        'practicante',
        'docente',
        'externo',
        'logística',
        'infraestructura',
        'medios audiovisuales'
    ];

    if (!in_array($rol, $rolesPermitidos, true)) {
        echo json_encode(['success' => false, 'message' => 'Rol no permitido']);
        exit;
    }

    if ($rol === 'externo' && preg_match('/@poligran\.edu\.co$/i', (string)$user['email'])) {
        echo json_encode(['success' => false, 'message' => 'Los usuarios @poligran.edu.co no pueden iniciar sesión como externo.']);
        exit;
    }

    if (in_array($rol, ['administrativo', 'practicante', 'docente', 'logística', 'infraestructura', 'medios audiovisuales'], true) && !is_local_request()) {
        if (!preg_match('/^[A-Za-z0-9._%+\-]+@poligran\.edu\.co$/i', (string)$user['email'])) {
            echo json_encode(['success' => false, 'message' => 'Para este rol se requiere correo @poligran.edu.co']);
            exit;
        }
    }

    $userId         = (int)$user['id'];
    $failedAttempts = (int)$user['failed_attempts'];
    $lockedUntil    = $user['locked_until'] ?? null;
    $now            = date('Y-m-d H:i:s');

    // Verificar bloqueo activo
    if (!empty($lockedUntil) && $lockedUntil > $now) {
        $remaining = max(1, strtotime($lockedUntil) - time());
        echo json_encode([
            'success'   => false,
            'locked'    => true,
            'remaining' => $remaining,
            'message'   => "Cuenta bloqueada. Espera {$remaining} segundos."
        ]);
        exit;
    }

    // Resetear bloqueo expirado
    if (!empty($lockedUntil) && $lockedUntil <= $now) {
        $pdo->prepare('UPDATE usuarios SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$userId]);
        $failedAttempts = 0;
    }

    // Verificar contraseña — SOLO bcrypt, sin comparación en texto plano
    $passDb = (string)($user['password'] ?? '');
    $valid  = ($passDb !== '' && password_verify($password, $passDb));

    if (!$valid) {
        $newAttempts = $failedAttempts + 1;
        $maxAttempts = 5;

        if ($newAttempts >= $maxAttempts) {
            // Bloqueo exponencial: 30 segundos base
            $lockSeconds = 30 * (2 ** max(0, intdiv($newAttempts, $maxAttempts) - 1));
            $lockSeconds = min($lockSeconds, 3600); // máximo 1 hora
            $lockTime    = date('Y-m-d H:i:s', time() + $lockSeconds);
            $pdo->prepare('UPDATE usuarios SET failed_attempts = ?, locked_until = ? WHERE id = ?')
                ->execute([$newAttempts, $lockTime, $userId]);
            echo json_encode([
                'success'   => false,
                'locked'    => true,
                'remaining' => $lockSeconds,
                'message'   => "Demasiados intentos fallidos. Cuenta bloqueada por {$lockSeconds} segundos."
            ]);
        } else {
            $restantes = $maxAttempts - $newAttempts;
            $pdo->prepare('UPDATE usuarios SET failed_attempts = ? WHERE id = ?')->execute([$newAttempts, $userId]);
            echo json_encode([
                'success' => false,
                'message' => CREDENCIALES_INVALIDAS . " Te quedan {$restantes} intento(s)."
            ]);
        }
        exit;
    }

    // Login correcto: resetear intentos
    $pdo->prepare('UPDATE usuarios SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$userId]);

    $_SESSION['user'] = [
        'id'     => $userId,
        'nombre' => $user['nombre'] ?? '',
        'email'  => $user['email']  ?? '',
        'rol'    => $rol,
    ];

    echo json_encode([
        'success'  => true,
        'message'  => 'Inicio de sesión exitoso',
        'redirect' => 'dashboard.php',
    ]);

} catch (Throwable $e) {
    error_log('login.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
