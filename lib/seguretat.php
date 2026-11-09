<?php

/**
 * Utilitats de seguretat transversals: capçaleres HTTP, sessió, CSRF,
 * escapat de sortida, missatges flash i limitació d'intents de login.
 */

function esHttps(): bool
{
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

function ipClient(): string
{
    // REMOTE_ADDR i prou: X-Forwarded-For el pot falsificar qualsevol client
    // si no hi ha un proxy de confiança al davant que el reescrigui.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * CSP estricta, sense 'unsafe-inline': cap <script> ni style="" en línia.
 * El JS va a assets/js/ i les dades per al JS, en atributs data-*.
 */
function enviaCapcaleresSeguretat(): void
{
    static $enviades = false;
    if ($enviades || headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    $enviades = true;

    $cdn = 'https://cdn.jsdelivr.net';
    // Ampliacions per app (config app.csp_extra): hosts addicionals per directiva.
    $extra = (array) config('app.csp_extra', []);
    $amb = fn (string $dir, string $base) => trim($base . ' ' . implode(' ', (array) ($extra[$dir] ?? [])));
    // app.csp_inline: permet <script>/<style> en línia (pàgines que encara no s'han migrat a data-*).
    $inline = config('app.csp_inline', false) ? " 'unsafe-inline'" : '';
    header("Content-Security-Policy: default-src 'self'; "
        . 'script-src ' . $amb('script', "'self'{$inline} {$cdn}") . '; '
        . 'style-src ' . $amb('style', "'self'{$inline} {$cdn}") . '; '
        . 'img-src ' . $amb('img', "'self' data:") . '; '
        . 'font-src ' . $amb('font', "'self' {$cdn}") . '; '
        . 'connect-src ' . $amb('connect', "'self'") . "; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    if (esHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

/**
 * Sessió amb nom de cookie propi (config app.nom_sessio): mai un
 * session_start() sol. Sense 'domain': cada app té la seva sessió; l'SSO
 * entre subdominis va per la cookie signada 'olot_sso' (lib/sso.php).
 */
function iniciaSessio(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_SAPI === 'cli') {
        // Tests i tasques programades: sense cookies, $_SESSION és un array normal.
        $_SESSION ??= [];

        return;
    }

    enviaCapcaleresSeguretat();
    ini_set('session.use_strict_mode', '1');
    session_name((string) config('app.nom_sessio', 'APP_SESSID'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => esHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Nom de servidor o adreça IPv4 (per a camps de configuració com el servidor SMTP o LDAP).
 */
function esNomServidor(string $valor): bool
{
    return (bool) preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/', $valor);
}

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// --- CSRF ---

function tokenCsrf(): string
{
    iniciaSessio();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function campCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(tokenCsrf()) . '">';
}

/**
 * @throws RuntimeException si el token no coincideix
 */
function verificaCsrf(): void
{
    iniciaSessio();
    $esperat = (string) ($_SESSION['csrf'] ?? '');
    $rebut = (string) ($_POST['csrf'] ?? '');
    // hash_equals('', '') és true: sense aquesta comprovació, una sessió
    // sense token acceptaria qualsevol POST sense token.
    if ($esperat === '' || !hash_equals($esperat, $rebut)) {
        throw new RuntimeException('La sessió del formulari ha caducat. Torna-ho a provar.');
    }
}

// --- Missatges flash (patró Post/Redirect/Get) ---

function desaFlash(string $tipus, string $missatge): void
{
    iniciaSessio();
    $_SESSION['flash'][] = ['tipus' => $tipus, 'missatge' => $missatge];
}

/**
 * @return array<int, array{tipus:string, missatge:string}>
 */
function agafaFlash(): array
{
    iniciaSessio();
    $missatges = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $missatges;
}

/**
 * Desa valors d'un formulari per tornar-los a omplir després d'un error.
 *
 * @param array<string, mixed> $valors
 */
function desaAntic(array $valors): void
{
    iniciaSessio();
    unset($valors['csrf'], $valors['password'], $valors['password_repeteix']);
    $_SESSION['antic'] = $valors;
}

/**
 * @return array<string, mixed>
 */
function agafaAntic(): array
{
    iniciaSessio();
    $valors = $_SESSION['antic'] ?? [];
    unset($_SESSION['antic']);

    return $valors;
}

function redirigeix(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Només accepta rutes relatives de la mateixa app ("usuaris.php?x=1"),
 * per evitar redireccions obertes cap a un domini extern.
 */
function urlRetornSegura(?string $url, string $perDefecte = 'index.php'): string
{
    if ($url === null || $url === '' || !preg_match('#^[A-Za-z0-9_\-]+\.php(\?[^\r\n]*)?$#', $url)) {
        return $perDefecte;
    }

    return $url;
}
