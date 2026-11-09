<?php

// Configuració general (es versiona: cap secret aquí). Els secrets van a
// config/local.php (gitignored), que sobreescriu qualsevol clau d'aquest fitxer.
// Les credencials de la BD continuen a config/config.php (DB_*), que és el
// fitxer que ja es manté a mà a cada entorn (local i Plesk).

return [
    'app' => [
        'nom'          => 'Espai Cràter',
        'subtitol'     => 'Control de procedències',
        // Marca de l'app (blanca, sobre el degradat), al costat del logotip de l'Ajuntament.
        'logo_app'     => 'img/logo-crater-blanc.png',
        // Cookie de sessió pròpia (no es trepitja amb altres apps al mateix host).
        'nom_sessio'   => 'CRATER_SESSID',
        'zona_horaria' => 'Europe/Madrid',
        'url_manual'   => null,
        'debug'        => defined('APP_ENV') && APP_ENV === 'local',
        // L'app sempre demana login (vegeu src/Auth.php).
        'login_actiu'  => true,
        'usuari_demo'  => null,
        // Les pàgines encara porten CSS/JS propi en línia i colors fixos: de moment
        // CSP amb 'unsafe-inline' i sense tema fosc. Es treuen quan es migrin a data-*.
        'csp_inline'      => true,
        'tema_selector'   => false,
        'csp_extra' => [
            'script'  => ['https://unpkg.com'],
            'style'   => ['https://unpkg.com', 'https://fonts.googleapis.com'],
            'font'    => ['https://fonts.gstatic.com'],
            'img'     => ['blob:', 'https://*.basemaps.cartocdn.com', 'https://unpkg.com'],
            'connect' => ['https://cdn.jsdelivr.net', 'https://raw.githubusercontent.com'],
        ],
    ],

    // Menú principal de la capçalera. 'rol' (opcional) = rol mínim per veure'l.
    'menu' => [
        ['url' => 'index.php',        'text' => 'Procedències'],
        ['url' => 'mapes.php',        'text' => 'Mapes'],
        ['url' => 'informes.php',     'text' => 'Informes'],
        ['url' => 'importacions.php', 'text' => 'Importacions'],
        ['url' => 'errors.php',       'text' => "Gestió d'errors"],
        ['url' => 'productes.php',    'text' => 'Productes'],
        ['url' => 'neteja_dades.php', 'text' => 'Neteja de dades'],
    ],

    'db' => [
        'dsn'      => 'mysql:host=' . (defined('DB_HOST') ? DB_HOST : '127.0.0.1')
                      . ';dbname=' . (defined('DB_NAME') ? DB_NAME : 'crater_procedencies')
                      . ';charset=' . (defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
        'usuari'   => defined('DB_USER') ? DB_USER : 'root',
        'password' => defined('DB_PASS') ? DB_PASS : '',
    ],

    // Rols (l'ordre marca la jerarquia: el primer és el més alt).
    'rols' => [
        'admin'  => 'Administrador',
        'usuari' => 'Usuari',
    ],

    // SSO entre subdominis d'olot.cat: desactivat (cal secret a config/local.php).
    'sso' => [
        'actiu'  => false,
        'domini' => '.olot.cat',
    ],
];
