<?php
/**
 * Configuración local de ejemplo.
 *
 * 1) Copia este archivo como  config.local.php
 * 2) Completa tus valores reales
 * 3) NUNCA subas config.local.php a git ni lo compartas (ya está en .gitignore)
 *
 * Alternativa: definir estas mismas variables en el entorno del servidor.
 */

// --- Base de datos ---
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3306');
putenv('DB_NAME=usuarios');
putenv('DB_USER=root');
putenv('DB_PASS=');

// --- Correo (SMTP Gmail con contraseña de aplicación) ---
// Si MAIL_USER / MAIL_PASS quedan vacíos, el sistema funciona en "modo simulación":
// no envía correos y los guarda en un log temporal para desarrollo.
putenv('MAIL_USER=tu_correo@example.com');
putenv('MAIL_PASS=tu_contraseña_de_aplicacion');
putenv('MAIL_FROM_NAME=Sistema de Reservas Poli');

// --- Destinatario de las calificaciones del servicio (opcional) ---
putenv('RATING_TO_EMAIL=admin@example.com');

// --- Datos institucionales (opcionales) ---
// Si se dejan vacíos, los correos y mensajes no muestran estos avisos.
putenv('VISITORS_EMAIL=');
putenv('SERVICEDESK_URL=');

// --- Depuración (dejar en 0 en producción) ---
putenv('MAIL_DEBUG_LOG=0');
