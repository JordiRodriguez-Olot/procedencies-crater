<?php

/**
 * Active Directory (LDAP): autenticació, cerca i importació d'usuaris.
 * La configuració es desa a la BD (pàgina Configuració → Active Directory),
 * amb la contrasenya del compte de consulta xifrada.
 *
 * Compte: l'AD de producció és el real. No facis proves de login fallit
 * repetides contra un usuari real: l'AD en pot bloquejar el compte.
 */

/**
 * @return array<string, string|null>
 */
function ldapConfig(bool $ambSecrets = true): array
{
    $cfg = configuracioGrup('ad', [
        'actiu'          => '0',
        'servidor'       => '',
        'port'           => '389',
        'seguretat'      => 'starttls',
        'verifica_cert'  => '1',
        'domini'         => '',
        'base_dn'        => '',
        'compte_consulta' => '',
        'filtre_usuaris' => '(objectCategory=person)(objectClass=user)',
        'atribut_usuari' => 'sAMAccountName',
        'atribut_nom'    => 'displayName',
        'atribut_correu' => 'mail',
        'grup_admins'    => '',
    ]);
    if ($ambSecrets) {
        $cfg['password_consulta'] = configuracioLlegeix('ad.password_consulta', '');
    }

    return $cfg;
}

function ldapActiu(): bool
{
    return configuracioBool('ad.actiu');
}

/**
 * @param array<string, string|null> $cfg
 * @return \LDAP\Connection
 */
function ldapConnecta(array $cfg)
{
    if (!function_exists('ldap_connect')) {
        throw new RuntimeException("L'extensió LDAP de PHP no està activada.");
    }
    if (($cfg['servidor'] ?? '') === '') {
        throw new RuntimeException("No hi ha cap servidor d'Active Directory configurat.");
    }

    // S'ha de fixar abans de ldap_connect() perquè tingui efecte.
    $verifica = ($cfg['verifica_cert'] ?? '1') === '1';
    ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $verifica ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER);

    $esquema = ($cfg['seguretat'] ?? '') === 'ldaps' ? 'ldaps' : 'ldap';
    $port = (int) ($cfg['port'] ?: ($esquema === 'ldaps' ? 636 : 389));
    $conn = ldap_connect("{$esquema}://{$cfg['servidor']}:{$port}");
    if ($conn === false) {
        throw new RuntimeException("L'adreça del servidor LDAP no és vàlida.");
    }
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 5);

    // Sense xifratge, la contrasenya viatja en clar per la xarxa.
    if (($cfg['seguretat'] ?? '') === 'starttls' && !@ldap_start_tls($conn)) {
        throw new RuntimeException("No s'ha pogut establir STARTTLS amb el servidor LDAP: " . ldap_error($conn));
    }

    return $conn;
}

/**
 * "usuari" → "usuari@domini"; si ja porta @ o és un DN, es deixa tal qual.
 *
 * @param array<string, string|null> $cfg
 */
function ldapNomBind(string $usuari, array $cfg): string
{
    $usuari = trim($usuari);
    if (str_contains($usuari, '@') || str_contains($usuari, '=') || ($cfg['domini'] ?? '') === '') {
        return $usuari;
    }

    return $usuari . '@' . $cfg['domini'];
}

/**
 * Filtre complet: condicions fixes de la configuració + la condició de la cerca.
 *
 * @param array<string, string|null> $cfg
 */
function ldapFiltre(array $cfg, string $condicio): string
{
    $fix = trim((string) ($cfg['filtre_usuaris'] ?? ''));

    return $fix === '' ? $condicio : "(&{$fix}{$condicio})";
}

/**
 * El filtre fix de la configuració ha de ser una o més condicions entre
 * parèntesis ben aparellats: "(a=b)(c=d)". Sense això, un filtre com
 * "(a=b))(|(x=*" podria tancar el (& ...) i obrir-ne un altre.
 */
function filtreLdapEquilibrat(string $filtre): bool
{
    if ($filtre === '' || $filtre[0] !== '(' || !str_ends_with($filtre, ')')) {
        return false;
    }
    $nivell = 0;
    foreach (str_split($filtre) as $caracter) {
        $nivell += $caracter === '(' ? 1 : ($caracter === ')' ? -1 : 0);
        if ($nivell < 0) {
            return false;
        }
    }

    return $nivell === 0;
}

function ldapEscapa(string $valor): string
{
    return ldap_escape($valor, '', LDAP_ESCAPE_FILTER);
}

/**
 * Comprova la pertinença a un grup pel DN complet (sense distingir
 * majúscules ni espais després de les comes).
 *
 * @param string[] $grups DNs de memberOf
 */
function esMembreGrup(array $grups, string $dnGrup): bool
{
    $normalitza = fn (string $dn) => mb_strtolower((string) preg_replace('/\s*,\s*/', ',', trim($dn)));
    $objectiu = $normalitza($dnGrup);
    if ($objectiu === '') {
        return false;
    }
    foreach ($grups as $grup) {
        if ($normalitza($grup) === $objectiu) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, mixed> $entrada fila de ldap_get_entries()
 * @param array<string, string|null> $cfg
 * @return array{usuari:string, nom:string, correu:?string, grups:string[]}
 */
function ldapEntradaAUsuari(array $entrada, array $cfg): array
{
    $attr = fn (string $nom) => $entrada[mb_strtolower((string) $cfg[$nom])][0] ?? null;
    $grups = [];
    for ($i = 0; $i < ($entrada['memberof']['count'] ?? 0); $i++) {
        $grups[] = $entrada['memberof'][$i];
    }
    $usuari = (string) $attr('atribut_usuari');

    return [
        'usuari' => $usuari,
        'nom'    => (string) ($attr('atribut_nom') ?? $usuari),
        'correu' => $attr('atribut_correu'),
        'grups'  => $grups,
    ];
}

/**
 * @param array<string, string|null> $cfg
 * @return string[]
 */
function ldapAtributs(array $cfg): array
{
    return [(string) $cfg['atribut_usuari'], (string) $cfg['atribut_nom'], (string) $cfg['atribut_correu'], 'memberOf'];
}

/**
 * Valida usuari i contrasenya fent un bind amb les credencials de l'usuari.
 *
 * @return array{usuari:string, nom:string, correu:?string, grups:string[]}
 * @throws RuntimeException si les credencials no són vàlides
 */
function ldapAutentica(string $usuari, string $password): array
{
    $cfg = ldapConfig();
    // Una contrasenya buida fa un "bind anònim", que l'AD accepta: cal rebutjar-la abans.
    if (trim($usuari) === '' || $password === '') {
        throw new RuntimeException('Usuari o contrasenya incorrectes.');
    }

    $conn = ldapConnecta($cfg);
    if (!@ldap_bind($conn, ldapNomBind($usuari, $cfg), $password)) {
        ldap_unbind($conn);
        throw new RuntimeException('Usuari o contrasenya incorrectes.');
    }

    $nomCurt = explode('@', trim($usuari))[0];
    $filtre = ldapFiltre($cfg, '(' . $cfg['atribut_usuari'] . '=' . ldapEscapa($nomCurt) . ')');
    $resultat = @ldap_search($conn, (string) $cfg['base_dn'], $filtre, ldapAtributs($cfg), 0, 1);
    $entrades = $resultat ? ldap_get_entries($conn, $resultat) : ['count' => 0];
    ldap_unbind($conn);

    if (($entrades['count'] ?? 0) === 0) {
        return ['usuari' => $nomCurt, 'nom' => $nomCurt, 'correu' => null, 'grups' => []];
    }

    return ldapEntradaAUsuari($entrades[0], $cfg);
}

/**
 * Connexió autenticada amb el compte de consulta (només lectura).
 *
 * @param array<string, string|null> $cfg
 * @return \LDAP\Connection
 */
function ldapConnectaServei(array $cfg)
{
    if (($cfg['compte_consulta'] ?? '') === '' || ($cfg['password_consulta'] ?? '') === '') {
        throw new RuntimeException('Falta el compte de consulta o la seva contrasenya.');
    }
    $conn = ldapConnecta($cfg);
    if (!@ldap_bind($conn, ldapNomBind((string) $cfg['compte_consulta'], $cfg), (string) $cfg['password_consulta'])) {
        $error = ldap_error($conn);
        ldap_unbind($conn);
        throw new RuntimeException("El compte de consulta no s'ha pogut autenticar: {$error}");
    }

    return $conn;
}

/**
 * Cerca usuaris per nom d'usuari, nom o correu.
 *
 * @return array<int, array{usuari:string, nom:string, correu:?string, grups:string[]}>
 */
function ldapCerca(string $text, int $limit = 25): array
{
    $cfg = ldapConfig();
    $t = ldapEscapa(trim($text));
    if (mb_strlen(trim($text)) < 2) {
        throw new RuntimeException('Escriu almenys 2 caràcters per cercar.');
    }
    $condicio = "(|({$cfg['atribut_usuari']}=*{$t}*)({$cfg['atribut_nom']}=*{$t}*)({$cfg['atribut_correu']}=*{$t}*))";

    $conn = ldapConnectaServei($cfg);
    // @: amb més resultats que el límit, ldap_search avisa "Sizelimit exceeded" però retorna els primers.
    $resultat = @ldap_search($conn, (string) $cfg['base_dn'], ldapFiltre($cfg, $condicio), ldapAtributs($cfg), 0, $limit);
    if ($resultat === false) {
        $error = ldap_error($conn);
        ldap_unbind($conn);
        throw new RuntimeException("Error en la cerca a l'Active Directory: {$error}");
    }
    $entrades = ldap_get_entries($conn, $resultat);
    ldap_unbind($conn);

    $usuaris = [];
    for ($i = 0; $i < ($entrades['count'] ?? 0); $i++) {
        $usuari = ldapEntradaAUsuari($entrades[$i], $cfg);
        if ($usuari['usuari'] !== '') {
            $usuaris[] = $usuari;
        }
    }
    usort($usuaris, fn ($a, $b) => strcasecmp($a['nom'], $b['nom']));

    return $usuaris;
}

/**
 * @return array{usuari:string, nom:string, correu:?string, grups:string[]}|null
 */
function ldapObteUsuari(string $usuari, $conn = null): ?array
{
    $cfg = ldapConfig();
    $propia = $conn === null;
    $conn ??= ldapConnectaServei($cfg);

    $filtre = ldapFiltre($cfg, '(' . $cfg['atribut_usuari'] . '=' . ldapEscapa($usuari) . ')');
    $resultat = @ldap_search($conn, (string) $cfg['base_dn'], $filtre, ldapAtributs($cfg), 0, 1);
    $entrades = $resultat ? ldap_get_entries($conn, $resultat) : ['count' => 0];
    if ($propia) {
        ldap_unbind($conn);
    }

    return ($entrades['count'] ?? 0) > 0 ? ldapEntradaAUsuari($entrades[0], $cfg) : null;
}

/**
 * Prova pas a pas: connexió, compte de consulta i filtre.
 *
 * @return array<int, array{text:string, ok:bool}>
 */
function ldapProvaConnexio(): array
{
    $cfg = ldapConfig();
    $passos = [];
    try {
        $conn = ldapConnecta($cfg);
        $passos[] = ['text' => "Connexió amb {$cfg['servidor']} (" . ($cfg['seguretat'] ?: 'cap') . ')', 'ok' => true];
        ldap_unbind($conn);

        $conn = ldapConnectaServei($cfg);
        $passos[] = ['text' => "Autenticació del compte de consulta {$cfg['compte_consulta']}", 'ok' => true];

        $filtre = ldapFiltre($cfg, '(' . $cfg['atribut_usuari'] . '=*)');
        $resultat = @ldap_search($conn, (string) $cfg['base_dn'], $filtre, [(string) $cfg['atribut_usuari']], 0, 5);
        if ($resultat === false) {
            $passos[] = ['text' => 'Cerca amb el filtre: ' . ldap_error($conn), 'ok' => false];
        } else {
            $total = ldap_count_entries($conn, $resultat);
            $passos[] = ['text' => "Cerca amb el filtre al DN base: {$total} usuari(s) de mostra trobats", 'ok' => $total > 0];
        }
        ldap_unbind($conn);
    } catch (Throwable $e) {
        $passos[] = ['text' => $e->getMessage(), 'ok' => false];
    }

    return $passos;
}
