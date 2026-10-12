<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
require_once '../config/reservas_logic.php';
require_once '../session.php';
require_once './mail_helper.php';
require_auth_api();

function build_receipt_url(int $reservaId): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $appRoot = dirname($scriptDir);
    if ($appRoot === '/' || $appRoot === '\\') {
        $appRoot = '';
    }
    return sprintf('%s://%s%s/recibo_reserva.php?id=%s', $scheme, $host, $appRoot, urlencode((string)$reservaId));
}

function ensureTipoOcupacionColumn(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'tipo_ocupacion'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec("ALTER TABLE reservas ADD COLUMN tipo_ocupacion ENUM('Clases particulares','Evento','Ocupación de logística','Ocupación de infraestructura','Ocupación de medios audiovisuales') DEFAULT 'Evento' AFTER estado");
    }
}

function ensureCobrosAndRecibosColumns(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'precio_reserva'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec("ALTER TABLE reservas ADD COLUMN precio_reserva DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER estado");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'recibo_numero'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec("ALTER TABLE reservas ADD COLUMN recibo_numero VARCHAR(64) DEFAULT NULL AFTER tipo_ocupacion");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'recibo_generado_en'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec("ALTER TABLE reservas ADD COLUMN recibo_generado_en DATETIME DEFAULT NULL AFTER recibo_numero");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'recibo_estado_pago'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec("ALTER TABLE reservas ADD COLUMN recibo_estado_pago ENUM('Pendiente','En verificación','Pagado','No pagado') DEFAULT 'Pendiente' AFTER recibo_generado_en");
    }
}

// Evitar que warnings/notices rompan el JSON
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al registrar la reserva.',
        'detail'  => (is_local_request() ? $e->getMessage() : null),
    ]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data       = json_decode(file_get_contents('php://input'), true) ?? [];
$fecha      = trim($data['fecha']       ?? '');
$horaInicio = trim($data['hora_inicio'] ?? '');
$horaFin    = trim($data['hora_fin']    ?? '');
$espacio    = strtoupper(trim($data['espacio'] ?? ''));
$requisitos = mb_substr(trim($data['requisitos_adicionales'] ?? ''), 0, 500);
$recursos   = is_array($data['recursos'] ?? null) ? $data['recursos'] : [];
$precioReserva = $data['precio_reserva'] ?? 0;
$tipoOcupacion = trim($data['tipo_ocupacion'] ?? 'Evento');
$userId = intval($_SESSION['user']['id'] ?? 0);
// Regla del jefe: SOLO se cobra/tarifa a "externo".
// Para otros roles, forzamos precio_reserva = 0 (ignorando payload manipulado).
$userRol = trim((string)($_SESSION['user']['rol'] ?? $_SESSION['user']['role'] ?? ''));
if ($userRol !== 'externo') {
    $precioReserva = 0;
} else {
    // Externos no eligen tipo de ocupación: siempre Evento.
    $tipoOcupacion = 'Evento';
}

$tiposOcupacionValidos = ['Clases particulares', 'Evento', 'Ocupación de logística', 'Ocupación de infraestructura', 'Ocupación de medios audiovisuales'];
if ($userRol === 'docente') {
    $tiposOcupacionValidos = ['Clases particulares', 'Evento'];
}
$espaciosValidos = ['B1', 'B2', 'B3'];

// Validación estricta: SOLO ENTEROS dígitos, sin decimales, sin letras
$precioStr = is_string($precioReserva) ? trim($precioReserva) : (is_int($precioReserva) || is_float($precioReserva) ? (string)$precioReserva : '');
if ($precioStr === '' || !preg_match('/^\d+$/', $precioStr)) {
    echo json_encode(['success' => false, 'message' => 'precio_reserva inválido. Usa un número entero (0 a 100000).']); exit;
}
$precioInt = (int)$precioStr;
if ($precioInt < 0 || $precioInt > 100000) {
    echo json_encode(['success' => false, 'message' => 'precio_reserva fuera de rango (0 a 100000).']); exit;
}

if (!$fecha || !$horaInicio || !$horaFin || !in_array($espacio, $espaciosValidos, true)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos o inválidos.']); exit;
}

if ($userRol !== 'externo' && !in_array($tipoOcupacion, $tiposOcupacionValidos, true)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de ocupación inválido.']); exit;
}


// Validar formato HH:MM
if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}$/', $horaFin)) {
    echo json_encode(['success' => false, 'message' => 'Formato de hora inválido. Use HH:MM.']); exit;
}

$fechaSolo = preg_replace('/[^0-9\-]/', '', explode(' ', $fecha)[0]);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaSolo)) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido.']); exit;
}

$fechaObj = new DateTime($fechaSolo);
$hoy      = new DateTime('today');

if ($fechaObj < $hoy) {
    echo json_encode(['success' => false, 'message' => 'No puedes reservar en una fecha pasada.']); exit;
}

$tsReserva = strtotime("$fechaSolo $horaInicio");
if ($tsReserva < (time() + 86400)) {
    echo json_encode(['success' => false, 'message' => 'Las reservas deben hacerse con al menos 24 horas de antelación.']); exit;
}

/**
 * Validar duración mínima y máxima
 * - mínima: 1 hora (60 min)
 * - máxima: 4 horas (240 min)
 */
$hInicioMin = (int)explode(':', $horaInicio)[0] * 60 + (int)explode(':', $horaInicio)[1];
$hFinMin    = (int)explode(':', $horaFin)[0] * 60 + (int)explode(':', $horaFin)[1];
$durMin     = $hFinMin - $hInicioMin;

if ($durMin < 60) {
    echo json_encode(['success' => false, 'message' => 'La duración mínima de una reserva es de 1 hora.']); exit;
}

if ($durMin > 240) {
    echo json_encode(['success' => false, 'message' => 'La duración máxima de una reserva es de 4 horas.']); exit;
}

$maxFecha = (new DateTime('today'))->modify('+3 months');
if ($fechaObj > $maxFecha) {
    echo json_encode(['success' => false, 'message' => 'Solo puedes reservar con hasta 3 meses de anticipación.']); exit;
}

if ($horaInicio >= $horaFin) {
    echo json_encode(['success' => false, 'message' => 'La hora de inicio debe ser menor a la hora de fin.']); exit;
}
if ($horaInicio < '08:00' || $horaFin > '21:00') {
    echo json_encode(['success' => false, 'message' => 'Las reservas deben estar entre las 08:00 y las 21:00.']); exit;
}

// ── Funciones de conflicto (reutilizadas dentro de la transacción) ───────────
function hasConflict(PDO $pdo, string $espacio, string $fecha, string $ini, string $fin, ?int $excludeId = null): bool {
    $sql = "SELECT COUNT(*) FROM reservas
            WHERE espacio = ? AND fecha = ? AND estado IN ('Pendiente','Aprobada')
              AND (? < hora_fin AND ? > hora_inicio)";
    $params = [$espacio, $fecha, $ini, $fin];
    if ($excludeId) { $sql .= ' AND id != ?'; $params[] = $excludeId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function verificarDisponibilidad(PDO $pdo, string $espacio, string $fecha, string $ini, string $fin): array {
    if ($espacio === 'B3') {
        if (hasConflict($pdo, 'B1', $fecha, $ini, $fin)) {
            return ['disponible' => false, 'mensaje' => 'No se puede reservar B3: B1 ya está ocupado en ese horario.'];
        }
        if (hasConflict($pdo, 'B2', $fecha, $ini, $fin)) {
            return ['disponible' => false, 'mensaje' => 'No se puede reservar B3: B2 ya está ocupado en ese horario.'];
        }
        if (hasConflict($pdo, 'B3', $fecha, $ini, $fin)) {
            return ['disponible' => false, 'mensaje' => 'B3 ya está ocupado en ese horario.'];
        }
    } else {
        if (hasConflict($pdo, $espacio, $fecha, $ini, $fin)) {
            return ['disponible' => false, 'mensaje' => "El espacio {$espacio} ya está ocupado en ese horario."];
        }
    }
    return ['disponible' => true, 'mensaje' => 'Disponible'];
}

try {
    // ── Validar día bloqueado ────────────────────────────────────────────────
    $stmtBlq = $pdo->prepare("SELECT motivo FROM dias_bloqueados WHERE fecha = ? LIMIT 1");
    $stmtBlq->execute([$fechaSolo]);
    $diaBloqueado = $stmtBlq->fetch(PDO::FETCH_ASSOC);
    if ($diaBloqueado !== false) {
        $motivo = $diaBloqueado['motivo'] ? ' Motivo: ' . $diaBloqueado['motivo'] : '';
        echo json_encode(['success' => false, 'message' => 'Este día está bloqueado por la administración.' . $motivo]); exit;
    }

    // Verificar bloqueo por reglas recurrentes indefinidas (por día de la semana)
    $dow = (int)$fechaObj->format('w'); // 0 domingo .. 6 sábado
    $stmtSem = $pdo->prepare("SELECT motivo FROM dias_bloqueados_recurrent WHERE dia = ? LIMIT 1");
    $stmtSem->execute([$dow]);
    $bloqSem = $stmtSem->fetch(PDO::FETCH_ASSOC);
    if ($bloqSem !== false) {
        $motivo = $bloqSem['motivo'] ? ' Motivo: ' . $bloqSem['motivo'] : '';
        echo json_encode(['success' => false, 'message' => 'Este día está bloqueado por la administración.' . $motivo]); exit;
    }

    // ── Validar que recursos existen en la DB ────────────────────────────────
    $recursosValidos = [];
    foreach ($recursos as $rec) {
        $recId  = intval($rec['id']       ?? 0);
        $cant   = max(1, intval($rec['cantidad'] ?? 1));
        if ($recId <= 0) continue;

        $chk = $pdo->prepare("SELECT id, cantidad FROM recursos WHERE id = ?");
        $chk->execute([$recId]);
        $recurso = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$recurso) {
            echo json_encode(['success' => false, 'message' => "Recurso con ID {$recId} no existe."]); exit;
        }
        if ($cant > (int)$recurso['cantidad']) {
            echo json_encode(['success' => false, 'message' => "No hay suficientes unidades del recurso (máx. {$recurso['cantidad']})."]); exit;
        }
        $recursosValidos[] = ['id' => $recId, 'cantidad' => $cant];
    }

    ensureTipoOcupacionColumn($pdo);
    ensureCobrosAndRecibosColumns($pdo);

    // ── TRANSACCIÓN con bloqueo para evitar race condition ───────────────────
    $pdo->beginTransaction();

    // Bloquear filas relevantes para la fecha (FOR UPDATE impide inserciones concurrentes)
    $pdo->prepare("SELECT id FROM reservas WHERE fecha = ? AND estado IN ('Pendiente','Aprobada') FOR UPDATE")
        ->execute([$fechaSolo]);

    // ── Verificar que el usuario no tiene ya una reserva solapada ese día ────
    $stmtProp = $pdo->prepare("
        SELECT espacio FROM reservas
        WHERE usuario_id = ? AND fecha = ? AND estado IN ('Pendiente','Aprobada')
          AND (? < hora_fin AND ? > hora_inicio)
    ");
    $stmtProp->execute([$userId, $fechaSolo, $horaInicio, $horaFin]);
    if ($stmtProp->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Ya tienes una reserva en ese horario.']); exit;
    }

    // ── Verificar disponibilidad del espacio ─────────────────────────────────
    $check = verificarConflictoReserva($pdo, $espacio, $fechaSolo, $horaInicio, $horaFin);
    if (!$check['disponible']) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $check['mensaje']]); exit;
    }

    // ── Insertar reserva ─────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO reservas (usuario_id, fecha, hora_inicio, hora_fin, espacio, requisitos, estado, precio_reserva, tipo_ocupacion)
        VALUES (?, ?, ?, ?, ?, ?, 'Pendiente', ?, ?)
    ");
    $stmt->execute([$userId, $fechaSolo, $horaInicio, $horaFin, $espacio, $requisitos ?: null, $precioReserva, $tipoOcupacion]);
    $reservaId = (int)$pdo->lastInsertId();

    $reciboNumero = null;
    if ($precioInt > 0) {
        $reciboNumero = sprintf('REC-%s-%05d', (new DateTime())->format('Ymd'), $reservaId);
        $stmtUpdateRecibo = $pdo->prepare("UPDATE reservas SET recibo_numero = ?, recibo_generado_en = NOW(), recibo_estado_pago = 'Pendiente' WHERE id = ?");
        $stmtUpdateRecibo->execute([$reciboNumero, $reservaId]);
    }

    // ── Guardar recursos seleccionados ───────────────────────────────────────
    if (!empty($recursosValidos)) {
        $stmtRec = $pdo->prepare("INSERT IGNORE INTO reserva_recursos (reserva_id, recurso_id, cantidad) VALUES (?, ?, ?)");
        foreach ($recursosValidos as $rec) {
            $stmtRec->execute([$reservaId, $rec['id'], $rec['cantidad']]);
        }
    }

    $pdo->commit();

    $reserva = [
        'nombre'     => trim((string)($_SESSION['user']['nombre'] ?? 'Usuario')),
        'espacio'    => $espacio,
        'fecha'      => $fechaSolo,
        'hora_inicio'=> $horaInicio,
        'hora_fin'   => $horaFin,
    ];

    $mailSubject = 'Reserva PENDIENTE - Auditorio ' . $espacio;

    // Garantizar que el link del recibo se incluya si el recibo_numero existe en BD,
    // independientemente de cómo haya quedado el precioInt/rol en el request.
    $stmtRecibo = $pdo->prepare("SELECT recibo_numero FROM reservas WHERE id = ? LIMIT 1");
    $stmtRecibo->execute([$reservaId]);
    $reciboNumeroBD = $stmtRecibo->fetchColumn();
    $reciboUrl = $reciboNumeroBD ? build_receipt_url((int)$reservaId) : null;

    // build_pending_email ya cambia el texto según $isPending y si existe $reciboUrl.
    $mailBody = build_pending_email($reserva, $reciboUrl);

    $emailDestino = trim((string)($_SESSION['user']['email'] ?? ''));
    $emailValido  = $emailDestino !== '' && filter_var($emailDestino, FILTER_VALIDATE_EMAIL);

    if (!$emailValido && $userId > 0) {
        // Si la sesión no trae un email válido, tomamos el email real del usuario desde BD
        try {
            $stmtEmail = $pdo->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
            $stmtEmail->execute([$userId]);
            $emailDestino = trim((string)($stmtEmail->fetchColumn() ?: ''));
            $emailValido  = $emailDestino !== '' && filter_var($emailDestino, FILTER_VALIDATE_EMAIL);
        } catch (Throwable $e) {
            // No romper el flujo de reserva si falla el email
            error_log('nueva_reserva: fallo obteniendo email desde BD -> ' . $e->getMessage());
            $emailValido = false;
        }
    }

    if (!$emailValido) {
        $mailErrorMsg = "nueva_reserva: no se encontró email válido en sesión ni en BD (userId={$userId})";
        error_log($mailErrorMsg);
    } else {
        $mailSent = send_system_email($emailDestino, $mailSubject, $mailBody, $mailError);

        // Logs para auditar “link perdido”
        $logExtra = ' | reservaId=' . $reservaId . ' | recibo_numero=' . ($reciboNumero ?: 'NULL') . ' | reciboUrl=' . ($reciboUrl ?: 'NULL') . ' | email=' . $emailDestino;
        if ($mailSent) {
            error_log('nueva_reserva: email enviado correctamente (PENDIENTE).' . $logExtra);
        } else {
            error_log('nueva_reserva: fallo envío email reserva pendiente -> ' . ($mailError ?? 'sin mensaje de error') . $logExtra);
        }
    }

    $response = [
        'success' => true,
        'message' => "Reserva registrada y pendiente de aprobación.\n\nRecuerda:\n• Si requieres apoyo de medios audiovisuales, carga la solicitud en la plataforma.\n• Las solicitudes que no estén registradas por este medio no podrán ser atendidas." . build_support_links_text(),
        'reserva_id' => $reservaId,
    ];
    if ($reciboNumero) {
        $response['recibo_numero'] = $reciboNumero;
    }

    echo json_encode($response);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('nueva_reserva EXCEPTION: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error al registrar la reserva.',
        'detail' => (is_local_request() ? $e->getMessage() : null)
    ]);
}
