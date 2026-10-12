<?php
header('Content-Type: application/json');

// Configuración local opcional (credenciales). No se versiona; ver config.local.example.php
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'usuarios';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

function is_local_request(): bool
{
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($remoteAddr, ['127.0.0.1', '::1'], true);
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

function create_pdo(string $host, string $port, string $db, string $user, string $pass, array $options)
{
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$GLOBALS['charset']}";
    return new PDO($dsn, $user, $pass, $options);
}

try {
    $pdo = create_pdo($host, $port, $db, $user, $pass, $options);
} catch (\PDOException $e) {
    // Intentar fallback a mysqli si PDO no está disponible o falla
    $isLocal = is_local_request() || (php_sapi_name() === 'cli');
    error_log('PDO connect failed: ' . $e->getMessage());

    // Intentar con host alternativo 'localhost' si venimos de 127.0.0.1
    $hostsToTry = [$host];
    if ($host === '127.0.0.1') $hostsToTry[] = 'localhost';

    $mysqli = null;
    foreach ($hostsToTry as $h) {
        $m = @new mysqli($h, $user, $pass, $db, (int)$port);
        if ($m && $m->connect_errno === 0) { $mysqli = $m; break; }
    }

    if ($mysqli) {
        // Cargar adaptador y exponer $pdo como adapter
        require_once __DIR__ . '/config/mysqli_adapter.php';
        $pdo = new MysqliPdoAdapter($mysqli);
    } else {
        // No se pudo conectar con mysqli tampoco: devolver error JSON (con detalle en local)
        $errMsg = '';
        foreach ($hostsToTry as $h) {
            $errMsg .= "host={$h} ";
        }
        http_response_code(500);
        $resp = [
            'success' => false,
            'message' => 'Error de conexión a la base de datos.'
        ];
        if ($isLocal) $resp['detail'] = 'PDO error: ' . $e->getMessage() . '; mysqli attempts failed for hosts: ' . implode(',', $hostsToTry);
        echo json_encode($resp);
        exit;
    }
}
