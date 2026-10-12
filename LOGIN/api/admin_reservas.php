<?php
require_once '../session.php';
require_auth_api();

$me = $_SESSION['user'] ?? [];
$rol = $me['rol'] ?? '';
if (!in_array($rol, ['administrativo', 'practicante'], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}
?>
<div class="admin-panel">
<h2>Gestión de Reservas Poli (Administrador)</h2>
    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr><th>Usuario</th><th>Espacio</th><th>Fecha</th><th>Horario</th><th>Estado</th><th>Acciones</th></tr>
            </thead>
            <tbody id="admin-list-body"></tbody>
        </table>
    </div>
</div>