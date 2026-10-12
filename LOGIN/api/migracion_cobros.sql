-- Migración: módulo Cobros (tarifas globales por espacio)
-- Ejecutar una sola vez

-- 1) Agregar campo precio de reserva (guardado por cada reserva)
ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS precio_reserva DECIMAL(10,2) NOT NULL DEFAULT 0;

-- 2) Tabla de tarifas globales (ajustables por administración/practicante)
CREATE TABLE IF NOT EXISTS cobros_tarifas (
    espacio ENUM('B1','B2','B3') PRIMARY KEY,
    precio  DECIMAL(10,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3) Sembrar valores iniciales si está vacío
INSERT INTO cobros_tarifas (espacio, precio)
SELECT 'B1', 0
WHERE NOT EXISTS (SELECT 1 FROM cobros_tarifas WHERE espacio='B1');

INSERT INTO cobros_tarifas (espacio, precio)
SELECT 'B2', 0
WHERE NOT EXISTS (SELECT 1 FROM cobros_tarifas WHERE espacio='B2');

INSERT INTO cobros_tarifas (espacio, precio)
SELECT 'B3', 0
WHERE NOT EXISTS (SELECT 1 FROM cobros_tarifas WHERE espacio='B3');

-- 4) Historial/audit trail de cobros (quién cambió y cuándo)
CREATE TABLE IF NOT EXISTS cobros_historial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    espacio ENUM('B1','B2','B3') NOT NULL,
    precio_anterior DECIMAL(10,2) NOT NULL DEFAULT 0,
    precio_nuevo    DECIMAL(10,2) NOT NULL DEFAULT 0,
    usuario_id INT NULL,
    usuario_nombre VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Índices para consultas rápidas
CREATE INDEX IF NOT EXISTS idx_cobros_historial_espacio_created_at
    ON cobros_historial (espacio, created_at);
