<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function normalizeRol(string $rolBruto): string
{
    $rol = mb_strtolower(trim($rolBruto), 'UTF-8');
    if ($rol === '') return '';

    // Normaliza separadores y colapsa guiones raros
    $rol = str_replace(['_', ' '], '-', $rol);
    $rol = preg_replace('/[‐‑‒–—]/u', '-', $rol);
    $rol = preg_replace('/-+/', '-', $rol) ?: $rol;

    // Alias -> canónicos
    $aliases = [
        // Practicante / administrativo (por si la IA mete conectores)
        'practicante-administrativo' => 'practicante',
        'practicante-administrativo ' => 'practicante',
        'logistica' => 'logística',
        'medios-audiovisuales' => 'medios audiovisuales',
        
        // Otros roles válidos (pass-through)
        'administrativo' => 'administrativo',
        'practicante' => 'practicante',
        'docente' => 'docente',
        'externo' => 'externo',
        'logística' => 'logística',
        'infraestructura' => 'infraestructura',
        'medios audiovisuales' => 'medios audiovisuales',
    ];

    return $aliases[$rol] ?? $rol;
}

function require_auth(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: index.html');
        exit;
    }

    // Normalizar rol en backend para consistencia
    if (isset($_SESSION['user']['rol'])) {
        $_SESSION['user']['rol'] = normalizeRol((string)$_SESSION['user']['rol']);
    } elseif (isset($_SESSION['user']['role'])) {
        $_SESSION['user']['rol'] = normalizeRol((string)$_SESSION['user']['role']);
        unset($_SESSION['user']['role']);
    }
}

function require_auth_api(): void
{
    if (empty($_SESSION['user'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'No autorizado'
        ]);
        exit;
    }

    // Normalizar rol en backend para consistencia
    if (isset($_SESSION['user']['rol'])) {
        $_SESSION['user']['rol'] = normalizeRol((string)$_SESSION['user']['rol']);
    } elseif (isset($_SESSION['user']['role'])) {
        $_SESSION['user']['rol'] = normalizeRol((string)$_SESSION['user']['role']);
        unset($_SESSION['user']['role']);
    }
}
