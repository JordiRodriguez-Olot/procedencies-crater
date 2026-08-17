-- ============================================================
-- MIGRACIÓ CONSOLIDADA — Espai Cràter
-- Executar amb --force perquè continuï encara que alguna part ja
-- estigués aplicada abans:
--   mysql -u root --default-character-set=utf8mb4 --force crater_procedencies < database/migracio_completa.sql
-- ============================================================

USE crater_procedencies;

-- ---- 1) Gestió d'errors: correccions persistents per CP ----
CREATE TABLE IF NOT EXISTS correccions_cp (
  codi_postal CHAR(5) PRIMARY KEY,
  provincia VARCHAR(80) NULL,
  comarca VARCHAR(80) NULL,
  data_correccio DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX idx_entrades_cp ON entrades (codi_postal);

-- ---- 2) Injecció manual d'entrades ----
ALTER TABLE entrades
  ADD COLUMN es_manual TINYINT(1) NOT NULL DEFAULT 0 AFTER pais_es_espanya,
  ADD COLUMN nota_manual VARCHAR(255) NULL AFTER es_manual;

CREATE INDEX idx_entrades_manual ON entrades (es_manual);

-- ---- 3) RGPD: eliminar dades personals innecessàries ----
UPDATE entrades SET email = NULL WHERE email IS NOT NULL;

UPDATE entrades e
JOIN canals c ON c.id = e.canal_id
SET e.nom_client = NULL
WHERE c.codi <> 'CGR' AND e.nom_client IS NOT NULL;

ALTER TABLE entrades DROP COLUMN email;

-- ---- 4) Ampliar codi_postal (evitar error "Data too long") ----
ALTER TABLE entrades MODIFY COLUMN codi_postal VARCHAR(30) NULL;

-- ---- 5) Revisió massiva: errors descartats conscientment ----
CREATE TABLE IF NOT EXISTS errors_descartats (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipus VARCHAR(30) NOT NULL,
  codi_postal VARCHAR(30) NULL,
  pais VARCHAR(80) NULL,
  motiu VARCHAR(255) NULL,
  data_descart DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_descart_cerca (tipus, codi_postal, pais)
) ENGINE=InnoDB;

-- ---- 6) Sistema de login i rols ----
CREATE TABLE IF NOT EXISTS usuaris (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_usuari VARCHAR(50) NOT NULL UNIQUE,
  contrasenya_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','usuari') NOT NULL DEFAULT 'usuari',
  actiu TINYINT(1) NOT NULL DEFAULT 1,
  data_creacio DATETIME DEFAULT CURRENT_TIMESTAMP,
  ultim_acces DATETIME NULL
) ENGINE=InnoDB;

-- ---- 7) Neteja de dades (parcial/total) + auditoria ----
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

-- ---- 8) Registre general d'activitat (correccions, entrades manuals,
--         gestió de productes i usuaris, etc.) ----
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

-- ---- 9) Bloqueig per intents de login fallits ----
CREATE TABLE IF NOT EXISTS intents_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_usuari VARCHAR(50) NOT NULL,
  ip VARCHAR(45) NULL,
  exit_ TINYINT(1) NOT NULL DEFAULT 0,
  data_intent DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intents_usuari_data (nom_usuari, data_intent)
) ENGINE=InnoDB;
