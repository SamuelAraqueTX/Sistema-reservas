<?php
header('Content-Type: application/json');
require_once '../session.php';
require_once '../config.php';
require_auth_api();

if (!in_array($_SESSION['user']['rol'], ['administrativo', 'practicante'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$adminId = (int)$_SESSION['user']['id'];
$rolesValidos = ['docente', 'externo', 'practicante'];

// ── GET: listar todos los códigos ─────────────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT c.id, c.codigo, c.descripcion, c.rol_permitido,
               c.usos_maximos, c.usos_actuales, c.activo, c.created_at,
               u.nombre AS creado_por
        FROM codigos_invitacion c
        LEFT JOIN usuarios u ON u.id = c.created_by
        ORDER BY c.created_at DESC
    ");
    $codigos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($codigos as &$c) {
        $c['activo']       = (bool)$c['activo'];
        $c['usos_maximos'] = (int)$c['usos_maximos'];
        $c['usos_actuales']= (int)$c['usos_actuales'];
        $c['agotado']      = $c['usos_actuales'] >= $c['usos_maximos'];
    }
    echo json_encode(['success' => true, 'codigos' => $codigos]);
    exit;
}

// ── POST: crear código ────────────────────────────────────────────────────────
if ($method === 'POST') {
    $descripcion  = mb_substr(trim($body['descripcion']  ?? ''), 0, 200);
    $rolPermitido = trim($body['rol_permitido'] ?? '');
    $usosMaximos  = max(1, min(1000, (int)($body['usos_maximos'] ?? 1)));
    $codigoCustom = trim($body['codigo'] ?? '');

    if ($rolPermitido !== '' && !in_array($rolPermitido, $rolesValidos, true)) {
        echo json_encode(['success' => false, 'message' => 'Rol no válido para código.']);
        exit;
    }

    // Generar o usar código personalizado
    if ($codigoCustom !== '') {
        if (!preg_match('/^[A-Za-z0-9\-_]{4,32}$/', $codigoCustom)) {
            echo json_encode(['success' => false, 'message' => 'Código inválido. Solo letras, números, guiones (4-32 caracteres).']);
            exit;
        }
        $codigo = strtoupper($codigoCustom);
    } else {
        $codigo = strtoupper(bin2hex(random_bytes(6))); // 12 chars hex
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO codigos_invitacion (codigo, descripcion, rol_permitido, usos_maximos, activo, created_by)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([
            $codigo,
            $descripcion ?: null,
            $rolPermitido ?: null,
            $usosMaximos,
            $adminId
        ]);
        echo json_encode([
            'success' => true,
            'message' => 'Código creado.',
            'codigo'  => $codigo
        ]);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['success' => false, 'message' => 'Ese código ya existe. Elige otro.']);
        } else {
            error_log('admin_codigos POST: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error al crear código.']);
        }
    }
    exit;
}

// ── PATCH: activar/desactivar código ─────────────────────────────────────────
if ($method === 'PATCH') {
    $id     = (int)($body['id']     ?? 0);
    $activo = isset($body['activo']) ? (int)(bool)$body['activo'] : null;

    if ($id <= 0 || $activo === null) {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE codigos_invitacion SET activo = ? WHERE id = ?");
    $stmt->execute([$activo, $id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Código no encontrado.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => $activo ? 'Código activado.' : 'Código desactivado.']);
    exit;
}

// ── DELETE: eliminar código ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido.']);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM codigos_invitacion WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Código no encontrado.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Código eliminado.']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido']);
