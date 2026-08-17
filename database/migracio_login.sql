-- ============================================================
-- Migració: Sistema de login amb rols (admin / usuari)
-- ============================================================

USE crater_procedencies;

CREATE TABLE IF NOT EXISTS usuaris (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nom_usuari VARCHAR(50) NOT NULL UNIQUE,
  contrasenya_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin','usuari') NOT NULL DEFAULT 'usuari',
  actiu TINYINT(1) NOT NULL DEFAULT 1,
  data_creacio DATETIME DEFAULT CURRENT_TIMESTAMP,
  ultim_acces DATETIME NULL
) ENGINE=InnoDB;
