-- Migración: soporte de recibos y estado de pago para reservas externas
-- Ejecutar una sola vez

ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS recibo_numero VARCHAR(64) DEFAULT NULL;

ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS recibo_generado_en DATETIME DEFAULT NULL;

ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS recibo_estado_pago ENUM('Pendiente','En verificación','Pagado','No pagado') DEFAULT 'Pendiente';
