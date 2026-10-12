<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
require_once '../session.php';
require_once '../config.php';
require_once '../config/reservas_logic.php';
require_once 'mail_helper.php';
require_auth_api();

$me = $_SESSION['user'];
$rolesPermitidos = ['administrativo', 'practicante'];
if (!in_array($me['rol'], $rolesPermitidos, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

function isFechaBloqueada(PDO $pdo, string $fecha): bool
{
    // Bloqueo exacto por fecha
    $stmtExact = $pdo->prepare("SELECT 1 FROM dias_bloqueados WHERE fecha = ? LIMIT 1");
    $stmtExact->execute([$fecha]);
    if ($stmtExact->fetch()) return true;

    // Bloqueo recurrente por día de la semana
    $weekday = (int)date('w', strtotime($fecha)); // 0 domingo ... 6 sábado
    $stmtRec = $pdo->prepare("SELECT 1 FROM dias_bloqueados_recurrent WHERE dia = ? LIMIT 1");
    $stmtRec->execute([$weekday]);
    if ($stmtRec->fetch()) return true;

    return false;
}

function ensureTipoOcupacionColumn(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'tipo_ocupacion'");
    if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC) === false) {
        $pdo->exec("ALTER TABLE reservas ADD COLUMN tipo_ocupacion ENUM('Clases particulares','Evento','Ocupación de logística','Ocupación de infraestructura','Ocupación de medios audiovisuales') DEFAULT 'Evento' AFTER estado");
    }

}
// ─── GET: listar todas las reservas ──────────────────────────────────────────
if ($method === 'GET') {
    $estado  = $_GET['estado']  ?? '';
    $espacio = $_GET['espacio'] ?? '';

    $where  = ['1=1'];
    $params = [];

    $estados_validos = ['Pendiente', 'Aprobada', 'Rechazada', 'Cancelada'];
    if ($estado && in_array($estado, $estados_validos)) {
        $where[]  = 'r.estado = ?';
        $params[] = $estado;
    }

    $espacios_validos = ['B1', 'B2', 'B3'];
    if ($espacio && in_array($espacio, $espacios_validos)) {
        $where[]  = 'r.espacio = ?';
        $params[] = $espacio;
    }

    $stmtCol = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'tipo_ocupacion'");
    $hasTipo = $stmtCol && $stmtCol->fetch(PDO::FETCH_ASSOC) !== false;

    $hasRecibos = false;
    $stmtRecCol = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'recibo_numero'");
    if ($stmtRecCol && $stmtRecCol->fetch(PDO::FETCH_ASSOC) !== false) {
        $hasRecibos = true;
    }

    $sql = "SELECT r.id, r.fecha, r.hora_inicio, r.hora_fin, r.espacio,
                   r.requisitos, r.estado, r.created_at, r.precio_reserva,
                   " . ($hasTipo ? 'r.tipo_ocupacion,' : "'Evento' AS tipo_ocupacion,") . "
                   " . ($hasRecibos ? 'r.recibo_numero, r.recibo_generado_en, r.recibo_estado_pago,' : "NULL AS recibo_numero, NULL AS recibo_generado_en, NULL AS recibo_estado_pago,") . "
                   u.id AS usuario_id, u.nombre AS usuario, u.email
            FROM reservas r
            JOIN usuarios u ON u.id = r.usuario_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.fecha DESC, r.hora_inicio ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si la columna tipo_ocupacion existe, cargarla también para el frontend.
    $hasTipo = false;
    $stmtCol = $pdo->query("SHOW COLUMNS FROM reservas LIKE 'tipo_ocupacion'");
    if ($stmtCol && $stmtCol->fetch(PDO::FETCH_ASSOC) !== false) {
        $hasTipo = true;
    }

    // Cargar recursos asociados a cada reserva
    $idsReservas = array_map(function ($r) { return (int)$r['id']; }, $reservas);
    if (empty($idsReservas)) {
        foreach ($reservas as &$r) { $r['recursos'] = []; }
        unset($r);
        echo json_encode(['success' => true, 'reservas' => $reservas]);
        exit;
    }

    $in = implode(',', array_fill(0, count($idsReservas), '?'));
    $sqlRec = "
        SELECT rr.reserva_id, rr.cantidad, rec.id AS recurso_id, rec.nombre
        FROM reserva_recursos rr
        JOIN recursos rec ON rec.id = rr.recurso_id
        WHERE rr.reserva_id IN ($in)
        ORDER BY rr.reserva_id ASC, rec.nombre ASC
    ";
    $stmtRec = $pdo->prepare($sqlRec);
    $stmtRec->execute($idsReservas);

    $recursosPorReserva = [];
    while ($row = $stmtRec->fetch(PDO::FETCH_ASSOC)) {
        $rid = (int)$row['reserva_id'];
        if (!isset($recursosPorReserva[$rid])) $recursosPorReserva[$rid] = [];
        $recursosPorReserva[$rid][] = [
            'id' => (int)$row['recurso_id'],
            'nombre' => $row['nombre'],
            'cantidad' => (int)$row['cantidad'],
        ];
    }

    foreach ($reservas as &$r) {
        if (!isset($r['tipo_ocupacion']) || $r['tipo_ocupacion'] === null) {
            $r['tipo_ocupacion'] = 'Evento';
        }
        $rid = (int)$r['id'];
        $r['recursos'] = $recursosPorReserva[$rid] ?? [];
    }
    unset($r);

    echo json_encode(['success' => true, 'reservas' => $reservas]);

// ─── PUT: editar reserva ──────────────────────────────────────────────────────
} elseif ($method === 'PUT') {
    $id          = intval($body['id']          ?? 0);
    $fecha       = trim($body['fecha']         ?? '');
    $hora_inicio = trim($body['hora_inicio']   ?? '');
    $hora_fin    = trim($body['hora_fin']      ?? '');
    $espacio     = trim($body['espacio']       ?? '');
    $estado      = trim($body['estado']        ?? '');
    $requisitos  = trim($body['requisitos']    ?? '');
    $tipoOcupacion = trim($body['tipo_ocupacion'] ?? '');

    $estados_validos = ['Pendiente', 'Aprobada', 'Rechazada', 'Cancelada'];
    $espacios_validos = ['B1', 'B2', 'B3'];

    $tiposOcupacionValidos = ['Clases particulares', 'Evento', 'Ocupación de logística', 'Ocupación de infraestructura', 'Ocupación de medios audiovisuales'];

    if (!$id || !$fecha || !$hora_inicio || !$hora_fin
        || !in_array($espacio, $espacios_validos)
        || !in_array($estado, $estados_validos)) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos o incompletos.']); exit;
    }

    ensureTipoOcupacionColumn($pdo);

    if (!in_array($tipoOcupacion, $tiposOcupacionValidos, true)) {
        echo json_encode(['success' => false, 'message' => 'Tipo de ocupación inválido.']); exit;
    }

    if ($hora_inicio >= $hora_fin) {
        echo json_encode(['success' => false, 'message' => 'La hora de inicio debe ser menor a la hora de fin.']); exit;
    }

    // Verificar que la reserva existe
    $check = $pdo->prepare("SELECT id FROM reservas WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada.']); exit;
    }

    // Verificar conflicto de horario con otras reservas usando la lógica compartida
    $resultado = verificarConflictoReserva($pdo, $espacio, $fecha, $hora_inicio, $hora_fin, $id);
    if (!$resultado['disponible']) {
        echo json_encode(['success' => false, 'message' => $resultado['mensaje']]); exit;
    }

    // Si intenta marcar como Aprobada, validar que la fecha no esté bloqueada
    if ($estado === 'Aprobada' && isFechaBloqueada($pdo, $fecha)) {
        echo json_encode(['success' => false, 'message' => 'No se puede aprobar: la fecha está bloqueada.']); exit;
    }

    $stmt = $pdo->prepare("
        UPDATE reservas
        SET fecha = ?, hora_inicio = ?, hora_fin = ?, espacio = ?, estado = ?, requisitos = ?, tipo_ocupacion = ?
        WHERE id = ?
    ");
    $stmt->execute([$fecha, $hora_inicio, $hora_fin, $espacio, $estado, $requisitos ?: null, $tipoOcupacion, $id]);
    echo json_encode(['success' => true, 'message' => 'Reserva actualizada correctamente.']);

// ─── DELETE: eliminar reserva ─────────────────────────────────────────────────
} elseif ($method === 'DELETE') {
    $id = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'ID inválido.']); exit; }

    $check = $pdo->prepare("SELECT id FROM reservas WHERE id = ?");
    $check->execute([$id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada.']); exit;
    }

    $pdo->prepare("DELETE FROM reservas WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Reserva eliminada.']);

// ─── POST: aprobar o rechazar ─────────────────────────────────────────────────
} elseif ($method === 'POST') {
    $id     = intval($body['id']     ?? 0);
    $accion = trim($body['accion']   ?? '');

    if (!$id || !in_array($accion, ['aprobar', 'rechazar'])) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']); exit;
    }

    $stmt = $pdo->prepare("
        SELECT r.id, r.fecha, r.hora_inicio, r.hora_fin, r.espacio,
               u.nombre, u.email
        FROM reservas r JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.id = ? AND r.estado = 'Pendiente'
    ");
    $stmt->execute([$id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        echo json_encode(['success' => false, 'message' => 'Reserva no encontrada o ya procesada.']); exit;
    }

    // Validar bloqueo si se intenta aprobar
    if ($accion === 'aprobar' && isFechaBloqueada($pdo, $reserva['fecha'])) {
        echo json_encode(['success' => false, 'message' => 'No se puede aprobar: la fecha está bloqueada.']); exit;
    }

    $nuevoEstado = $accion === 'aprobar' ? 'Aprobada' : 'Rechazada';
    $pdo->prepare("UPDATE reservas SET estado = ? WHERE id = ?")->execute([$nuevoEstado, $id]);

    $subject = $accion === 'aprobar'
        ? 'Reserva APROBADA - Auditorio ' . $reserva['espacio']
        : 'Reserva RECHAZADA - Auditorio ' . $reserva['espacio'];
    $body_mail = $accion === 'aprobar'
        ? build_approval_email($reserva)
        : build_rejection_email($reserva);
    send_system_email($reserva['email'], $subject, $body_mail);

    $msg = $accion === 'aprobar' ? 'Reserva aprobada.' : 'Reserva rechazada.';
    echo json_encode(['success' => true, 'message' => $msg]);

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
}
