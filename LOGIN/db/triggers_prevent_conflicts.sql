-- Triggers para prevenir conflictos entre B1/B2 y B3
DELIMITER //
DROP TRIGGER IF EXISTS reservas_before_insert;//
CREATE TRIGGER reservas_before_insert BEFORE INSERT ON reservas
FOR EACH ROW
BEGIN
  IF NEW.espacio = 'B3' THEN
    IF EXISTS (
      SELECT 1 FROM reservas
      WHERE fecha = NEW.fecha AND espacio IN ('B1','B2') AND estado IN ('Pendiente','Aprobada')
        AND (hora_inicio < NEW.hora_fin AND hora_fin > NEW.hora_inicio)
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflicto con B1/B2';
    END IF;
  ELSEIF NEW.espacio IN ('B1','B2') THEN
    IF EXISTS (
      SELECT 1 FROM reservas
      WHERE fecha = NEW.fecha AND espacio = 'B3' AND estado IN ('Pendiente','Aprobada')
        AND (hora_inicio < NEW.hora_fin AND hora_fin > NEW.hora_inicio)
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflicto con B3';
    END IF;
  END IF;
END;//

DROP TRIGGER IF EXISTS reservas_before_update;//
CREATE TRIGGER reservas_before_update BEFORE UPDATE ON reservas
FOR EACH ROW
BEGIN
  IF NEW.espacio = 'B3' THEN
    IF EXISTS (
      SELECT 1 FROM reservas
      WHERE fecha = NEW.fecha AND espacio IN ('B1','B2') AND estado IN ('Pendiente','Aprobada')
        AND (hora_inicio < NEW.hora_fin AND hora_fin > NEW.hora_inicio) AND id <> NEW.id
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflicto con B1/B2';
    END IF;
  ELSEIF NEW.espacio IN ('B1','B2') THEN
    IF EXISTS (
      SELECT 1 FROM reservas
      WHERE fecha = NEW.fecha AND espacio = 'B3' AND estado IN ('Pendiente','Aprobada')
        AND (hora_inicio < NEW.hora_fin AND hora_fin > NEW.hora_inicio) AND id <> NEW.id
    ) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conflicto con B3';
    END IF;
  END IF;
END;//

DELIMITER ;
