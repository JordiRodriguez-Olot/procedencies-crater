<?php

/**
 * Autenticació i autorització. Només hi entra qui consta a la taula
 * `usuaris` (actiu). Dos orígens:
 *  - local: contrasenya pròpia (password_hash).
 *  - ad:    contrasenya del domini, validada amb un bind a l'Active Directory.
 *
 * L'usuari es rellegeix de la BD a cada petició: desactivar-lo o canviar-li
 * el rol té efecte immediat, sense esperar que caduqui la sessió.
 */

/**
 * @return array<string, mixed>|null
 */
function carregaUsuari(int $id): ?array
{
    // Crater manté els noms de columna històrics (nom_usuari, contrasenya_hash):
    // aquí s'exposen amb els noms que fa servir la resta del codi de plantilla.
    $usuari = dbFila('SELECT *, nom_usuari AS usuari, contrasenya_hash AS password_hash FROM usuaris WHERE id = ?', [$id]);
    if ($usuari === null) {
        return null;
    }
    if (trim((string) ($usuari['nom'] ?? '')) === '') {
        $usuari['nom'] = $usuari['nom_usuari'];
    }
    $usuari['departaments'] = []; // crater no treballa per departaments

    return $usuari;
}

/**
 * La columna `usuari` té col·lació *_ci: la cerca no distingeix majúscules.
 *
 * @return array<string, mixed>|null
 */
function usuariPerNom(string $nomUsuari): ?array
{
    $id = dbValor('SELECT id FROM usuaris WHERE nom_usuari = ?', [trim($nomUsuari)]);

    return $id === null ? null : carregaUsuari((int) $id);
}

/**
 * @return array<string, mixed>|null
 */
function usuariActual(): ?array
{
    iniciaSessio();

    $id = (int) ($_SESSION['usuari_id'] ?? 0);
    if ($id > 0) {
        $usuari = carregaUsuari($id);
        if ($usuari !== null && (int) $usuari['actiu'] === 1) {
            return $usuari;
        }
        unset($_SESSION['usuari_id'], $_SESSION['usuari_nom_usuari']);
    }

    $sso = llegeixCookieSso();
    if ($sso !== null) {
        $usuari = usuariPerNom($sso['username']);
        if ($usuari !== null && (int) $usuari['actiu'] === 1 && $usuari['origen'] === 'ad') {
            estableixSessioUsuari($usuari, 'sso');

            return $usuari;
        }
    }

    return null;
}

/**
 * Amb `app.login_actiu` a `false` (per defecte), no exigeix res: retorna
 * l'usuari real si algú ha entrat voluntàriament, l'usuari fictici de
 * `app.usuari_demo` si no n'hi ha cap de real (vegeu config.php — `null`
 * en una aplicació real), o `null` si tampoc n'hi ha. `usuariActual()` —
 * i per tant `login.php`/`logout.php` — no passa mai per aquí: continua
 * reflectint només sessions reals, encara que l'usuari fictici estigui
 * actiu. Amb `app.login_actiu` a `true`, es comporta com un login
 * clàssic: redirigeix a `login.php` si no hi ha sessió real. Vegeu
 * docs/SEGURETAT.md#login-opcional.
 *
 * @return array<string, mixed>|null
 */
function requereixLogin(): ?array
{
    $usuari = usuariActual();
    if ($usuari !== null) {
        return $usuari;
    }
    if (!config('app.login_actiu', false)) {
        return config('app.usuari_demo');
    }

    $torna = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    if (!empty($_SERVER['QUERY_STRING'])) {
        $torna .= '?' . $_SERVER['QUERY_STRING'];
    }
    redirigeix('login.php?torna=' . urlencode($torna));
}

/**
 * A diferència de requereixLogin(), sempre bloqueja si no es compleix el
 * rol: una pàgina d'administració (Usuaris, Configuració) no es pot obrir
 * a tothom només perquè l'app tingui el login desactivat. Si l'app és
 * oberta, ningú ha entrat de debò i no hi ha cap `app.usuari_demo`
 * configurat, dona un 403 explicant que cal activar `app.login_actiu` (o
 * entrar manualment a login.php) per fer-la servir. Amb l'usuari fictici
 * de la plantilla actiu (rol admin per defecte), hi passa de llarg.
 *
 * @return array<string, mixed>
 */
function requereixRol(string $rol): array
{
    $usuari = requereixLogin();
    if (!teRol($usuari, $rol)) {
        if ($usuari === null && !config('app.login_actiu', false)) {
            renderError(
                403,
                'Accés no disponible',
                "Aquesta pàgina exigeix el rol \"{$rol}\" i l'aplicació té el login desactivat. " .
                "Activa'l a config/config.php → app.login_actiu, o entra manualment a login.php amb un compte que ja el tingui."
            );
        }
        renderError(403, 'Accés no autoritzat', "No tens permís per veure aquesta pàgina.");
    }

    return $usuari;
}

/**
 * Jerarquia segons l'ordre de config('rols'): el primer és el més alt.
 *
 * @param array<string, mixed>|null $usuari
 */
function teRol(?array $usuari, string $rol): bool
{
    if ($usuari === null) {
        return false;
    }
    $ordre = array_keys((array) config('rols', []));
    $posUsuari = array_search($usuari['rol'] ?? '', $ordre, true);
    $posRol = array_search($rol, $ordre, true);

    return $posUsuari !== false && $posRol !== false && $posUsuari <= $posRol;
}

/**
 * @return array<string, mixed> usuari autenticat
 * @throws RuntimeException amb un missatge apte per mostrar
 */
function autentica(string $nomUsuari, string $password): array
{
    $generic = 'Usuari o contrasenya incorrectes.';
    $usuari = usuariPerNom($nomUsuari);

    if ($usuari === null || (int) $usuari['actiu'] !== 1) {
        // Mateix cost que un login real, perquè el temps de resposta no
        // reveli quins usuaris existeixen.
        password_verify($password, '$2y$12$mrniCeZf.z4gHhPsZBE/xOrNJnmESqjkranQ5LsfBJXBbHuspOPkq');
        throw new RuntimeException($generic);
    }

    if ($usuari['origen'] === 'local') {
        if (empty($usuari['password_hash']) || !password_verify($password, $usuari['password_hash'])) {
            throw new RuntimeException($generic);
        }
        if (password_needs_rehash($usuari['password_hash'], PASSWORD_DEFAULT)) {
            dbExecuta('UPDATE usuaris SET contrasenya_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $usuari['id']]);
        }

        return $usuari;
    }

    if (!ldapActiu()) {
        throw new RuntimeException("L'autenticació amb l'Active Directory està desactivada.");
    }
    $ad = ldapAutentica((string) $usuari['usuari'], $password);
    actualitzaDesDeAd((int) $usuari['id'], $ad);
    emetCookieSso(['username' => $usuari['usuari'], 'name' => $ad['nom'], 'email' => $ad['correu'], 'grups' => $ad['grups']]);

    return carregaUsuari((int) $usuari['id']) ?? $usuari;
}

/**
 * Sincronitza nom i correu amb l'AD i, si hi ha un grup d'administradors
 * configurat, en deriva el rol: membre → admin; admin que ja no n'és
 * membre → gestor. Sense grup configurat, els rols es gestionen a mà.
 *
 * @param array{usuari:string, nom:string, correu:?string, grups:string[]} $ad
 */
function actualitzaDesDeAd(int $id, array $ad, bool $ambRol = true): void
{
    dbExecuta('UPDATE usuaris SET nom = ?, correu = ? WHERE id = ?', [$ad['nom'], $ad['correu'], $id]);

    $grup = (string) (ldapConfig(false)['grup_admins'] ?? '');
    if (!$ambRol || $grup === '') {
        return;
    }
    $rolActual = (string) dbValor('SELECT rol FROM usuaris WHERE id = ?', [$id]);
    $esMembre = esMembreGrup($ad['grups'], $grup);
    $rolsDef = array_keys((array) config('rols', []));
    $rolBaix = (string) end($rolsDef); // el rol més baix: on cau un admin que surt del grup
    $rolNou = $esMembre ? 'admin' : ($rolActual === 'admin' ? $rolBaix : $rolActual);
    if ($rolNou !== $rolActual) {
        dbExecuta('UPDATE usuaris SET rol = ? WHERE id = ?', [$rolNou, $id]);
        auditoria('usuari.rol_per_grup', "{$ad['usuari']}: {$rolActual} → {$rolNou}");
    }
}

/**
 * @param array<string, mixed> $usuari
 */
function estableixSessioUsuari(array $usuari, string $via): void
{
    iniciaSessio();
    // Nou identificador de sessió en autenticar-se: evita la fixació de sessió.
    if (PHP_SAPI !== 'cli') {
        session_regenerate_id(true);
    }
    $_SESSION['usuari_id'] = (int) $usuari['id'];
    $_SESSION['usuari_nom_usuari'] = (string) $usuari['usuari'];
    $_SESSION['ultim_moviment'] = time(); // caducitat per inactivitat (src/Auth.php)
    dbExecuta('UPDATE usuaris SET ultim_acces = ? WHERE id = ?', [araSql(), $usuari['id']]);
    auditoria('login', "via {$via}", (string) $usuari['usuari']);
}

function tancaSessio(): void
{
    iniciaSessio();
    auditoria('logout');
    $_SESSION = [];
    if (PHP_SAPI !== 'cli') {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        session_destroy();
    }
    esborraCookieSso();
}
