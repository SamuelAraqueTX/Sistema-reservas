<?php
header('Content-Type: application/json');
require_once '../session.php';
require_once '../config.php';
require_auth_api();

if (!in_array($_SESSION['user']['rol'], ['practicante', 'administrativo'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

try {
    // Totales y tasa de aprobación
    $totales = $pdo->query("
        SELECT
            COUNT(*) AS total,
            SUM(estado = 'Aprobada')   AS aprobadas,
            SUM(estado = 'Rechazada')  AS rechazadas,
            SUM(estado = 'Pendiente')  AS pendientes,
            SUM(estado = 'Cancelada')  AS canceladas
        FROM reservas
    ")->fetch(PDO::FETCH_ASSOC);

    $tasa = $totales['total'] > 0
        ? round(($totales['aprobadas'] / $totales['total']) * 100) . '%'
        : '0%';

    // Por espacio (aprobadas)
    $porEspacio = $pdo->query("
        SELECT espacio, COUNT(*) AS total
        FROM reservas WHERE estado = 'Aprobada'
        GROUP BY espacio ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    $espacioMasUsado = !empty($porEspacio) ? array_key_first($porEspacio) : 'N/A';

    // Por día de semana
    $porDia = $pdo->query("
        SELECT DAYOFWEEK(fecha) AS dow, COUNT(*) AS total
        FROM reservas WHERE estado IN ('Aprobada','Pendiente')
        GROUP BY dow ORDER BY dow
    ")->fetchAll(PDO::FETCH_ASSOC);

    $diasNombres = ['', 'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $diasData = [];
    foreach ($porDia as $row) {
        $diasData[$diasNombres[(int)$row['dow']]] = (int)$row['total'];
    }

    // Hora pico
    $horaPico = $pdo->query("
        SELECT HOUR(hora_inicio) AS hora, COUNT(*) AS total
        FROM reservas WHERE estado IN ('Aprobada','Pendiente')
        GROUP BY hora ORDER BY total DESC LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    $horaPicoStr = $horaPico ? str_pad($horaPico['hora'], 2, '0', STR_PAD_LEFT) . ':00' : 'N/A';

    // Tendencia últimas 8 semanas
    $tendencia = $pdo->query("
        SELECT
            YEAR(fecha) AS anio,
            WEEK(fecha, 1) AS semana,
            COUNT(*) AS total
        FROM reservas
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
        GROUP BY anio, semana
        ORDER BY anio, semana
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Heatmap: día × hora
    $heatmapRows = $pdo->query("
        SELECT DAYOFWEEK(fecha) AS dow, HOUR(hora_inicio) AS hora, COUNT(*) AS total
        FROM reservas WHERE estado IN ('Aprobada','Pendiente')
        GROUP BY dow, hora
    ")->fetchAll(PDO::FETCH_ASSOC);

    $heatmap = [];
    foreach ($heatmapRows as $row) {
        $heatmap[] = [
            'dia'   => $diasNombres[(int)$row['dow']] ?? 'N/A',
            'hora'  => str_pad($row['hora'], 2, '0', STR_PAD_LEFT) . ':00',
            'total' => (int)$row['total']
        ];
    }

    // Por rol de usuario
    $porRol = $pdo->query("
        SELECT u.rol, COUNT(r.id) AS total
        FROM reservas r
        JOIN usuarios u ON u.id = r.usuario_id
        WHERE r.estado IN ('Aprobada','Pendiente')
        GROUP BY u.rol ORDER BY total DESC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Calificación promedio
    $calStmt = $pdo->query("SELECT AVG(calificacion) AS prom FROM calificaciones");
    $calRow  = $calStmt ? $calStmt->fetch(PDO::FETCH_ASSOC) : null;
    $calProm = ($calRow && $calRow['prom'] !== null) ? round((float)$calRow['prom'], 1) : null;

    echo json_encode([
        'success'          => true,
        'total'            => (int)$totales['total'],
        'aprobadas'        => (int)$totales['aprobadas'],
        'rechazadas'       => (int)$totales['rechazadas'],
        'pendientes'       => (int)$totales['pendientes'],
        'tasa_aprobacion'  => $tasa,
        'por_espacio'      => $porEspacio,
        'espacio_mas_usado'=> $espacioMasUsado,
        'por_dia_semana'   => $diasData,
        'hora_pico'        => $horaPicoStr,
        'tendencia_semanal'=> $tendencia,
        'heatmap'          => $heatmap,
        'por_rol'          => $porRol,
        'calificacion_prom'=> $calProm,
    ]);

} catch (Throwable $e) {
    error_log('dashboard_coordinador: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al cargar estadísticas.']);
}
