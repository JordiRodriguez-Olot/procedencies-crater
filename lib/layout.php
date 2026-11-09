<?php

/**
 * Estructura comuna de les pàgines: <head>, capçalera amb degradat, menú,
 * píndola d'usuari (amb selector de tema) i peu. Vegeu docs/ESTIL.md.
 *
 *   renderCapcalera('Usuaris', $usuari);
 *   renderTitolPagina('Usuaris', 'Subtítol opcional');
 *   renderMissatges();
 *   ... contingut ...
 *   renderPeu();
 */

function renderHead(string $titol, string $classeBody = ''): void
{
    enviaCapcaleresSeguretat();
    $nomApp = (string) config('app.nom');
    ?>
<!DOCTYPE html>
<html lang="ca" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titol === $nomApp ? $nomApp : "{$titol} · {$nomApp}") ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏛️</text></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <!-- Síncron i abans del body: aplica el tema abans de pintar (sense parpelleig). -->
    <?php if (config('app.tema_selector', true)): ?>
    <script src="<?= e(asset('js/tema.js')) ?>"></script>
    <?php endif; ?>
</head>
<body class="<?= e($classeBody) ?>">
    <?php
}

/**
 * @param array<string, mixed>|null $usuari
 */
function renderCapcalera(string $titol, ?array $usuari = null): void
{
    renderHead($titol);
    $actual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $menu = array_filter(
        (array) config('menu', []),
        fn ($item) => empty($item['rol']) || teRol($usuari, $item['rol'])
    );
    ?>
    <a class="visually-hidden-focusable app-salta" href="#contingut">Salta al contingut</a>
    <header class="app-capcalera">
        <nav class="navbar navbar-expand-lg navbar-dark container-xxl" aria-label="Menú principal">
            <a class="navbar-brand app-marca" href="index.php">
                <?php renderMarca(); ?>
            </a>
            <?php if ($usuari !== null): ?>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu-principal"
                        aria-controls="menu-principal" aria-expanded="false" aria-label="Obre el menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="menu-principal">
                    <ul class="navbar-nav ms-auto app-nav">
                        <?php foreach ($menu as $item): ?>
                            <li class="nav-item">
                                <a class="nav-link<?= $item['url'] === $actual ? ' active' : '' ?>"
                                   <?= $item['url'] === $actual ? 'aria-current="page"' : '' ?>
                                   href="<?= e($item['url']) ?>"><?= e($item['text']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php renderMenuUsuari($usuari); ?>
                </div>
            <?php endif; ?>
        </nav>
    </header>
    <main id="contingut" class="app-main container-xxl">
    <?php
}

/**
 * Logotip de l'Ajuntament (versió blanca, sobre el degradat vermell) + nom de l'app.
 * Es fa servir a la capçalera i al login. Les altres versions del logotip
 * (color i negatiu) són a assets/img/ per a fons clars o foscos.
 */
function renderMarca(): void
{
    $subtitol = (string) config('app.subtitol', '');
    ?>
    <img src="<?= e(asset('img/logo-ajuntament-blanc.png')) ?>" alt="Ajuntament d'Olot" class="app-logo" width="2480" height="503">
    <span class="app-marca-separador" aria-hidden="true"></span>
    <?php if (config('app.logo_app')): ?>
        <img src="<?= e(asset((string) config('app.logo_app'))) ?>" alt="" class="app-logo-app" width="636" height="548">
    <?php endif; ?>
    <span>
        <span class="app-marca-nom"><?= e(config('app.nom')) ?></span>
        <?php if ($subtitol !== ''): ?><span class="app-marca-sub"><?= e($subtitol) ?></span><?php endif; ?>
    </span>
    <?php
}

/**
 * @param array<string, mixed> $usuari
 */
function renderMenuUsuari(array $usuari): void
{
    // id 0 == l'usuari fictici de config('app.usuari_demo'), no una sessió
    // real (vegeu requereixLogin()): s'hi distingeix perquè no es confongui
    // amb un login de debò.
    $esDemo = (int) ($usuari['id'] ?? 0) === 0;
    ?>
    <div class="dropdown ms-lg-3 mt-2 mt-lg-0">
        <button class="app-usuari dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="app-avatar" aria-hidden="true"><?= e(inicials((string) $usuari['nom'])) ?></span>
            <span class="app-usuari-nom"><?= e($usuari['nom']) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li class="px-3 py-2">
                <div class="fw-semibold small"><?= e($usuari['usuari']) ?></div>
                <div class="mt-1"><?= etiquetaRol((string) $usuari['rol']) ?><?php if ($esDemo): ?> <?= etiqueta('Sense login', 'contorn') ?><?php endif; ?></div>
            </li>
            <?php if (teRol($usuari, 'admin')): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="usuaris.php"><i class="bi bi-people me-2"></i>Usuaris</a></li>
                <li><a class="dropdown-item" href="configuracio.php"><i class="bi bi-gear me-2"></i>Configuració</a></li>
            <?php endif; ?>
            <?php if (config('app.url_manual')): ?>
                <li><a class="dropdown-item" href="<?= e(config('app.url_manual')) ?>" target="_blank" rel="noopener"><i class="bi bi-book me-2"></i>Manual</a></li>
            <?php endif; ?>
            <?php if (config('app.tema_selector', true)): ?>
                <li><hr class="dropdown-divider"></li>
                <li><h6 class="dropdown-header">Tema</h6></li>
                <?php renderOpcionsTema('dropdown-item'); ?>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li>
                <?php if ($esDemo): ?>
                    <a class="dropdown-item" href="login.php"><i class="bi bi-box-arrow-in-right me-2"></i>Inicia sessió</a>
                <?php else: ?>
                    <form method="post" action="logout.php">
                        <?= campCsrf() ?>
                        <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2"></i>Surt</button>
                    </form>
                <?php endif; ?>
            </li>
        </ul>
    </div>
    <?php
}

function renderOpcionsTema(string $classe): void
{
    $opcions = ['light' => ['Clar', 'bi-sun'], 'dark' => ['Fosc', 'bi-moon-stars'], 'auto' => ['Automàtic', 'bi-circle-half']];
    foreach ($opcions as $valor => [$text, $icona]) {
        echo '<li><button type="button" class="' . e($classe) . '" data-tema-valor="' . e($valor) . '" aria-pressed="false">'
            . '<i class="bi ' . e($icona) . ' me-2"></i>' . e($text) . '</button></li>';
    }
}

function renderTitolPagina(string $titol, ?string $subtitol = null): void
{
    ?>
    <div class="titol-pagina">
        <h1><?= e($titol) ?></h1>
        <?php if ($subtitol): ?><p><?= e($subtitol) ?></p><?php endif; ?>
    </div>
    <?php
}

/**
 * Missatges flash (exit, error, alerta, info) com a avisos que es poden tancar.
 */
function renderMissatges(): void
{
    $icones = ['exit' => 'bi-check-circle', 'error' => 'bi-exclamation-octagon', 'alerta' => 'bi-exclamation-triangle', 'info' => 'bi-info-circle'];
    foreach (agafaFlash() as $flash) {
        $tipus = isset($icones[$flash['tipus']]) ? $flash['tipus'] : 'info';
        echo '<div class="avis avis-' . e($tipus) . ' alert alert-dismissible fade show mb-3" role="' . ($tipus === 'error' ? 'alert' : 'status') . '">'
            . '<i class="bi ' . $icones[$tipus] . '" aria-hidden="true"></i><div>' . e($flash['missatge']) . '</div>'
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tanca"></button></div>';
    }
}

/**
 * @param string[] $scripts URLs de scripts extra (p. ex. Chart.js per a les pàgines amb gràfics)
 * @param bool $tanca false = deixa <body> obert perquè la pàgina hi afegeixi els seus <script> en línia
 *                    (cal tancar-lo amb </body></html>); només amb app.csp_inline.
 */
function renderPeu(array $scripts = [], bool $tanca = true): void
{
    ?>
    </main>
    <footer class="app-peu">
        <div class="container-xxl"><small>&copy; <?= date('Y') ?> - Ajuntament d'Olot</small></div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php foreach ($scripts as $script): ?>
        <script src="<?= e($script) ?>"></script>
    <?php endforeach; ?>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    <?php if ($tanca): ?>
</body>
</html>
    <?php endif;
}

/**
 * Scripts per a les pàgines amb gràfics: renderPeu(scriptsGrafics()).
 *
 * @return string[]
 */
function scriptsGrafics(): array
{
    return ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', asset('js/grafics.js')];
}

/**
 * Pàgina d'error completa. No toca la BD: ha de funcionar encara que caigui.
 */
function renderError(int $codi, string $titol, string $missatge): never
{
    if (!headers_sent()) {
        http_response_code($codi);
    }
    renderHead($titol, 'pagina-centrada');
    ?>
    <main class="targeta-centrada card text-center">
        <div class="card-body p-4 p-md-5">
            <div class="estat-buit-icona text-primari mb-3"><i class="bi <?= $codi === 403 ? 'bi-shield-lock' : ($codi === 404 ? 'bi-signpost-split' : 'bi-exclamation-triangle') ?>"></i></div>
            <p class="text-body-secondary small mb-1">Error <?= (int) $codi ?></p>
            <h1 class="h4 fw-bold"><?= e($titol) ?></h1>
            <p class="text-body-secondary"><?= e($missatge) ?></p>
            <a href="index.php" class="btn btn-primary mt-2"><i class="bi bi-house me-1"></i>Torna a l'inici</a>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
    <?php
    exit;
}
