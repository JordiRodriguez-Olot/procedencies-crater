-- ============================================================
-- Migració: Protecció de dades (RGPD) — eliminació de dades
-- personals innecessàries.
-- ============================================================

USE crater_procedencies;

UPDATE entrades SET email = NULL WHERE email IS NOT NULL;

UPDATE entrades e
JOIN canals c ON c.id = e.canal_id
SET e.nom_client = NULL
WHERE c.codi <> 'CGR' AND e.nom_client IS NOT NULL;

ALTER TABLE entrades DROP COLUMN email;
