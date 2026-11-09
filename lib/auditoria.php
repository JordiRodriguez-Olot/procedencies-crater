<?php

/**
 * Registre d'accions rellevants: es desa a la taula `registre_activitat`
 * (la mateixa que fa servir src/Registre.php). Mai hi desis contrasenyes,
 * tokens ni dades personals innecessàries al camp detall.
 */

function auditoria(string $accio, ?string $detall = null, ?string $usuari = null): void
{
    try {
        $id = (int) ($_SESSION['usuari_id'] ?? 0);
        $nom = $usuari ?? ($_SESSION['usuari_nom_usuari'] ?? null);
        dbExecuta(
            'INSERT INTO registre_activitat (usuari_id, nom_usuari, tipus, detall) VALUES (?, ?, ?, ?)',
            [
                $id > 0 ? $id : null,
                $nom === null ? null : mb_substr($nom, 0, 50),
                mb_substr($accio, 0, 50),
                $detall === null ? null : mb_substr($detall, 0, 500),
            ]
        );
    } catch (Throwable $e) {
        // L'auditoria mai ha de trencar l'acció principal.
        error_log('Auditoria: ' . $e->getMessage());
    }
}
