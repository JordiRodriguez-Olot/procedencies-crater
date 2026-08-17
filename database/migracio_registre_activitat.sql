-- ============================================================
-- Migració: registre general d'activitat — qui ha corregit un
-- CP, afegit/eliminat una entrada manual, aplicat suggeriments
-- massius, fusionat grups de productes, desfet una importació,
-- o fet gestions d'usuaris. Complementa registre_neteja (que
-- només cobreix els esborrats de dades) amb la resta de canvis.
-- ============================================================

USE crater_procedencies;

CREATE TABLE IF NOT EXISTS registre_activitat (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuari_id INT UNSIGNED NULL,
  nom_usuari VARCHAR(50) NULL,
  tipus VARCHAR(50) NOT NULL,
  detall VARCHAR(500) NULL,
  data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuari_id) REFERENCES usuaris(id) ON DELETE SET NULL,
  INDEX idx_activitat_data (data_hora)
) ENGINE=InnoDB;
