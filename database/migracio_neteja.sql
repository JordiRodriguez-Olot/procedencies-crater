-- ============================================================
-- Migració: Neteja de dades (parcial i total) + registre
-- d'auditoria de qui ha esborrat què i quan.
-- ============================================================

USE crater_procedencies;

CREATE TABLE IF NOT EXISTS registre_neteja (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuari_id INT UNSIGNED NULL,
  nom_usuari VARCHAR(50) NULL,
  tipus ENUM('parcial','total') NOT NULL,
  criteris VARCHAR(500) NULL,
  files_eliminades INT UNSIGNED NOT NULL DEFAULT 0,
  pax_eliminats INT UNSIGNED NOT NULL DEFAULT 0,
  data_eliminacio DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuari_id) REFERENCES usuaris(id) ON DELETE SET NULL
) ENGINE=InnoDB;
