<?php

require_once __DIR__ . '/../lib/arrencada.php';

/**
 * Capa de compatibilitat sobre lib/auth.php (plantilla): les pàgines i les
 * APIs de crater continuen cridant Auth::requerirLogin(), Auth::esAdmin()...
 * però la sessió, els usuaris (locals i de l'Active Directory) i els rols
 * viuen a lib/.
 *
 * Es manté el que ja feia crater:
 *  - Bloqueig temporal després de diversos intents de login fallits
 *    (taula intents_login).
 *  - Caducitat de sessió per inactivitat.
 *
 * Dos rols: 'admin' i 'usuari'. La majoria de l'aplicació no en distingeix
 * (només protegeix l'accés general), a excepció de la gestió d'usuaris,
 * la configuració i l'esborrat total de dades, exclusius d'admin.
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
        iniciaSessio();
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

        if (!$r || (int) $r['total'] < self::LLINDAR_INTENTS) {
            return null;
        }

        $minutsTranscorreguts = (time() - strtotime($r['primer'])) / 60;
        $restants = self::FINESTRA_INTENTS_MINUTS - $minutsTranscorreguts;
        return $restants > 0 ? (int) ceil($restants) : null;
    }

    private static function registrarIntent(PDO $pdo, string $usuari, bool $exit): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $pdo->prepare('INSERT INTO intents_login (nom_usuari, ip, exit_) VALUES (?, ?, ?)')
            ->execute([mb_substr($usuari, 0, 50), $ip, $exit ? 1 : 0]);

        // Neteja d'intents antics (manteniment oportunista, sense cron)
        $pdo->exec("DELETE FROM intents_login WHERE data_intent < (NOW() - INTERVAL 1 DAY)");
    }

    /**
     * Valida usuari i contrasenya contra la BD (comptes locals) o contra
     * l'Active Directory (comptes amb origen 'ad'), i obre la sessió.
     *
     * @return array{ok: bool, error: ?string} 'error' inclou el missatge
     *  a mostrar si ok=false (ja sigui per bloqueig o credencials errònies).
     */
    public static function intentarLogin(PDO $pdo, string $usuari, string $contrasenya): array
    {
        iniciaSessio();

        $minutsRestants = self::minutsBloquejat($pdo, $usuari);
        if ($minutsRestants !== null) {
            return ['ok' => false, 'error' => "Massa intents fallits. Torna-ho a provar d'aquí a {$minutsRestants} minut(s)."];
        }

        try {
            $u = autentica($usuari, $contrasenya);
        } catch (PDOException $e) {
            // Abans que RuntimeException: mai ha d'arribar SQL a l'usuari.
            error_log((string) $e);
            return ['ok' => false, 'error' => "No s'ha pogut iniciar la sessió. Avisa el servei d'informàtica."];
        } catch (RuntimeException $e) {
            self::registrarIntent($pdo, $usuari, false);
            auditoria('login_fallit', null, mb_substr($usuari, 0, 50));
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        self::registrarIntent($pdo, $usuari, true);
        estableixSessioUsuari($u, (string) $u['origen']);

        return ['ok' => true, 'error' => null];
    }

    public static function logout(): void
    {
        tancaSessio();
    }

    /**
     * Comprova sessió activa I que no hagi caducat per inactivitat.
     * Si ha caducat, tanca la sessió automàticament i retorna false.
     */
    public static function esLogat(): bool
    {
        iniciaSessio();
        $teSessio = isset($_SESSION['usuari_id']);
        if ($teSessio && (time() - (int) ($_SESSION['ultim_moviment'] ?? time())) > self::TIMEOUT_INACTIVITAT_SEGONS) {
            self::logout();
            return false;
        }
        if (usuariActual() === null) {
            return false;
        }

        $_SESSION['ultim_moviment'] = time();
        return true;
    }

    public static function usuari(): ?array
    {
        return self::esLogat() ? usuariActual() : null;
    }

    public static function esAdmin(): bool
    {
        return teRol(self::usuari(), 'admin');
    }

    public static function usuariId(): ?int
    {
        iniciaSessio();
        return isset($_SESSION['usuari_id']) ? (int) $_SESSION['usuari_id'] : null;
    }

    public static function nomUsuari(): ?string
    {
        $u = self::usuari();
        return $u === null ? null : (string) $u['usuari'];
    }

    public static function rol(): ?string
    {
        $u = self::usuari();
        return $u === null ? null : (string) $u['rol'];
    }

    /** Per a pàgines HTML: redirigeix a login si no hi ha sessió activa */
    public static function requerirLogin(): void
    {
        iniciaSessio();
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
