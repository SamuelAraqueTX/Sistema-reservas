<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once 'mail_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($data['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    echo json_encode(['success' => false, 'message' => 'Correo electrónico no válido.']);
    exit;
}

try {
    // Verificar que el email NO esté ya registrado
    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Este correo ya tiene una cuenta registrada.']);
        exit;
    }

    // Limitar intentos: máx 3 códigos activos en los últimos 15 min
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) FROM codigos_verificacion
        WHERE email = ? AND usado = 0 AND expires_at > NOW()
          AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmtCheck->execute([$email]);
    if ((int)$stmtCheck->fetchColumn() >= 3) {
        echo json_encode(['success' => false, 'message' => 'Demasiados intentos. Espera unos minutos.']);
        exit;
    }

    // Invalidar códigos anteriores del mismo email
    $pdo->prepare("UPDATE codigos_verificacion SET usado = 1 WHERE email = ? AND usado = 0")
        ->execute([$email]);

    // Generar código de 6 dígitos
    $codigo     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    $pdo->prepare("INSERT INTO codigos_verificacion (email, codigo, expires_at) VALUES (?, ?, ?)")
        ->execute([$email, $codigo, $expires_at]);

    // Enviar correo
    $subject = 'Código de verificación - Reservas Poli';
    $body    = build_verificacion_email($email, $codigo);
    $error   = null;
    $sent    = send_system_email($email, $subject, $body, $error);

    if (!$sent) {
        echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo. Intenta de nuevo.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Código enviado a ' . $email . '. Revisa tu bandeja (expira en 10 minutos).'
    ]);

} catch (Throwable $e) {
    error_log('enviar_verificacion_registro ERROR: ' . $e->getMessage() . ' | Line: ' . $e->getLine() . ' | File: ' . $e->getFile());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno. Intenta de nuevo.']);
}

function build_verificacion_email(string $email, string $codigo): string
{
    $emailSafe  = htmlspecialchars($email);
    $codigoSafe = htmlspecialchars($codigo);
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f4f7ff;margin:0;padding:20px;">
  <div style="background:#fff;border-radius:10px;max-width:480px;margin:auto;
              box-shadow:0 4px 18px rgba(0,0,0,.10);overflow:hidden;">
    <div style="background:#1a237e;color:#fff;padding:24px;text-align:center;">
      <h1 style="margin:0;font-size:20px;">Sistema de Reservas Poli</h1>
      <p style="margin:6px 0 0;opacity:.85;font-size:14px;">Verificación de correo electrónico</p>
    </div>
    <div style="padding:28px 32px;">
      <p style="color:#374151;font-size:15px;">
        Alguien intentó crear una cuenta con el correo <strong>{$emailSafe}</strong>.
        Si fuiste tú, usa el siguiente código para confirmar tu registro:
      </p>
      <div style="text-align:center;margin:24px 0;">
        <span style="display:inline-block;background:#1a237e;color:#fff;font-size:34px;
                     font-weight:700;letter-spacing:14px;padding:18px 32px;border-radius:10px;">
          {$codigoSafe}
        </span>
      </div>
      <p style="color:#6b7280;font-size:13px;text-align:center;">
        Este código expira en <strong>10 minutos</strong>.
      </p>
      <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">
      <p style="color:#9ca3af;font-size:12px;">
        Si no intentaste registrarte, ignora este correo. Nadie podrá crear una cuenta sin este código.
      </p>
    </div>
    <div style="background:#f9faff;text-align:center;padding:14px;
                color:#9ca3af;font-size:12px;border-top:1px solid #e5e7eb;">
      Sistema de Reservas Poli &mdash; Correo automático, no responder.
    </div>
  </div>
</body>
</html>
HTML;
}
