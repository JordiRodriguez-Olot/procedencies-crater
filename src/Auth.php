<?php

/**
 * Gestió d'autenticació senzilla amb sessions natives de PHP.
 *
 * Pensada per a un ús intern (intranet de l'ajuntament): no hi ha
 * recuperació de contrasenya per correu (no hi ha servidor de correu
 * connectat), ni verificació d'email. Si algú oblida la contrasenya,
 * un administrador la reinicia manualment des de "Gestió d'usuaris".
 *
 * Dos rols: 'admin' i 'usuari'. La majoria de l'aplicació no en
 * distingeix (només protegeix l'accés general), a excepció de la
 * gestió d'usuaris i l'esborrat total de dades, exclusius d'admin.
 *
 * Inclou dues proteccions bàsiques:
 *  - Bloqueig temporal després de diversos intents de login fallits.
 *  - Caducitat de sessió per inactivitat.
 */
class Auth
{
    /** Intents fallits permesos abans de bloquejar temporalment */
    private const LLINDAR_INTENTS = 5;
    /** Finestra de temps (minuts) en què es compten els intents fallits */
    private const FINESTRA_INTENTS_MINUTS = 15;
    /** Temps d'inactivitat (segons) abans de tancar la sessió sola */
    private const TIMEOUT_INACTIVITAT_SEGONS = 3600; // 1 hora

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Comprova si aquest usuari té massa intents fallits recents.
     * Retorna els minuts que falten per poder tornar a provar, o null
     * si no està bloquejat.
     */
    public static function minutsBloquejat(PDO $pdo, string $usuari): ?int
    {
        $stmt = $pdo->prepare(
            "SELECT MIN(data_intent) AS primer, COUNT(*) AS total
             FROM intents_login
             WHERE nom_usuari = ? AND exit_ = 0
               AND data_intent > (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->execute([$usuari, self::FINESTRA_INTENTS_MINUTS]);
        $r = $stmt->fetch();

        if (!$r || (int)$r['total'] < self::LLINDAR_INTENTS) {
            return null;
        }

        $minutsTranscorreguts = (time() - strtotime($r['primer'])) / 60;
        $restants = self::FINESTRA_INTENTS_MINUTS - $minutsTranscorreguts;
        return $restants > 0 ? (int)ceil($restants) : null;
    }

    private static function registrarIntent(PDO $pdo, string $usuari, bool $exit): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo->prepare('INSERT INTO intents_login (nom_usuari, ip, exit_) VALUES (?, ?, ?)')
            ->execute([$usuari, $ip, $exit ? 1 : 0]);

        // Neteja d'intents antics (manteniment oportunista, sense cron)
        $pdo->exec("DELETE FROM intents_login WHERE data_intent < (NOW() - INTERVAL 1 DAY)");
    }

    /**
     * @return array{ok: bool, error: ?string} 'error' inclou el missatge
     *  a mostrar si ok=false (ja sigui per bloqueig o credencials errònies).
     */
    public static function intentarLogin(PDO $pdo, string $usuari, string $contrasenya): array
    {
        self::iniciar();

        $minutsRestants = self::minutsBloquejat($pdo, $usuari);
        if ($minutsRestants !== null) {
            return ['ok' => false, 'error' => "Massa intents fallits. Torna-ho a provar d'aquí a {$minutsRestants} minut(s)."];
        }

        $stmt = $pdo->prepare('SELECT id, nom_usuari, contrasenya_hash, rol, actiu FROM usuaris WHERE nom_usuari = ?');
        $stmt->execute([$usuari]);
        $u = $stmt->fetch();

        if (!$u || !(int)$u['actiu'] || !password_verify($contrasenya, $u['contrasenya_hash'])) {
            self::registrarIntent($pdo, $usuari, false);
            return ['ok' => false, 'error' => 'Usuari o contrasenya incorrectes.'];
        }

        self::registrarIntent($pdo, $usuari, true);

        // Prevenció bàsica contra fixació de sessió
        session_regenerate_id(true);

        $_SESSION['usuari_id'] = (int)$u['id'];
        $_SESSION['usuari_nom'] = $u['nom_usuari'];
        $_SESSION['usuari_rol'] = $u['rol'];
        $_SESSION['ultim_moviment'] = time();

        $pdo->prepare('UPDATE usuaris SET ultim_acces = NOW() WHERE id = ?')->execute([$u['id']]);

        return ['ok' => true, 'error' => null];
    }

    public static function logout(): void
    {
        self::iniciar();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /**
     * Comprova sessió activa I que no hagi caducat per inactivitat.
     * Si ha caducat, tanca la sessió automàticament i retorna false.
     */
    public static function esLogat(): bool
    {
        self::iniciar();
        if (!isset($_SESSION['usuari_id'])) {
            return false;
        }

        $ultimMoviment = $_SESSION['ultim_moviment'] ?? time();
        if ((time() - $ultimMoviment) > self::TIMEOUT_INACTIVITAT_SEGONS) {
            self::logout();
            return false;
        }

        $_SESSION['ultim_moviment'] = time();
        return true;
    }

    public static function esAdmin(): bool
    {
        self::iniciar();
        return self::esLogat() && ($_SESSION['usuari_rol'] ?? '') === 'admin';
    }

    public static function usuariId(): ?int
    {
        self::iniciar();
        return $_SESSION['usuari_id'] ?? null;
    }

    public static function nomUsuari(): ?string
    {
        self::iniciar();
        return $_SESSION['usuari_nom'] ?? null;
    }

    public static function rol(): ?string
    {
        self::iniciar();
        return $_SESSION['usuari_rol'] ?? null;
    }

    /** Per a pàgines HTML: redirigeix a login si no hi ha sessió activa */
    public static function requerirLogin(): void
    {
        self::iniciar();
        $teniaSessio = isset($_SESSION['usuari_id']);
        if (!self::esLogat()) {
            $desti = $_SERVER['REQUEST_URI'] ?? 'index.php';
            $motiu = $teniaSessio ? '&expirat=1' : '';
            header('Location: login.php?redirect=' . urlencode($desti) . $motiu);
            exit;
        }
    }

    /** Per a pàgines HTML que exigeixen rol admin (p. ex. gestió d'usuaris) */
    public static function requerirAdmin(): void
    {
        self::requerirLogin();
        if (!self::esAdmin()) {
            header('Location: index.php?error=sense_permisos');
            exit;
        }
    }

    /** Per a endpoints API: retorna un JSON 401 en lloc de redirigir */
    public static function requerirLoginApi(): void
    {
        self::iniciar();
        if (!self::esLogat()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Sessió no iniciada o caducada. Torna a la pàgina de login.']);
            exit;
        }
    }

    /** Per a endpoints API que exigeixen rol admin */
    public static function requerirAdminApi(): void
    {
        self::requerirLoginApi();
        if (!self::esAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Aquesta acció requereix el rol d\'administrador.']);
            exit;
        }
    }
}
