<?php
require_once 'config.php';
header('Content-Type: text/html; charset=utf-8');
require_once 'session.php';
require_auth();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'ID de reserva inválido.';
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT r.*, u.nombre AS usuario_nombre, u.email AS usuario_email, u.rol AS usuario_rol
         FROM reservas r
         JOIN usuarios u ON u.id = r.usuario_id
         WHERE r.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        http_response_code(404);
        echo 'Reserva no encontrada.';
        exit;
    }

    $user = $_SESSION['user'];
    $userId = $user['id'] ?? null;
    $userRol = trim((string)($user['rol'] ?? ''));

    if ($userRol !== 'administrativo' && $userRol !== 'practicante' && $reserva['usuario_id'] != $userId) {
        http_response_code(403);
        echo 'No tienes permiso para ver este recibo.';
        exit;
    }

    // Si no hay recibo generado, mostrar información mínima
    $tieneRecibo = !empty($reserva['recibo_numero']);
    $precio = number_format((float)($reserva['precio_reserva'] ?? 0), 0, ',', '.');
    $fechaRecibo = $reserva['recibo_generado_en'] ? (new DateTime($reserva['recibo_generado_en']))->format('Y-m-d H:i') : null;

    // Derivar estado de pago si la reserva ya venció y sigue pendiente
    $estadoPago = $reserva['recibo_estado_pago'] ?? null;
    if (!$estadoPago) {
        if ($reserva['estado'] === 'Aprobada') {
            $estadoPago = 'Pagado';
        } elseif ($reserva['estado'] === 'Rechazada') {
            $estadoPago = 'No pagado';
        } else {
            $estadoPago = 'Pendiente';
        }
    }
    if ($estadoPago === 'Pendiente' && $reserva['estado'] === 'Pendiente') {
        $fechaFin = sprintf('%s %s:00', $reserva['fecha'], substr($reserva['hora_fin'] ?? '', 0, 5));
        $dtFin = DateTime::createFromFormat('Y-m-d H:i:s', $fechaFin);
        if ($dtFin !== false && $dtFin < new DateTime()) {
            $estadoPago = 'No pagado';
        }
    }

    function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    $tipoOcupacion = $reserva['tipo_ocupacion'] ?: 'Evento';
    $fechaReserva = $reserva['fecha'] ?? '';
    $horaInicio = substr($reserva['hora_inicio'] ?? '', 0, 5);
    $horaFin = substr($reserva['hora_fin'] ?? '', 0, 5);
    $espacio = $reserva['espacio'] ?? '';
    $nombreCliente = $reserva['usuario_nombre'] ?? 'Cliente externo';
    $emailCliente = $reserva['usuario_email'] ?? '';
    $estadoReserva = $reserva['estado'] ?? 'Pendiente';
    $notas = trim((string)($reserva['requisitos'] ?? ''));
    $reciboNumero = $reserva['recibo_numero'] ?? null;
    $reciboGenerado = $fechaRecibo ?: date('Y-m-d H:i');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error al cargar el recibo: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Cobro - Reserva #<?php echo escapeHtml((string)$id); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f4f4; margin:0; padding:0; color:#222; }
        .page { max-width:900px; margin:24px auto; padding:24px; background:#fff; box-shadow:0 0 18px rgba(0,0,0,.08); }
        h1 { margin:0 0 16px; font-size:1.55rem; letter-spacing:.03em; }
        .top-row, .details-grid, .summary-grid { display:grid; gap:12px; }
        .top-row { grid-template-columns: 1fr auto; align-items:start; }
        .brand { line-height:1.4; }
        .brand strong { display:block; font-size:1.1rem; }
        .badge { display:inline-block; padding:.35em .75em; background:#0d6efd; color:#fff; border-radius:999px; font-size:.85rem; text-transform:uppercase; letter-spacing:.06em; }
        .card { border:1px solid #d7d7d7; padding:16px; border-radius:10px; background:#fbfbfb; }
        .details-grid { grid-template-columns: repeat(3, minmax(180px,1fr)); }
        .summary-grid { grid-template-columns: 2fr 1fr; }
        .summary-grid .line { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e6e6e6; }
        .summary-grid .line:last-child { border-bottom:none; font-weight:700; }
        .meta-list p, .details-grid p, .card p { margin:.35rem 0; }
        .note { margin-top:16px; padding:14px; background:#f8f9fa; border-left:4px solid #0d6efd; }
        .actions { margin:20px 0; display:flex; gap:12px; flex-wrap:wrap; }
        .actions a, .actions button { text-decoration:none; border:none; display:inline-flex; align-items:center; justify-content:center; padding:12px 18px; border-radius:8px; font-weight:600; cursor:pointer; }
        .actions button { background:#0d6efd; color:#fff; }
        .actions a { background:#e9ecef; color:#212529; }
        .print-only { display:none; }
        @media print {
            body { background:none; }
            .page { box-shadow:none; margin:0; }
            .actions, .no-print { display:none !important; }
            .print-only { display:block; margin-top:16px; font-size:.95rem; color:#444; }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="top-row">
            <div class="brand">
                <strong>RECIBO DE COBRO</strong>
                <span>COMPROBANTE DE PAGO</span>
                <p style="margin-top:10px;font-size:.95rem;color:#555;">Sistema de Reservas Poli · Politécnico Grancolombiano</p>
            </div>
            <div class="badge"><?php echo escapeHtml($estadoPago); ?></div>
        </div>

        <?php if (!$tieneRecibo): ?>
            <div class="card" style="margin-top:18px;">
                <p><strong>Este comprobante aún no tiene un recibo generado.</strong></p>
                <p>La reserva fue registrada, pero no se ha generado un comprobante formal para esta transacción.</p>
            </div>
        <?php endif; ?>

        <div class="details-grid" style="margin-top:20px;">
            <div class="card meta-list">
                <p><strong>Cliente</strong></p>
                <p><?php echo escapeHtml($nombreCliente); ?></p>
                <p><?php echo escapeHtml($emailCliente); ?></p>
                <p>Rol: <?php echo escapeHtml($userRol); ?></p>
            </div>
            <div class="card meta-list">
                <p><strong>Datos de reserva</strong></p>
                <p>Reserva ID: <?php echo escapeHtml((string)$id); ?></p>
                <p>Espacio: <?php echo escapeHtml($espacio); ?></p>
                <p>Fecha: <?php echo escapeHtml($fechaReserva); ?></p>
                <p>Hora: <?php echo escapeHtml($horaInicio); ?> - <?php echo escapeHtml($horaFin); ?></p>
            </div>
            <div class="card meta-list">
                <p><strong>Recibo</strong></p>
                <p>Número: <?php echo escapeHtml($reciboNumero ?? 'N/A'); ?></p>
                <p>Generado: <?php echo escapeHtml($reciboGenerado); ?></p>
                <p>Tipo: <?php echo escapeHtml($tipoOcupacion); ?></p>
                <p>Estado reserva: <?php echo escapeHtml($estadoReserva); ?></p>
            </div>
        </div>

        <div class="summary-grid" style="margin-top:20px;">
            <div class="card">
                <p><strong>Descripción</strong></p>
                <div class="line"><span>Uso de espacio</span><span><?php echo escapeHtml($espacio); ?></span></div>
                <div class="line"><span>Tipo de ocupación</span><span><?php echo escapeHtml($tipoOcupacion); ?></span></div>
                <div class="line"><span>Notas</span><span><?php echo escapeHtml($notas ?: 'Sin notas adicionales'); ?></span></div>
            </div>
            <div class="card">
                <div class="line"><span>Subtotal</span><span>$<?php echo escapeHtml($precio); ?></span></div>
                <div class="line"><span>I.V.A.</span><span>$0</span></div>
                <div class="line"><span>Total</span><span>$<?php echo escapeHtml($precio); ?></span></div>
            </div>
        </div>

        <div class="card note" style="margin-top:20px;">
            <p><strong>Instrucciones de pago</strong></p>
            <p>Consigne el valor a la cuenta bancaria indicada y conserve este comprobante. El número de recibo debe incluirse como concepto de la transferencia.</p>
            <p>Si la reserva es aprobada, el estado se actualizará automáticamente en el sistema.</p>
        </div>

        <div class="card" style="margin-top:16px;">
            <p><strong>Datos bancarios (ejemplo)</strong></p>
            <p>Banco: Banco del Sistema</p>
            <p>Cuenta corriente: 1234567890</p>
            <p>Nombre: Politécnico Grancolombiano</p>
            <p>Documento: 900123456-7</p>
            <p>Concepto: <?php echo escapeHtml($reciboNumero ?? 'RESERVA-' . $id); ?></p>
        </div>

        <div class="actions no-print">
            <button type="button" onclick="window.print()">Imprimir / Guardar PDF</button>
            <a href="dashboard.php">Volver al panel</a>
        </div>
        <div class="print-only">Documento generado para impresión.</div>
    </div>
</body>
</html>
