<?php
/**
 * mail_helper.php
 * Funciones de envío de correo para el Sistema de Reservas.
 * Usa PHPMailer si está disponible; si no, usa mail() nativo de PHP.
 * Siempre guarda una copia en mail_log.txt para debugging.
 */

// ============================================================
// CARGA DE PHPMAILER VIA COMPOSER
// ============================================================
$_phpmailerAvailable = false;
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    $_phpmailerAvailable = true;
}

// ============================================================
// CONFIGURACIÓN DEL REMITENTE (NUNCA escribir credenciales aquí)
// ============================================================
// Los valores se leen de variables de entorno o de config.local.php
// (archivo local, ignorado por git; ver config.local.example.php).
if (file_exists(__DIR__ . '/../config.local.php')) {
    require_once __DIR__ . '/../config.local.php';
}

define('MAIL_FROM',      (string)(getenv('MAIL_USER') ?: ''));
define('MAIL_FROM_NAME', (string)(getenv('MAIL_FROM_NAME') ?: 'Sistema de Reservas Poli'));
define('MAIL_PASSWORD',  (string)(getenv('MAIL_PASS') ?: ''));
define('MAIL_CONFIGURED', MAIL_FROM !== '' && MAIL_PASSWORD !== '');
// Datos institucionales opcionales (si quedan vacíos, el aviso correspondiente no se muestra)
define('VISITORS_EMAIL', (string)(getenv('VISITORS_EMAIL') ?: ''));
define('SERVICEDESK_URL', (string)(getenv('SERVICEDESK_URL') ?: ''));
// Log de correos: SOLO en modo desarrollo (sin credenciales) o si se activa MAIL_DEBUG_LOG=1.
// Se guarda fuera de la carpeta web para que no sea accesible por URL.
define('MAIL_LOG_ENABLED', !MAIL_CONFIGURED || getenv('MAIL_DEBUG_LOG') === '1');
define('MAIL_LOG_PATH', (string)(getenv('MAIL_LOG_PATH') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'reservas_mail_log.txt'));

/**
 * Bloque HTML con el enlace al portal de solicitudes y el correo de visitantes.
 * Solo muestra lo que esté configurado (SERVICEDESK_URL / VISITORS_EMAIL).
 */
function build_support_links_html(string $visitorsVerb = 'relaciona'): string
{
    $html = '';
    if (SERVICEDESK_URL !== '') {
        $u = htmlspecialchars(SERVICEDESK_URL, ENT_QUOTES, 'UTF-8');
        $html .= '<br><br><a href="' . $u . '" target="_blank" rel="noopener noreferrer">' . $u . '</a>';
    }
    if (VISITORS_EMAIL !== '') {
        $m = htmlspecialchars(VISITORS_EMAIL, ENT_QUOTES, 'UTF-8');
        $html .= '<br><br>Así mismo, ' . $visitorsVerb . ' los visitantes en el correo <a href="mailto:' . $m . '">' . $m . '</a>';
    }
    return $html;
}

/**
 * Texto plano equivalente para las respuestas JSON.
 */
function build_support_links_text(): string
{
    $t = '';
    if (VISITORS_EMAIL !== '') {
        $t .= "\n• Relaciona los visitantes en el correo " . VISITORS_EMAIL . ".";
    }
    if (SERVICEDESK_URL !== '') {
        $t .= "\n\n" . SERVICEDESK_URL;
    }
    return $t;
}

function mail_log_write(string $text): void
{
    if (MAIL_LOG_ENABLED) {
        @file_put_contents(MAIL_LOG_PATH, $text, FILE_APPEND | LOCK_EX);
    }
}

// ============================================================
// FUNCIÓN PRINCIPAL: send_system_email()
// ============================================================
/**
 * Envía un correo electrónico.
 * Intenta PHPMailer/SMTP si está configurado; si no, usa mail() nativo.
 * Solo registra en el log local en modo desarrollo (ver MAIL_LOG_ENABLED).
 *
 * @param string      $to      Dirección de destino
 * @param string      $subject Asunto del correo
 * @param string      $body    Cuerpo HTML del correo
 * @param string|null $error   Variable de salida para mensajes de error
 * @return bool
 */
function send_system_email(string $to, string $subject, string $body, ?string &$error = null): bool
{
    global $_phpmailerAvailable;

    // --- Guardar en log local (solo modo desarrollo) ---
    $logContent  = "==========================================\r\n";
    $logContent .= "[" . date('Y-m-d H:i:s') . "] PARA: {$to}\r\n";
    $logContent .= "ASUNTO: {$subject}\r\n";
    $logContent .= "MENSAJE:\r\n{$body}\r\n";
    $logContent .= "==========================================\r\n\r\n";
    mail_log_write($logContent);

    // --- Si no hay credenciales reales, simular éxito (modo desarrollo) ---
    if (!MAIL_CONFIGURED) {
        error_log("mail_helper: modo simulación (MAIL_USER/MAIL_PASS no configurados) — correo para {$to}");
        return true;
    }

    // --- Intentar PHPMailer + SMTP ---
    if ($_phpmailerAvailable) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_FROM;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();
            error_log("mail_helper: correo enviado via SMTP a {$to}");
            return true;

        } catch (\Exception $e) {
            $error = "SMTP error: " . $e->getMessage();
            mail_log_write("ERROR SMTP: {$error}\r\n");
            error_log("mail_helper SMTP falló: {$error}");
            // Caer a mail() nativo
        }
    }

    // --- Fallback: mail() nativo de PHP ---
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    if (MAIL_FROM !== '') {
        $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    }
    $headers .= "X-Mailer: PHP/" . phpversion();

    $sent = @mail($to, $subject, $body, $headers);
    if ($sent) {
        error_log("mail_helper: correo enviado via mail() nativo a {$to}");
    } else {
        $error = "mail() nativo falló para {$to}";
        mail_log_write("ERROR mail(): {$error}\r\n");
        error_log("mail_helper: {$error}");
    }
    return $sent;
}

// ============================================================
// FUNCIÓN: build_approval_email()
// Construye el HTML del correo de APROBACIÓN
// ============================================================
/**
 * @param array $reserva  Fila de BD con: nombre, espacio, fecha, hora_inicio, hora_fin
 * @return string HTML del correo
 */
function build_approval_email(array $reserva): string
{
    $nombre     = htmlspecialchars($reserva['nombre']      ?? 'Usuario');
    $espacio    = htmlspecialchars($reserva['espacio']     ?? '');
    $fecha      = htmlspecialchars($reserva['fecha']       ?? '');
    $horaInicio = htmlspecialchars(substr($reserva['hora_inicio'] ?? '', 0, 5));
    $horaFin    = htmlspecialchars(substr($reserva['hora_fin']    ?? '', 0, 5));

    // Formatear fecha legible
    $fechaLegible = $fecha;
    if ($fecha) {
        try {
            $dt = new DateTime($fecha);
            $fechaLegible = $dt->format('d/m/Y');
        } catch (Exception $e) { /* usar fecha cruda */ }
    }

    $supportLinks = build_support_links_html('relacionar');
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7ff; margin: 0; padding: 20px; }
    .card { background: #fff; border-radius: 10px; max-width: 520px; margin: auto;
            padding: 32px; box-shadow: 0 4px 18px rgba(0,0,0,0.10); }
    .header { background: #1a237e; color: #fff; border-radius: 8px 8px 0 0;
              padding: 20px 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 22px; }
    .badge { display: inline-block; background: #28a745; color: #fff;
             border-radius: 20px; padding: 6px 18px; font-size: 15px;
             font-weight: bold; margin: 16px 0; }
    .detail { background: #f0f4ff; border-radius: 8px; padding: 16px 20px; margin: 16px 0; }
    .detail p { margin: 8px 0; font-size: 15px; color: #333; }
    .detail strong { color: #1a237e; }
    .footer { text-align: center; color: #888; font-size: 12px; margin-top: 24px; }
    .note { background: #fff8e1; border-left: 4px solid #ffc107;
            padding: 10px 14px; border-radius: 4px; font-size: 13px; color: #555; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>🏛️ Sistema de Reservas Poli</h1>
    </div>
    <div style="padding: 24px;">
      <p>Hola, <strong>{$nombre}</strong></p>
      <p>Nos complace informarte que tu solicitud de reserva ha sido:</p>
      <div style="text-align:center;">
        <span class="badge">✅ APROBADA</span>
      </div>
      <div class="detail">
        <p>📍 <strong>Espacio:</strong> Auditorio {$espacio}</p>
        <p>📅 <strong>Fecha:</strong> {$fechaLegible}</p>
        <p>🕐 <strong>Hora:</strong> {$horaInicio} – {$horaFin}</p>
      </div>
      <div class="note">
        Confirmo reserva; recuerda que, si requieres apoyo de medios audiovisuales,
        debes cargar la solicitud en la plataforma (comparto enlace);
        las solicitudes que no estén registradas por este medio no podrán ser atendidas.
        {$supportLinks}
      </div>
      <p style="margin-top:20px; color:#555; font-size:14px;">
        Si tienes alguna pregunta, comunícate con la administración.
      </p>
    </div>
    <div class="footer">
      Sistema de Reservas Poli &mdash; Correo automático, no responder.
    </div>
  </div>
</body>
</html>
HTML;
}

// ============================================================
// FUNCIÓN: build_pending_email()
// Construye el HTML del correo de SOLICITUD PENDIENTE
// ============================================================
function build_pending_email(array $reserva, ?string $reciboUrl = null): string
{
    $nombre     = htmlspecialchars($reserva['nombre']      ?? 'Usuario');
    $espacio    = htmlspecialchars($reserva['espacio']     ?? '');
    $fecha      = htmlspecialchars($reserva['fecha']       ?? '');
    $horaInicio = htmlspecialchars(substr($reserva['hora_inicio'] ?? '', 0, 5));
    $horaFin    = htmlspecialchars(substr($reserva['hora_fin']    ?? '', 0, 5));

    $fechaLegible = $fecha;
    if ($fecha) {
        try {
            $dt = new DateTime($fecha);
            $fechaLegible = $dt->format('d/m/Y');
        } catch (Exception $e) { /* usar fecha cruda */ }
    }

    $reciboSection = '';
    if ($reciboUrl) {
        $reciboUrlSafe = htmlspecialchars($reciboUrl);
        $reciboSection = <<<HTML
      <div style="margin-top:24px; padding:20px; background:#e8f0ff; border-radius:12px;">
        <p style="margin:0 0 12px; font-size:15px; color:#1a237e;"><strong>Factura / recibo generado</strong></p>
        <p style="margin:0 0 16px; font-size:14px; color:#333;">
          Tu factura fue generada automáticamente y puedes verla desde el siguiente enlace:
        </p>
        <p style="text-align:center; margin:0;">
          <a href="{$reciboUrlSafe}" target="_blank" rel="noopener noreferrer"
             style="display:inline-block; padding:12px 20px; background:#1a237e; color:#fff; border-radius:8px; text-decoration:none; font-weight:bold;">
             Ver factura / recibo
          </a>
        </p>
        <p style="margin:16px 0 0; font-size:13px; color:#555;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
        <p style="word-break:break-all; font-size:13px; color:#303f9f;">{$reciboUrlSafe}</p>
      </div>
HTML;
    }

    $supportLinks = build_support_links_html('relaciona');
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7ff; margin: 0; padding: 20px; }
    .card { background: #fff; border-radius: 10px; max-width: 520px; margin: auto;
            padding: 32px; box-shadow: 0 4px 18px rgba(0,0,0,0.10); }
    .header { background: #1a237e; color: #fff; border-radius: 8px 8px 0 0;
              padding: 20px 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 22px; }
    .badge { display: inline-block; background: #ffc107; color: #1a237e;
             border-radius: 20px; padding: 6px 18px; font-size: 15px;
             font-weight: bold; margin: 16px 0; }
    .detail { background: #f0f4ff; border-radius: 8px; padding: 16px 20px; margin: 16px 0; }
    .detail p { margin: 8px 0; font-size: 15px; color: #333; }
    .detail strong { color: #1a237e; }
    .footer { text-align: center; color: #888; font-size: 12px; margin-top: 24px; }
    .note { background: #fff8e1; border-left: 4px solid #ffc107;
            padding: 10px 14px; border-radius: 4px; font-size: 13px; color: #555; }
    a { color: #1a237e; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>🏛️ Sistema de Reservas Poli</h1>
    </div>
    <div style="padding: 24px;">
      <p>Hola, <strong>{$nombre}</strong></p>
      <p>Tu solicitud de reserva ha sido registrada y está pendiente de aprobación.</p>
      <div style="text-align:center;">
        <span class="badge">🕒 PENDIENTE</span>
      </div>
      <div class="detail">
        <p>📍 <strong>Espacio:</strong> Auditorio {$espacio}</p>
        <p>📅 <strong>Fecha:</strong> {$fechaLegible}</p>
        <p>🕐 <strong>Hora:</strong> {$horaInicio} – {$horaFin}</p>
      </div>
      {$reciboSection}
      <div class="note">
        Recuerda que si requieres apoyo de medios audiovisuales debes cargar la solicitud en la plataforma:
        {$supportLinks}
      </div>
      <p style="margin-top:20px; color:#555; font-size:14px;">
        Si tienes alguna pregunta, comunícate con la administración.
      </p>
    </div>
    <div class="footer">
      Sistema de Reservas Poli &mdash; Correo automático, no responder.
    </div>
  </div>
</body>
</html>
HTML;
}

// ============================================================
// FUNCIÓN: build_block_cancellation_email()
// Construye el HTML del correo de CANCELACIÓN por bloqueo
// ============================================================
/**
 * @param array $reserva  Fila de BD con: nombre, espacio, fecha, hora_inicio, hora_fin
 * @param string $motivo Bloqueo administrado
 * @return string HTML del correo
 */
function build_block_cancellation_email(array $reserva, string $motivo = ''): string
{
    $nombre     = htmlspecialchars($reserva['nombre']      ?? 'Usuario');
    $espacio    = htmlspecialchars($reserva['espacio']     ?? '');
    $fecha      = htmlspecialchars($reserva['fecha']       ?? '');
    $horaInicio = htmlspecialchars(substr($reserva['hora_inicio'] ?? '', 0, 5));
    $horaFin    = htmlspecialchars(substr($reserva['hora_fin']    ?? '', 0, 5));

    $fechaLegible = $fecha;
    if ($fecha) {
        try {
            $dt = new DateTime($fecha);
            $fechaLegible = $dt->format('d/m/Y');
        } catch (Exception $e) { /* usar fecha cruda */ }
    }

    $motivoHtml = '';
    if ($motivo !== '') {
        $motivoHtml = '<p style="margin-top:14px; color:#555; font-size:14px;"><strong>Motivo del bloqueo:</strong> ' . htmlspecialchars($motivo) . '</p>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7ff; margin: 0; padding: 20px; }
    .card { background: #fff; border-radius: 10px; max-width: 520px; margin: auto;
            padding: 32px; box-shadow: 0 4px 18px rgba(0,0,0,0.10); }
    .header { background: #1a237e; color: #fff; border-radius: 8px 8px 0 0;
              padding: 20px 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 22px; }
    .badge { display: inline-block; background: #dc3545; color: #fff;
             border-radius: 20px; padding: 6px 18px; font-size: 15px;
             font-weight: bold; margin: 16px 0; }
    .detail { background: #f0f4ff; border-radius: 8px; padding: 16px 20px; margin: 16px 0; }
    .detail p { margin: 8px 0; font-size: 15px; color: #333; }
    .detail strong { color: #1a237e; }
    .footer { text-align: center; color: #888; font-size: 12px; margin-top: 24px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>🏛️ Sistema de Reservas Poli</h1>
    </div>
    <div style="padding: 24px;">
      <p>Hola, <strong>{$nombre}</strong></p>
      <p>Lamentamos informarte que tu reserva ha sido:</p>
      <div style="text-align:center;">
        <span class="badge">❌ CANCELADA</span>
      </div>
      <div class="detail">
        <p>📍 <strong>Espacio:</strong> Auditorio {$espacio}</p>
        <p>📅 <strong>Fecha:</strong> {$fechaLegible}</p>
        <p>🕐 <strong>Hora:</strong> {$horaInicio} – {$horaFin}</p>
      </div>
      {$motivoHtml}
      <p style="color:#555; font-size:14px; margin-top:16px;">
        Esta cancelación se realizó porque ese día fue bloqueado por la administración.
        Si necesitas más información, por favor contacta con el área de coordinación.
      </p>
    </div>
    <div class="footer">
      Sistema de Reservas Poli — Correo automático, no responder.
    </div>
  </div>
</body>
</html>
HTML;
}

// ============================================================
// FUNCIÓN: build_rejection_email()
// Construye el HTML del correo de RECHAZO
// ============================================================
/**
 * @param array $reserva  Fila de BD con: nombre, espacio, fecha, hora_inicio, hora_fin
 * @return string HTML del correo
 */
function build_rejection_email(array $reserva): string
{
    $nombre     = htmlspecialchars($reserva['nombre']      ?? 'Usuario');
    $espacio    = htmlspecialchars($reserva['espacio']     ?? '');
    $fecha      = htmlspecialchars($reserva['fecha']       ?? '');
    $horaInicio = htmlspecialchars(substr($reserva['hora_inicio'] ?? '', 0, 5));
    $horaFin    = htmlspecialchars(substr($reserva['hora_fin']    ?? '', 0, 5));

    $fechaLegible = $fecha;
    if ($fecha) {
        try {
            $dt = new DateTime($fecha);
            $fechaLegible = $dt->format('d/m/Y');
        } catch (Exception $e) { /* usar fecha cruda */ }
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; background: #f4f7ff; margin: 0; padding: 20px; }
    .card { background: #fff; border-radius: 10px; max-width: 520px; margin: auto;
            padding: 32px; box-shadow: 0 4px 18px rgba(0,0,0,0.10); }
    .header { background: #1a237e; color: #fff; border-radius: 8px 8px 0 0;
              padding: 20px 24px; text-align: center; }
    .header h1 { margin: 0; font-size: 22px; }
    .badge { display: inline-block; background: #dc3545; color: #fff;
             border-radius: 20px; padding: 6px 18px; font-size: 15px;
             font-weight: bold; margin: 16px 0; }
    .detail { background: #f0f4ff; border-radius: 8px; padding: 16px 20px; margin: 16px 0; }
    .detail p { margin: 8px 0; font-size: 15px; color: #333; }
    .detail strong { color: #1a237e; }
    .footer { text-align: center; color: #888; font-size: 12px; margin-top: 24px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>🏛️ Sistema de Reservas Poli</h1>
    </div>
    <div style="padding: 24px;">
      <p>Hola, <strong>{$nombre}</strong></p>
      <p>Lamentamos informarte que tu solicitud de reserva ha sido:</p>
      <div style="text-align:center;">
        <span class="badge">❌ RECHAZADA</span>
      </div>
      <div class="detail">
        <p>📍 <strong>Espacio:</strong> Auditorio {$espacio}</p>
        <p>📅 <strong>Fecha:</strong> {$fechaLegible}</p>
        <p>🕐 <strong>Hora:</strong> {$horaInicio} – {$horaFin}</p>
      </div>
      <p style="color:#555; font-size:14px;">
        Puedes intentar reservar en otro horario o comunicarte con la administración
        para más información.
      </p>
    </div>
    <div class="footer">
      Sistema de Reservas Poli &mdash; Correo automático, no responder.
    </div>
  </div>
</body>
</html>
HTML;
}
