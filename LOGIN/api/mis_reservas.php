<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';
require_once '../session.php';
require_auth_api();

/**
 * Forzar que SIEMPRE se responda JSON, aunque haya warnings/notices.
 * Esto evita que el frontend muestre "Respuesta no JSON".
 */

// Amortiguar cualquier warning/notice hacia una excepción para capturar en catch
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Convertir fatales en excepción capturable
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener tus reservas',
        'detail' => (is_local_request() ? $e->getMessage() : null),
    ]);
    exit;
});

// Limpiar output accidental antes de imprimir JSON
while (ob_get_level() > 0) { ob_end_clean(); }
ob_start();

try {
    $userId = $_SESSION['user']['id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado'
        ]);
        exit;
    }

    // Obtenemos todas las reservas del usuario, sin importar el estado.
    // Importante: desempatar para que, si existen varias reservas para el mismo slot,
    // la más reciente sea la que predomine en listados/UI.
    $stmt = $pdo->prepare("
        SELECT *
        FROM reservas
        WHERE usuario_id = ?
        ORDER BY fecha DESC, hora_inicio ASC, created_at DESC, id DESC
    ");
    $stmt->execute([$userId]);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($reservas as &$reserva) {
        // Algunas bases/esquemas pueden no traer la PK como "id" tras SELECT *.
        // Usamos fallback para evitar que el endpoint caiga y deje de renderizar "Mis reservas".
        $reservaPk =
            isset($reserva['id']) ? $reserva['id'] :
            (isset($reserva['reserva_id']) ? $reserva['reserva_id'] :
            (isset($reserva['ID']) ? $reserva['ID'] : null));

        if (!$reservaPk) {
            $reserva['recursos'] = [];
            continue;
        }

        $stmtRec = $pdo->prepare(
            "SELECT rr.recurso_id AS id, rc.nombre, rc.descripcion, rr.cantidad
             FROM reserva_recursos rr
             JOIN recursos rc ON rc.id = rr.recurso_id
             WHERE rr.reserva_id = ?"
        );
        $stmtRec->execute([(int)$reservaPk]);
        $reserva['recursos'] = $stmtRec->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    unset($reserva);

    echo json_encode([
        'success' => true,
        'reservas' => $reservas
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener tus reservas',
        'detail' => (is_local_request() ? $e->getMessage() : null)
    ]);
}

ob_end_flush();
restore_error_handler();
