-- ============================================================
-- completar_tablas.sql
-- Tablas adicionales de la aplicacion (recursos, dias bloqueados,
-- recuperacion de contrasena, calificaciones y contactos).
-- Ejecutar DESPUES de importar instalar.sql.
-- ============================================================
USE usuarios;

CREATE TABLE IF NOT EXISTS recursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reserva_recursos (
    reserva_id INT NOT NULL,
    recurso_id INT NOT NULL,
    cantidad INT NOT NULL DEFAULT 1,
    PRIMARY KEY (reserva_id, recurso_id),
    FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    FOREIGN KEY (recurso_id) REFERENCES recursos(id)
);

CREATE TABLE IF NOT EXISTS dias_bloqueados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL UNIQUE,
    motivo VARCHAR(255) NULL,
    regla_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bloqueos por dia de la semana (0 = domingo ... 6 = sabado)
CREATE TABLE IF NOT EXISTS dias_bloqueados_recurrent (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dia TINYINT NOT NULL,
    motivo VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (dia)
);

CREATE TABLE IF NOT EXISTS codigos_recuperacion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL,
    codigo VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS calificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    calificacion TINYINT NOT NULL,
    comentario TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- contactos (widget de servicio al cliente)
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
