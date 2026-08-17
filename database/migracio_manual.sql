-- ============================================================
-- Migració: Injecció manual d'entrades (casos excepcionals)
-- ============================================================

USE crater_procedencies;

ALTER TABLE entrades
  ADD COLUMN es_manual TINYINT(1) NOT NULL DEFAULT 0 AFTER pais_es_espanya,
  ADD COLUMN nota_manual VARCHAR(255) NULL AFTER es_manual;

CREATE INDEX idx_entrades_manual ON entrades (es_manual);
