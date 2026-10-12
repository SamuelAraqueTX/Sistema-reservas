-- Tabla para almacenar preguntas de contacto/servicio al cliente
CREATE TABLE IF NOT EXISTS contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    asunto VARCHAR(200),
    mensaje TEXT NOT NULL,
    categoria ENUM('consulta', 'problema', 'sugerencia', 'otro') DEFAULT 'consulta',
    estado ENUM('nuevo', 'leído', 'respondido') DEFAULT 'nuevo',
    respuesta TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX idx_contactos_usuario ON contactos(usuario_id);
CREATE INDEX idx_contactos_estado ON contactos(estado);
CREATE INDEX idx_contactos_fecha ON contactos(created_at);
