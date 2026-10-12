-- Migración: agregar rol coordinador/practicante al ENUM y crear tabla de códigos de invitación
-- Ejecutar una sola vez en producción

-- 1. Ampliar ENUM de roles en usuarios
ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM('administrativo','coordinador','docente','externo','practicante')
    NOT NULL DEFAULT 'externo';

-- 2. Tabla de códigos de invitación
CREATE TABLE IF NOT EXISTS codigos_invitacion (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    codigo       VARCHAR(32)  NOT NULL UNIQUE,
    descripcion  VARCHAR(200) NULL                COMMENT 'Ej: Docentes Facultad Sistemas',
    rol_permitido ENUM('docente','externo','practicante') NULL COMMENT 'NULL = cualquier rol no privilegiado',
    usos_maximos INT NOT NULL DEFAULT 1,
    usos_actuales INT NOT NULL DEFAULT 0,
    activo       TINYINT(1)  NOT NULL DEFAULT 1,
    created_by   INT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_codigo_creador
        FOREIGN KEY (created_by) REFERENCES usuarios(id)
        ON DELETE SET NULL
);

CREATE INDEX idx_codigo_activo ON codigos_invitacion (codigo, activo);

-- 3. Tabla de códigos de verificación para registro
CREATE TABLE IF NOT EXISTS codigos_verificacion (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(254) NOT NULL,
    codigo     VARCHAR(6)   NOT NULL,
    expires_at DATETIME     NOT NULL,
    usado      TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_verif_email ON codigos_verificacion (email, usado);
