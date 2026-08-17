-- ============================================================
-- Migració: Revisió massiva d'errors — permet descartar
-- (marcar com a revisat/acceptat) casos que no calen corregir.
-- ============================================================

USE crater_procedencies;

CREATE TABLE IF NOT EXISTS errors_descartats (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipus VARCHAR(30) NOT NULL,
  codi_postal VARCHAR(30) NULL,
  pais VARCHAR(80) NULL,
  motiu VARCHAR(255) NULL,
  data_descart DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_descart_cerca (tipus, codi_postal, pais)
) ENGINE=InnoDB;
