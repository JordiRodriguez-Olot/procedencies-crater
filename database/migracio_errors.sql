-- ============================================================
-- Migració: Gestió d'errors — correccions manuals persistents
-- Executar sobre una base de dades crater_procedencies ja existent.
-- ============================================================

USE crater_procedencies;

CREATE TABLE IF NOT EXISTS correccions_cp (
  codi_postal CHAR(5) PRIMARY KEY,
  provincia VARCHAR(80) NULL,
  comarca VARCHAR(80) NULL,
  data_correccio DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_entrades_cp ON entrades (codi_postal);
