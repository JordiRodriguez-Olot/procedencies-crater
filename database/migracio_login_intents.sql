-- ============================================================
-- Migració: registre d'intents de login (per bloquejar
-- temporalment després de diversos intents fallits).
-- ============================================================

USE crater_procedencies;

CREATE TABLE IF NOT EXISTS intents_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_usuari VARCHAR(50) NOT NULL,
  ip VARCHAR(45) NULL,
  exit_ TINYINT(1) NOT NULL DEFAULT 0,
  data_intent DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intents_usuari_data (nom_usuari, data_intent)
) ENGINE=InnoDB;
