-- ============================================================
-- Migració: ampliar el camp codi_postal per evitar errors
-- d'importació quan una fila conté un valor inesperadament llarg.
-- ============================================================

USE crater_procedencies;

ALTER TABLE entrades MODIFY COLUMN codi_postal VARCHAR(30) NULL;
