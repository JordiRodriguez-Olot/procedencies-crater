-- ============================================================
-- Control de càrrega mensual: empremta del contingut de cada importació,
-- per detectar el mateix fitxer pujat dues vegades. Idempotent: es pot
-- executar més d'un cop (phpMyAdmin o mysql) sense errors.
-- El codi funciona igual sense aquesta columna (simplement no detecta
-- fitxers repetits), així que l'ordre respecte el desplegament no és crític.
-- ============================================================

SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE importacions ADD COLUMN signatura CHAR(64) NULL AFTER files_importades, ADD INDEX idx_importacions_signatura (signatura)',
  'SELECT 1')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'importacions' AND COLUMN_NAME = 'signatura');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
