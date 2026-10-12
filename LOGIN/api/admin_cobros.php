<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';
require_auth_api();

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error en cobros.',
        'detail'  => (is_local_request() ? $e->getMessage() : null),
    ]);
    exit;
});

$me = $_SESSION['user'] ?? null;
$rolesPermitidos = ['administrativo', 'practicante'];
if (!$me || !in_array($me['rol'] ?? '', $rolesPermitidos, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$espaciosValidos = ['B1', 'B2', 'B3'];

function validatePrecioEstricto($val, string $esp): int
{
    // Acepta SOLO dígitos enteros (sin signos, sin decimales, sin letras)
    $s = is_string($val) ? trim($val) : (is_int($val) || is_float($val) ? (string)$val : '');
    if ($s === '') {
        throw new InvalidArgumentException("Falta precio para {$esp}.");
    }
    if (!preg_match('/^\d+$/', $s)) {
        // rechaza decimales, letras y cualquier otro formato
        throw new InvalidArgumentException("Precio inválido para {$esp}. Usa un número entero sin decimales (0 a 100000).");
    }

    $n = (int)$s;
    if ($n < 0) {
        throw new InvalidArgumentException("Precio no puede ser negativo ({$esp}).");
    }
    if ($n > 100000) {
        throw new InvalidArgumentException("Precio máximo permitido es 100000. ({$esp}).");
    }
    return $n;
}

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("SELECT espacio, precio FROM cobros_tarifas WHERE espacio IN ('B1','B2','B3')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Completar si faltan
        $map = ['B1' => 0.0, 'B2' => 0.0, 'B3' => 0.0];
        foreach ($rows as $r) {
            $esp = $r['espacio'] ?? '';
            if (isset($map[$esp])) $map[$esp] = (float)$r['precio'];
        }

        echo json_encode([
            'success' => true,
            'tarifas' => [
                'B1' => $map['B1'],
                'B2' => $map['B2'],
                'B3' => $map['B3'],
            ]
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al cargar tarifas.', 'detail' => (is_local_request() ? $e->getMessage() : null)]);
    }
    exit;
}

if ($method === 'PUT') {
    try {
        $precioB1 = $body['B1'] ?? null;
        $precioB2 = $body['B2'] ?? null;
        $precioB3 = $body['B3'] ?? null;

        $preciosRaw = ['B1' => $precioB1, 'B2' => $precioB2, 'B3' => $precioB3];

        $precios = [];
        foreach ($preciosRaw as $esp => $val) {
            $precios[$esp] = validatePrecioEstricto($val, $esp); // int 0..100000
        }

        $usuarioId = (int)($me['id'] ?? 0);
        $usuarioNombre = $me['nombre'] ?? null;

        $pdo->beginTransaction();

        // Guardar historial por cada espacio (precio anterior vs nuevo)
        $stmtSel = $pdo->prepare("SELECT espacio, precio FROM cobros_tarifas WHERE espacio = ? LIMIT 1");
        $stmtUpd = $pdo->prepare("UPDATE cobros_tarifas SET precio = ? WHERE espacio = ?");
        $stmtHist = $pdo->prepare("
            INSERT INTO cobros_historial (espacio, precio_anterior, precio_nuevo, usuario_id, usuario_nombre)
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach (['B1','B2','B3'] as $esp) {
            $nuevo = $precios[$esp];

            $stmtSel->execute([$esp]);
            $row = $stmtSel->fetch(PDO::FETCH_ASSOC);
            $anterior = $row ? (float)$row['precio'] : 0.0;

            // Update
            $stmtUpd->execute([$nuevo, $esp]);

            // Insert historial solo si hubo cambio (evita ruido)
            $anteriorInt = (int)round((float)$anterior);
            if ($anteriorInt !== (int)$nuevo) {
                $stmtHist->execute([
                    $esp,
                    $anteriorInt,
                    (int)$nuevo,
                    $usuarioId > 0 ? $usuarioId : null,
                    $usuarioNombre
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Tarifas actualizadas correctamente.']);
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar tarifas.', 'detail' => (is_local_request() ? $e->getMessage() : null)]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
