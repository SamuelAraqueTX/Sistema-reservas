<?php
header('Content-Type: application/json');
require_once '../session.php';
require_once '../config.php';
require_auth_api();

$user   = $_SESSION['user'];
$userId = (int)$user['id'];
$esAdmin = in_array($user['rol'], ['administrativo', 'practicante'], true);

try {
    // Stats del usuario actual
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(estado = 'Pendiente')  AS pendientes,
            SUM(estado = 'Aprobada')   AS aprobadas,
            SUM(estado = 'Rechazada')  AS rechazadas,
            SUM(estado = 'Cancelada')  AS canceladas
        FROM reservas WHERE usuario_id = ?
    ");
    $stmt->execute([$userId]);
    $mis = $stmt->fetch(PDO::FETCH_ASSOC);

    // Próxima reserva aprobada del usuario
    $stmtProx = $pdo->prepare("
        SELECT espacio, fecha, hora_inicio, hora_fin
        FROM reservas
        WHERE usuario_id = ? AND estado = 'Aprobada' AND fecha >= CURDATE()
        ORDER BY fecha ASC, hora_inicio ASC
        LIMIT 1
    ");
    $stmtProx->execute([$userId]);
    $proxima = $stmtProx->fetch(PDO::FETCH_ASSOC) ?: null;

    $result = [
        'success'  => true,
        'mis_stats' => [
            'total'      => (int)$mis['total'],
            'pendientes' => (int)$mis['pendientes'],
            'aprobadas'  => (int)$mis['aprobadas'],
            'rechazadas' => (int)$mis['rechazadas'],
            'canceladas' => (int)$mis['canceladas'],
        ],
        'proxima' => $proxima,
    ];

    // Stats del sistema (solo admin)
    if ($esAdmin) {
        $sys = $pdo->query("
            SELECT
                COUNT(*) AS total,
                SUM(estado = 'Pendiente') AS pendientes,
                SUM(estado = 'Aprobada')  AS aprobadas,
                SUM(fecha = CURDATE())    AS hoy
            FROM reservas
        ")->fetch(PDO::FETCH_ASSOC);

        $porEspacio = $pdo->query("
            SELECT espacio, COUNT(*) AS total
            FROM reservas WHERE estado = 'Aprobada'
            GROUP BY espacio
        ")->fetchAll(PDO::FETCH_KEY_PAIR);

        $result['sistema'] = [
            'total'      => (int)$sys['total'],
            'pendientes' => (int)$sys['pendientes'],
            'aprobadas'  => (int)$sys['aprobadas'],
            'hoy'        => (int)$sys['hoy'],
            'por_espacio'=> $porEspacio,
        ];
    }

    echo json_encode($result);

} catch (Throwable $e) {
    error_log('dashboard_stats: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar estadísticas.']);
}
