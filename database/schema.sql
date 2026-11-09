-- ============================================================
-- Espai Cràter - Control de procedències
-- Esquema de base de dades (MySQL / MariaDB - Laragon)
-- ============================================================

CREATE DATABASE IF NOT EXISTS crater_procedencies
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE crater_procedencies;

-- ---------- Taules de referència / catàlegs ----------

CREATE TABLE canals (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codi VARCHAR(20) NOT NULL UNIQUE,   -- TAQUILLA, ONLINE, CGR
  nom VARCHAR(50) NOT NULL
) ENGINE=InnoDB;

INSERT INTO canals (codi, nom) VALUES
  ('TAQUILLA', 'Taquilla'),
  ('ONLINE', 'Canal online'),
  ('CGR', 'CGR (backend)');

-- Nom de producte tal com surt a Clorian
CREATE TABLE productes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_original VARCHAR(150) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- Agrupació de productes (per exemple diverses variants de "Visita guiada"
-- s'agrupen sota un mateix grup per poder filtrar de manera conjunta)
CREATE TABLE mapeig_productes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_original VARCHAR(150) NOT NULL UNIQUE,
  grup VARCHAR(150) NOT NULL
) ENGINE=InnoDB;

-- Províncies espanyoles, identificades pel prefix de 2 dígits del codi postal
CREATE TABLE provincies (
  codi_prefix CHAR(2) PRIMARY KEY,
  nom VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

-- Comarques catalanes
CREATE TABLE comarques (
  codi INT UNSIGNED PRIMARY KEY,
  nom VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

-- Municipis catalans i la seva comarca.
-- NOTA: fem servir el NOM del municipi com a clau, no el codi, perquè
-- l'Excel origen fa servir dos sistemes de codificació de municipi
-- diferents a les pestanyes "DeMunicipiAComarca" i "CP municipis
-- Catalunya" (no són compatibles entre si), mentre que els noms sí
-- que coincideixen al 100%.
CREATE TABLE municipis (
  nom_municipi VARCHAR(100) PRIMARY KEY,
  codi_comarca INT UNSIGNED,
  FOREIGN KEY (codi_comarca) REFERENCES comarques(codi)
) ENGINE=InnoDB;

-- Relació codi postal (Catalunya) -> municipi. Un mateix CP pot repetir
-- municipi (barris/districtes), per això no és clau única per si sola.
CREATE TABLE cp_municipis (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codi_postal CHAR(5) NOT NULL,
  nom_municipi VARCHAR(100) NOT NULL,
  INDEX idx_cp (codi_postal),
  FOREIGN KEY (nom_municipi) REFERENCES municipis(nom_municipi)
) ENGINE=InnoDB;

-- Correccions manuals per codi postal (gestió d'errors). S'apliquen
-- automàticament en importar, perquè no calgui repetir-les cada mes.
CREATE TABLE correccions_cp (
  codi_postal CHAR(5) PRIMARY KEY,
  provincia VARCHAR(80) NULL,
  comarca VARCHAR(80) NULL,
  data_correccio DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Casos d'error revisats i descartats conscientment (p. ex. "país buit"
-- que és una dada real del visitant, no un error a corregir)
CREATE TABLE errors_descartats (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipus VARCHAR(30) NOT NULL,
  codi_postal VARCHAR(30) NULL,
  pais VARCHAR(80) NULL,
  motiu VARCHAR(255) NULL,
  data_descart DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_descart_cerca (tipus, codi_postal, pais)
) ENGINE=InnoDB;

-- ---------- Usuaris i rols ----------

CREATE TABLE usuaris (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_usuari VARCHAR(50) NOT NULL UNIQUE,
  nom VARCHAR(200) NULL,
  correu VARCHAR(200) NULL,
  contrasenya_hash VARCHAR(255) NULL, -- NULL als comptes del domini (origen 'ad')
  rol ENUM('admin','usuari') NOT NULL DEFAULT 'usuari',
  origen ENUM('local','ad') NOT NULL DEFAULT 'local',
  actiu TINYINT(1) NOT NULL DEFAULT 1,
  data_creacio DATETIME DEFAULT CURRENT_TIMESTAMP,
  ultim_acces DATETIME NULL
) ENGINE=InnoDB;

-- Registre d'auditoria de neteges de dades (parcials o totals), perquè
-- sempre quedi constància de qui ha esborrat què i quan.
CREATE TABLE registre_neteja (
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

-- Registre general d'activitat (correccions de CP, entrades manuals,
-- suggeriments massius, gestió de productes, gestió d'usuaris...).
CREATE TABLE registre_activitat (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuari_id INT UNSIGNED NULL,
  nom_usuari VARCHAR(50) NULL,
  tipus VARCHAR(50) NOT NULL,
  detall VARCHAR(500) NULL,
  data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuari_id) REFERENCES usuaris(id) ON DELETE SET NULL,
  INDEX idx_activitat_data (data_hora)
) ENGINE=InnoDB;

-- Intents de login (per bloquejar temporalment després de diversos
-- intents fallits consecutius)
CREATE TABLE intents_login (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_usuari VARCHAR(50) NOT NULL,
  ip VARCHAR(45) NULL,
  exit_ TINYINT(1) NOT NULL DEFAULT 0,
  data_intent DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_intents_usuari_data (nom_usuari, data_intent)
) ENGINE=InnoDB;

-- ---------- Importacions (traçabilitat) ----------

CREATE TABLE importacions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  canal_id TINYINT UNSIGNED NOT NULL,
  nom_fitxer VARCHAR(255) NOT NULL,
  data_visita_inici DATE NULL,
  data_visita_fi DATE NULL,
  files_importades INT UNSIGNED DEFAULT 0,
  data_importacio DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (canal_id) REFERENCES canals(id)
) ENGINE=InnoDB;

-- ---------- Taula de fets: entrades ----------

CREATE TABLE entrades (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  importacio_id INT UNSIGNED NOT NULL,
  canal_id TINYINT UNSIGNED NOT NULL,
  producte_id INT UNSIGNED NULL,
  -- Nom del client: NOMÉS s'omple per al canal CGR (normalment nom
  -- d'una entitat/escola, no d'una persona física), i únicament per
  -- poder detectar duplicats de grups escolars. Per a Online i
  -- Taquilla es deixa sempre a NULL (dada personal sense ús a
  -- l'aplicació — principi de minimització de dades, RGPD art. 5.1.c).
  -- El correu electrònic NO es guarda mai (no té cap finalitat).
  nom_client VARCHAR(200) NULL,
  idioma VARCHAR(30) NULL,
  pais VARCHAR(80) NULL,             -- "Procedencia" / "pais"
  comunitat VARCHAR(80) NULL,
  provincia VARCHAR(80) NULL,        -- provincia textual si venia a l'Excel
  codi_postal VARCHAR(30) NULL,
  pax INT UNSIGNED NOT NULL DEFAULT 0,
  any_visita SMALLINT UNSIGNED NOT NULL,
  mes_visita TINYINT UNSIGNED NOT NULL,
  anymes INT UNSIGNED NOT NULL,      -- format YYYYMM, per filtrar ràpid

  -- Camps derivats a l'importar (per no haver de fer joins pesats als gràfics)
  provincia_derivada VARCHAR(80) NULL,
  comarca_derivada VARCHAR(80) NULL,
  pais_es_espanya TINYINT(1) NOT NULL DEFAULT 0,
  es_manual TINYINT(1) NOT NULL DEFAULT 0,
  nota_manual VARCHAR(255) NULL,

  FOREIGN KEY (importacio_id) REFERENCES importacions(id),
  FOREIGN KEY (canal_id) REFERENCES canals(id),
  FOREIGN KEY (producte_id) REFERENCES productes(id),

  INDEX idx_anymes (anymes),
  INDEX idx_canal (canal_id),
  INDEX idx_producte (producte_id),
  INDEX idx_pais (pais),
  INDEX idx_provincia (provincia_derivada),
  INDEX idx_comarca (comarca_derivada),
  INDEX idx_manual (es_manual),
  INDEX idx_entrades_cp (codi_postal)
) ENGINE=InnoDB;

-- Configuració editable des del navegador (Active Directory...). Secrets xifrats.
CREATE TABLE configuracio (
  clau VARCHAR(100) NOT NULL PRIMARY KEY,
  valor TEXT NULL,
  xifrat TINYINT(1) NOT NULL DEFAULT 0,
  actualitzat_el DATETIME NULL,
  actualitzat_per VARCHAR(100) NULL
) ENGINE=InnoDB;
