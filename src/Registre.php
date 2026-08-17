<?php

/**
 * Registre general d'activitat/auditoria: qui ha fet quin canvi i quan.
 * Complementa registre_neteja (específic dels esborrats de dades) amb
 * la resta d'accions que modifiquen dades (correccions, entrades
 * manuals, gestió de productes, gestió d'usuaris...).
 *
 * Ús: Registre::afegir($pdo, 'correccio_cp', "CP 17800 -> comarca Garrotxa");
 */
class Registre
{
    public static function afegir(PDO $pdo, string $tipus, string $detall = ''): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO registre_activitat (usuari_id, nom_usuari, tipus, detall)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([Auth::usuariId(), Auth::nomUsuari(), $tipus, mb_substr($detall, 0, 500)]);
        } catch (Throwable $e) {
            // Un fallada en registrar l'auditoria mai ha de trencar
            // l'acció principal que s'estava fent.
        }
    }
}
