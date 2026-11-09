-- Login amb Active Directory + usuaris locals (aliniat amb la plantilla
-- d'apps corporatives). Idempotent i compatible amb MySQL i MariaDB.
-- Ús: mysql -u root --default-character-set=utf8mb4 crater_procedencies < database/migracio_ad.sql

-- usuaris.nom
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE usuaris ADD COLUMN nom VARCHAR(200) NULL AFTER nom_usuari', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuaris' AND COLUMN_NAME = 'nom');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- usuaris.correu
SET @s = (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE usuaris ADD COLUMN correu VARCHAR(200) NULL AFTER nom', 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuaris' AND COLUMN_NAME = 'correu');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- usuaris.origen (els comptes existents són locals)
SET @s = (SELECT IF(COUNT(*) = 0,
  "ALTER TABLE usuaris ADD COLUMN origen ENUM('local','ad') NOT NULL DEFAULT 'local' AFTER rol", 'SELECT 1')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuaris' AND COLUMN_NAME = 'origen');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Els comptes del domini no tenen contrasenya local.
ALTER TABLE usuaris MODIFY contrasenya_hash VARCHAR(255) NULL;

-- Configuració editable des del navegador (AD). Els secrets hi van xifrats.
CREATE TABLE IF NOT EXISTS configuracio (
  clau VARCHAR(100) NOT NULL PRIMARY KEY,
  valor TEXT NULL,
  xifrat TINYINT(1) NOT NULL DEFAULT 0,
  actualitzat_el DATETIME NULL,
  actualitzat_per VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
