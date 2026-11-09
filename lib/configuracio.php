<?php

/**
 * Configuració editable des del navegador (pàgina configuracio.php), desada
 * a la taula `configuracio`. Per a valors fixos de desplegament, fes servir
 * config/config.php; per a valors que un administrador ha de poder canviar
 * sense tocar codi (servidor SMTP, AD...), aquesta taula.
 *
 * Claus amb prefix de grup: "correu.servidor", "ad.base_dn"...
 */

/**
 * @return array<string, array{valor:?string, xifrat:bool}>
 */
function configuracioTotes(bool $recarrega = false): array
{
    static $cache = null;
    if ($cache === null || $recarrega) {
        $cache = [];
        foreach (dbFiles('SELECT clau, valor, xifrat FROM configuracio') as $fila) {
            $cache[$fila['clau']] = ['valor' => $fila['valor'], 'xifrat' => (bool) $fila['xifrat']];
        }
    }

    return $cache;
}

function configuracioLlegeix(string $clau, ?string $defecte = null): ?string
{
    $fila = configuracioTotes()[$clau] ?? null;
    if ($fila === null || $fila['valor'] === null) {
        return $defecte;
    }

    return $fila['xifrat'] ? desxifra($fila['valor']) : $fila['valor'];
}

function configuracioBool(string $clau, bool $defecte = false): bool
{
    $valor = configuracioLlegeix($clau);

    return $valor === null ? $defecte : $valor === '1';
}

/**
 * Tots els valors d'un grup, sense el prefix. Els secrets NO es desxifren
 * aquí: es retorna només si estan definits (per mostrar "sense canvis").
 *
 * @param array<string, string|null> $defectes
 * @return array<string, string|null>
 */
function configuracioGrup(string $grup, array $defectes = []): array
{
    $resultat = $defectes;
    $prefix = $grup . '.';
    foreach (configuracioTotes() as $clau => $fila) {
        if (str_starts_with($clau, $prefix)) {
            $resultat[substr($clau, strlen($prefix))] = $fila['xifrat']
                ? ($fila['valor'] !== null && $fila['valor'] !== '' ? '********' : '')
                : $fila['valor'];
        }
    }

    return $resultat;
}

function configuracioDesa(string $clau, ?string $valor, bool $xifrat = false, ?string $usuari = null): void
{
    $valorDesat = ($xifrat && $valor !== null && $valor !== '') ? xifra($valor) : $valor;
    dbExecuta(
        'INSERT INTO configuracio (clau, valor, xifrat, actualitzat_el, actualitzat_per)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE valor = VALUES(valor), xifrat = VALUES(xifrat),
             actualitzat_el = VALUES(actualitzat_el), actualitzat_per = VALUES(actualitzat_per)',
        [$clau, $valorDesat, $xifrat ? 1 : 0, araSql(), $usuari]
    );
    configuracioTotes(true);
}

/**
 * Desa un secret només si l'usuari n'ha escrit un de nou: el camp buit
 * vol dir "conserva l'actual" (la contrasenya mai es torna a mostrar).
 */
function configuracioDesaSecret(string $clau, string $valorRebut, ?string $usuari = null): void
{
    if ($valorRebut !== '') {
        configuracioDesa($clau, $valorRebut, true, $usuari);
    }
}
