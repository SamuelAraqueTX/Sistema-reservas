<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../session.php';

// Solo por CLI o por un administrador autenticado.
if (php_sapi_name() !== 'cli') {
    $rolActual = $_SESSION['user']['rol'] ?? '';
    if (empty($_SESSION['user']) || !in_array($rolActual, ['administrativo', 'practicante'], true)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Acceso denegado: ejecuta este script por consola (php api/ejecutar_migracion.php) o inicia sesión como administrador.\n";
        exit;
    }
}

header('Content-Type: text/plain; charset=utf-8');

/**
 * Runner de migraciones idempotente:
 * - Evita re-ejecutar migraciones ya aplicadas (reduce riesgo de duplicados / rompe cosas).
 * - Registra ejecución en `migraciones_aplicadas`.
 */

function ensureMigrationsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migraciones_aplicadas (
            nombre VARCHAR(255) PRIMARY KEY,
            aplicado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

function isMigrationApplied(PDO $pdo, string $nombre): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM migraciones_aplicadas WHERE nombre = ? LIMIT 1");
    $stmt->execute([$nombre]);
    return (bool)$stmt->fetchColumn();
}

function markMigrationApplied(PDO $pdo, string $nombre): void {
    $stmt = $pdo->prepare("
        INSERT INTO migraciones_aplicadas (nombre) VALUES (?)
        ON DUPLICATE KEY UPDATE aplicado_en = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$nombre]);
}

function runSqlMigration(PDO $pdo, string $nombre, string $filePath): void {
    if (isMigrationApplied($pdo, $nombre)) {
        echo "Omitiendo {$nombre}: ya aplicada.\n";
        return;
    }

    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new Exception("No se pudo leer migración: {$filePath}");
    }

    try {
        // DDL en MySQL suele ser auto-commit; evitar beginTransaction reduce errores
        // como "There is no active transaction".
        $pdo->exec($sql);
        markMigrationApplied($pdo, $nombre);
        echo "Migración aplicada: {$nombre}\n";
    } catch (Throwable $e) {
        // Caso tolerado: re-ejecución donde el SQL ya creó índices/constraints
        // y el motor falla por "Duplicate key name".
        $msg = $e->getMessage();
        if (strpos($msg, "Duplicate key name") !== false || strpos($msg, "1061") !== false) {
            // Marcamos como aplicada para que el runner no se quede atascado en el re-run.
            // Esto evita que bloqueemos el resto de migraciones (p.ej. cobros).
            markMigrationApplied($pdo, $nombre);
            echo "Aviso: migración {$nombre} falló por duplicado (se tolera y se marca como aplicada): {$msg}\n";
            return;
        }

        throw new Exception("Error en migración {$nombre}: " . $msg);
    }
}

function getForceMigrations(): array {
    // Soporta:
    // - URL: ?force=migracion_cobros.sql  ó ?force=all
    // - CLI: php api/ejecutar_migracion.php --force=migracion_cobros.sql ó --force=all
    $force = null;

    if (isset($_GET['force'])) {
        $force = (string)$_GET['force'];
    } else {
        // CLI
        $argvCopy = $GLOBALS['argv'] ?? [];
        foreach ($argvCopy as $arg) {
            if (is_string($arg) && str_starts_with($arg, '--force=')) {
                $force = substr($arg, strlen('--force='));
                break;
            }
        }
    }

    if (!$force) return [];

    $force = trim($force);
    if ($force === 'all') {
        return ['*'];
    }

    return [$force];
}

try {
    ensureMigrationsTable($pdo);

    $forceList = getForceMigrations();

    $migrations = [
        'migracion_codigos.sql' => __DIR__ . '/migracion_codigos.sql',
        'migracion_cobros.sql'  => __DIR__ . '/migracion_cobros.sql',
        'migracion_recibos.sql' => __DIR__ . '/migracion_recibos.sql',
    ];

    foreach ($migrations as $nombre => $filePath) {
        $shouldForce = in_array('*', $forceList, true) || in_array($nombre, $forceList, true);

        if ($shouldForce) {
            // Re-ejecutar aunque esté marcado como aplicado.
            $sql = file_get_contents($filePath);
            if ($sql === false) {
                throw new Exception("No se pudo leer migración: {$filePath}");
            }

            try {
                $pdo->exec($sql);
                markMigrationApplied($pdo, $nombre);
                echo "Migración forzada aplicada: {$nombre}\n";
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (strpos($msg, "Duplicate key name") !== false || strpos($msg, "1061") !== false) {
                    markMigrationApplied($pdo, $nombre);
                    echo "Aviso: migración forzada {$nombre} falló por duplicado (se tolera): {$msg}\n";
                    continue;
                }
                throw new Exception("Error en migración forzada {$nombre}: " . $msg);
            }

            continue;
        }

        // Normal (idempotente por migraciones_aplicadas)
        runSqlMigration($pdo, $nombre, $filePath);
    }

    echo "Runner de migraciones finalizado.\n";
} catch (Exception $e) {
    http_response_code(500);
    echo "Error en runner de migraciones: " . $e->getMessage() . "\n";
}
