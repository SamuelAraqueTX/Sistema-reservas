<?php
header('Content-Type: application/json');
require_once '../config.php';
require_once '../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

$pregunta = trim($data['pregunta'] ?? '');
$categoria = trim($data['categoria'] ?? 'consulta');
$usuario_id = isset($_SESSION['user']) ? (int)($_SESSION['user']['id'] ?? 0) : 0;
$nombre = trim($data['nombre'] ?? '');
$email = trim($data['email'] ?? '');

if ($usuario_id > 0) {
    // Si está autenticado, usar datos de sesión
    $nombre = $_SESSION['user']['nombre'] ?? $nombre;
    $email = $_SESSION['user']['email'] ?? $email;
}

if (empty($pregunta)) {
    echo json_encode(['success' => false, 'message' => 'La pregunta no puede estar vacía']);
    exit;
}

if (empty($nombre) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Nombre y email son requeridos']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Email no válido']);
    exit;
}

try {
    // Buscar respuesta automática
    $respuestaAutomatica = obtenerRespuestaAutomatica($pregunta);
    
    // Guardar la pregunta
    $stmt = $pdo->prepare(
        "INSERT INTO contactos (usuario_id, nombre, email, mensaje, categoria, respuesta)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $usuario_id > 0 ? $usuario_id : null,
        $nombre,
        $email,
        $pregunta,
        in_array($categoria, ['consulta', 'problema', 'sugerencia', 'otro']) ? $categoria : 'consulta',
        $respuestaAutomatica['existe'] ? $respuestaAutomatica['texto'] : null
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Pregunta registrada correctamente',
        'respuesta_automatica' => $respuestaAutomatica['existe'] ? $respuestaAutomatica['texto'] : null,
        'contacto_id' => $pdo->lastInsertId()
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar la pregunta'
    ]);
    error_log("servicio_cliente ERROR: " . $e->getMessage());
}

// Función para detectar palabras clave y dar respuesta automática
function obtenerRespuestaAutomatica($pregunta) {
    $pregunta_lower = strtolower($pregunta);
    
    // Array de palabras clave y respuestas - ORDENADO POR PRIORIDAD
    $respuestas = [
        // B3 (Combinación)
        [
            'palabras' => ['b3', 'b 3', 'cómo funciona b3', 'qué es b3', 'b3 funciona'],
            'respuesta' => '🔗 <strong>¿QUÉ ES B3?</strong><br>
                            B3 es la <strong>COMBINACIÓN de B1 + B2</strong> (100 personas).<br><br>
                            ⚠️ IMPORTANTE:<br>
                            Para poder reservar <strong>B3</strong>, AMBOS espacios deben estar <strong>libres</strong>:<br>
                            • Si B1 está ocupado → No puedes reservar B3 ❌<br>
                            • Si B2 está ocupado → No puedes reservar B3 ❌<br>
                            • Solo si AMBOS están libres → Puedes reservar B3 ✅<br><br>
                            Si reservas B3, automáticamente B1 y B2 quedan bloqueados en ese horario. 🚫'
        ],
        
        // B1 y B2 (Independientes)
        [
            'palabras' => ['b1', 'b2', 'b 1', 'b 2', 'independiente', 'diferencia b1 b2'],
            'respuesta' => '✅ <strong>B1 Y B2 SON INDEPENDIENTES</strong><br><br>
                            📊 CAPACIDADES:<br>
                            • <strong>B1</strong>: 50 personas (espacio 1)<br>
                            • <strong>B2</strong>: 50 personas (espacio 2)<br>
                            • <strong>B3</strong>: 100 personas (B1 + B2 juntos)<br><br>
                            🔓 FUNCIONAMIENTO:<br>
                            • Puedes reservar B1 sin afectar B2 ✅<br>
                            • Puedes reservar B2 sin afectar B1 ✅<br>
                            • Si reservas B1, B3 se bloquea (porque incluye B1) 🚫<br>
                            • Si reservas B2, B3 se bloquea (porque incluye B2) 🚫'
        ],
        
        // CAPACIDAD
        [
            'palabras' => ['capacidad', 'personas', 'cuántas personas', 'aforo'],
            'respuesta' => '👥 <strong>CAPACIDAD DE ESPACIOS</strong><br><br>
                            <strong>B1</strong> → 50 personas<br>
                            <strong>B2</strong> → 50 personas<br>
                            <strong>B3</strong> → 100 personas (combinación)<br><br>
                            Selecciona el espacio según tus necesidades. 📊'
        ],
        
        // 24 HORAS / ANTICIPACIÓN
        [
            'palabras' => ['24 horas', 'anticipacion', 'cuánto tiempo', 'con cuanto', 'adelante', 'antes'],
            'respuesta' => '⏰ <strong>TIEMPO DE ANTICIPACIÓN REQUERIDO</strong><br><br>
                            Las reservas deben realizarse con <strong>MÍNIMO 24 HORAS de anticipación</strong>.<br><br>
                            ❌ NO PERMITIDO: Reservar para hoy o mañana<br>
                            ✅ PERMITIDO: Reservar desde pasado mañana en adelante<br><br>
                            Ejemplo:<br>
                            • Hoy es lunes → Puedes reservar desde miércoles<br>
                            • Hoy es viernes → Puedes reservar desde lunes ⏳'
        ],
        
        // HORARIOS
        [
            'palabras' => ['horario', 'hora', 'abierto', 'disponible horas', 'qué horas', 'de qué'],
            'respuesta' => '🕐 <strong>HORARIO DE OPERACIÓN</strong><br><br>
                            ⏰ <strong>Inicio:</strong> 08:00 AM<br>
                            ⏰ <strong>Cierre:</strong> 09:00 PM (21:00)<br><br>
                            📅 <strong>Días permitidos:</strong><br>
                            • Lunes ✅<br>
                            • Miércoles ✅<br>
                            • Viernes ✅<br>
                            • Sábado ✅<br>
                            • Domingo ✅<br>
                            • Martes ❌<br>
                            • Jueves ❌<br><br>
                            Las reservas deben estar dentro de este rango horario.'
        ],
        
        // ESTADO / PENDIENTE / APROBADA
        [
            'palabras' => ['pendiente', 'estado', 'aprobada', 'rechazada', 'cancelada', 'status'],
            'respuesta' => '📋 <strong>ESTADOS DE RESERVA</strong><br><br>
                            🟡 <strong>PENDIENTE</strong><br>
                            → Esperando aprobación del administrador<br>
                            → El admin revisará tu solicitud<br><br>
                            🟢 <strong>APROBADA</strong><br>
                            → ¡Confirmada! Tu reserva está autorizada<br>
                            → Recibirás notificación por email<br><br>
                            🔴 <strong>RECHAZADA</strong><br>
                            → No fue autorizada<br>
                            → El admin te enviará motivo<br><br>
                            ⚪ <strong>CANCELADA</strong><br>
                            → Cancelada por el usuario<br><br>
                            💡 Puedes ver tus reservas en: <strong>Dashboard → Mis reservas</strong>'
        ],
        
        // CANCELAR
        [
            'palabras' => ['cancelar', 'eliminar', 'borrar', 'quitar reserva'],
            'respuesta' => '❌ <strong>¿PUEDO CANCELAR MI RESERVA?</strong><br><br>
                            ✅ <strong>SÍ, puedes cancelar</strong> si está en estado <strong>PENDIENTE</strong>.<br><br>
                            🚫 <strong>NO puedes cancelar</strong> si está en estado:<br>
                            • APROBADA<br>
                            • RECHAZADA<br>
                            • CANCELADA<br><br>
                            📍 <strong>Cómo cancelar:</strong><br>
                            1. Ve a <strong>Dashboard → Mis reservas</strong><br>
                            2. Encuentra tu reserva<br>
                            3. Click en botón <strong>"Cancelar"</strong><br>
                            4. Confirma la acción<br><br>
                            Para reservas aprobadas, contacta al administrador.'
        ],
        
        // CONFLICTO / HORARIO OCUPADO
        [
            'palabras' => ['conflicto', 'ocupado', 'no disponible', 'ya reservado', 'solapamiento'],
            'respuesta' => '⚠️ <strong>HORARIO NO DISPONIBLE</strong><br><br>
                            El espacio que seleccionaste <strong>NO está disponible</strong> en ese horario.<br><br>
                            Posibles razones:<br>
                            • Ya hay una reserva aprobada en ese horario<br>
                            • Hay una reserva pendiente en ese horario<br>
                            • B3 está reservado (bloquea B1 y B2)<br>
                            • B1 o B2 están reservados (bloquean B3)<br><br>
                            💡 <strong>Soluciones:</strong><br>
                            1. Prueba con otro horario<br>
                            2. Prueba con otro espacio<br>
                            3. Consulta la disponibilidad: <strong>Dashboard → Ver disponibilidad</strong>'
        ],
        
        // CONTACTO ADMINISTRADOR
        [
            'palabras' => ['contacto', 'administrador', 'admin', 'hablar', 'llamar', 'correo'],
            'respuesta' => '📞 <strong>CONTACTAR AL ADMINISTRADOR</strong><br><br>
                            Puedes contactar a través de:<br><br>
                            📧 <strong>Email:</strong> correo de la oficina administrativa<br>
                            🔔 <strong>Este chat:</strong> Escribe tu pregunta/problema<br>
                            📞 <strong>Teléfono:</strong> Pregunta en recepción<br>
                            📍 <strong>Oficina:</strong> Bloque administrativo<br><br>
                            Usaremos este chat para mantenerte informado. ✅'
        ],
        
        // TRES MESES
        [
            'palabras' => ['futuro', 'próximo', 'meses', '3 meses', 'máximo'],
            'respuesta' => '📅 <strong>LÍMITE DE RESERVA FUTURA</strong><br><br>
                            Puedes hacer reservas hasta <strong>3 MESES en el futuro</strong>.<br><br>
                            Ejemplo:<br>
                            • Hoy es 8 de mayo<br>
                            • Puedes reservar hasta el 8 de agosto<br>
                            • No puedes reservar después del 8 de agosto<br><br>
                            📝 <strong>Válido:</strong> Cualquier día en los próximos 3 meses<br>
                            ❌ <strong>No válido:</strong> Fechas pasadas o más allá de 3 meses'
        ],
    ];
    
    // Buscar coincidencia
    foreach ($respuestas as $item) {
        foreach ($item['palabras'] as $palabra) {
            if (strpos($pregunta_lower, $palabra) !== false) {
                return ['existe' => true, 'texto' => $item['respuesta']];
            }
        }
    }
    
    // Respuesta por defecto si no coincide
    return [
        'existe' => true,
        'texto' => '🤔 <strong>Pregunta Registrada</strong><br><br>
                    No tengo una respuesta automática para esto, pero tu pregunta ha sido registrada.<br><br>
                    <strong>Un administrador te contactará pronto</strong> en tu correo para ayudarte.<br><br>
                    Mientras tanto, puedes consultar:<br>
                    • Dashboard → Ver disponibilidad<br>
                    • Dashboard → Mi perfil<br>
                    • Preguntas frecuentes arriba ⬆️'
    ];
}
