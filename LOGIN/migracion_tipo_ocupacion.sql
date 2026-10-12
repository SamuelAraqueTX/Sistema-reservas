-- Migración: Agregar campo tipo_ocupacion a tabla reservas
-- Fecha: 2026-05-25
-- Descripción: Agregar el tipo de ocupación para clasificar las reservas

USE usuarios;

-- Agregar columna tipo_ocupacion a tabla reservas
ALTER TABLE reservas
ADD COLUMN tipo_ocupacion ENUM('Clases particulares', 'Evento', 'Ocupación de logística', 'Ocupación de infraestructura', 'Ocupación de medios audiovisuales') 
DEFAULT 'Evento' AFTER estado;

-- Agregar índice para búsquedas por tipo_ocupacion (opcional, pero útil)
CREATE INDEX idx_reservas_tipo_ocupacion ON reservas (tipo_ocupacion);

-- Verificación: mostrar la estructura actualizada
DESCRIBE reservas;
