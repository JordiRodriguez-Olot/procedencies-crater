<?php

/**
 * SSO entre les apps dels subdominis d'olot.cat (dpm, proveidors,
 * utils-BPM...): cookie 'olot_sso' signada amb HMAC-SHA256 amb un secret
 * compartit (config/local.php → sso.secret). Mateix format que dpm.
 *
 * La cookie està signada però NO xifrada, i qui tingui el secret pot
 * falsificar la identitat de qualsevol usuari a totes les apps: tracta'l
 * com una contrasenya d'administrador.
 *
 * Aquí l'SSO només prova QUI és l'usuari. Què pot fer ho decideix la taula
 * `usuaris` d'aquesta app: si no hi consta (o està inactiu), no entra.
 * Només s'emet per a usuaris d'origen AD: un usuari local amb el mateix nom
 * que un compte de domini podria suplantar-lo a les altres apps.
 */

function ssoActiu(): bool
{
    return (bool) config('sso.actiu', false) && (string) config('sso.secret', '') !== '';
}

/**
 * @param array{username:string, name:string, email:?string, grups:string[]} $usuari
 */
function emetCookieSso(array $usuari): void
{
    if (!ssoActiu() || PHP_SAPI === 'cli') {
        return;
    }
    $caducitat = time() + 8 * 3600;
    $payload = [
        'username' => $usuari['username'],
        'name'     => $usuari['name'],
        'email'    => $usuari['email'],
        // Només el CN curt de cada grup, perquè la cookie no superi la mida màxima.
        'grups'    => array_map(
            fn ($dn) => preg_match('/^CN=([^,]+),/i', $dn, $m) ? $m[1] : $dn,
            $usuari['grups']
        ),
        'exp' => $caducitat,
    ];
    $dades = base64_encode((string) json_encode($payload));
    $signatura = hash_hmac('sha256', $dades, (string) config('sso.secret'));

    setcookie('olot_sso', $dades . '.' . $signatura, [
        'expires'  => $caducitat,
        'path'     => '/',
        'domain'   => (string) config('sso.domini'),
        'secure'   => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * @return array{username:string, name:string, email:?string}|null
 */
function llegeixCookieSso(): ?array
{
    $cookie = (string) ($_COOKIE['olot_sso'] ?? '');
    if (!ssoActiu() || !str_contains($cookie, '.')) {
        return null;
    }
    [$dades, $signatura] = explode('.', $cookie, 2);
    if (!hash_equals(hash_hmac('sha256', $dades, (string) config('sso.secret')), $signatura)) {
        return null;
    }
    $payload = json_decode((string) base64_decode($dades, true), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time() || empty($payload['username'])) {
        return null;
    }

    return [
        'username' => (string) $payload['username'],
        'name'     => (string) ($payload['name'] ?? ''),
        'email'    => $payload['email'] ?? null,
    ];
}

function esborraCookieSso(): void
{
    if (!ssoActiu() || PHP_SAPI === 'cli') {
        return;
    }
    setcookie('olot_sso', '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'domain'   => (string) config('sso.domini'),
        'secure'   => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
