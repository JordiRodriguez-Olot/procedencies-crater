<?php

/**
 * Lectura de la configuració: config/app.php (versionat) fusionat amb
 * config/local.php (secrets, gitignored). Accés per camí amb punts:
 * config('db.dsn'), config('app.nom').
 */

function config(string $clau, mixed $defecte = null): mixed
{
    $valor = configTota();
    foreach (explode('.', $clau) as $part) {
        if (!is_array($valor) || !array_key_exists($part, $valor)) {
            return $defecte;
        }
        $valor = $valor[$part];
    }

    return $valor;
}

/**
 * @return array<string, mixed>
 */
function configTota(?array $substitueix = null): array
{
    static $config = null;

    if ($substitueix !== null) {
        $config = $substitueix;
    }

    if ($config === null) {
        $base = require __DIR__ . '/../config/app.php';
        $fitxerLocal = __DIR__ . '/../config/local.php';
        $local = is_file($fitxerLocal) ? require $fitxerLocal : []; // auditoria-ok: ruta fixa
        $config = array_replace_recursive($base, is_array($local) ? $local : []);
    }

    return $config;
}

/**
 * Només per a tests: fixa una configuració concreta en lloc de llegir els fitxers.
 *
 * @param array<string, mixed> $config
 */
function configEstableix(array $config): void
{
    configTota($config);
}
