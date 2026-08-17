<?php
// ============================================================
// Configuració de connexió i entorn — PLANTILLA
//
// COM FER-SERVIR AQUEST FITXER:
//   1. Copia'l com a "config.php" dins de la mateixa carpeta.
//   2. Omple DB_USER i DB_PASS amb les credencials reals
//      (locals a Laragon, o de producció al servidor).
//   3. El fitxer "config.php" MAI es puja a Git (està al
//      .gitignore). Aquest "config.example.php" sí que es puja,
//      però sense cap dada real.
// ============================================================

define('APP_ENV', 'local'); // 'production' o 'local'

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../storage/php-error.log');
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Reforç de seguretat de la sessió (aplica sempre, abans de qualsevol
// session_start(), que es fa de manera diferida des de src/Auth.php)
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

// ---------- Credencials de connexió a MySQL ----------
// Substitueix pels valors reals al teu config.php local (mai aquí).
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'crater_procedencies');
define('DB_USER', 'canvia_això');
define('DB_PASS', 'canvia_això');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    }
    return $pdo;
}
