<?php

/**
 * Punt d'entrada comú: cada pàgina comença amb
 *   require __DIR__ . '/lib/arrencada.php';
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/seguretat.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/xifratge.php';
require_once __DIR__ . '/configuracio.php';
require_once __DIR__ . '/auditoria.php';
require_once __DIR__ . '/ldap.php';
require_once __DIR__ . '/sso.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/vistes.php';
require_once __DIR__ . '/layout.php';

date_default_timezone_set((string) config('app.zona_horaria', 'Europe/Madrid'));
mb_internal_encoding('UTF-8');
ini_set('display_errors', config('app.debug', false) ? '1' : '0');

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    // Mai es mostren traces ni SQL a l'usuari, excepte amb app.debug a local.
    renderError(
        500,
        'Error inesperat',
        config('app.debug', false)
            ? $e->getMessage()
            : "S'ha produït un error. Si es repeteix, avisa el servei d'informàtica."
    );
});
