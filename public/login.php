<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::iniciar();

// Si ja hi ha sessió, no cal tornar a mostrar el login
if (Auth::esLogat()) {
    redirigeix('index.php');
}

$error = null;
$nomUsuari = '';
if (isset($_GET['expirat'])) {
    $error = 'La sessió ha caducat per inactivitat. Torna a entrar.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomUsuari = trim((string) ($_POST['usuari'] ?? ''));
    $contrasenya = (string) ($_POST['contrasenya'] ?? '');
    $error = null;
    try {
        verificaCsrf();
        if ($nomUsuari === '' || $contrasenya === '') {
            $error = 'Cal omplir usuari i contrasenya.';
        } else {
            $resultat = Auth::intentarLogin(getPDO(), $nomUsuari, $contrasenya);
            if ($resultat['ok']) {
                // Evitar "open redirect": només acceptem rutes internes relatives
                $desti = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php');
                // REQUEST_URI porta el camí complet (p. ex. /crater_app/public/index.php si
                // l'app penja d'una subcarpeta): ens quedem només amb pàgina + query.
                $q = parse_url($desti, PHP_URL_QUERY);
                $desti = basename((string) parse_url($desti, PHP_URL_PATH)) . ($q ? '?' . $q : '');
                if (!preg_match('#^[a-zA-Z0-9_\-\./]+\.php(\?.*)?$#', ltrim($desti, '/'))) {
                    $desti = 'index.php';
                }
                redirigeix(ltrim($desti, '/'));
            }
            $error = $resultat['error'];
        }
    } catch (PDOException $e) {
        // Abans que RuntimeException (PDOException n'hereta): mai ha d'arribar
        // a l'usuari un missatge amb SQL o dades de connexió.
        error_log((string) $e);
        $error = "No s'ha pogut iniciar la sessió. Avisa el servei d'informàtica.";
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$redirect = (string) ($_GET['redirect'] ?? $_POST['redirect'] ?? '');

renderHead('Inicia sessió', 'pagina-centrada');
?>
    <main class="w-100 d-flex flex-column align-items-center">
        <div class="login-marca">
            <?php renderMarca(); ?>
        </div>

        <div class="card targeta-centrada">
            <div class="card-body p-4">
                <h1 class="h5 fw-bold mb-1">Inicia sessió</h1>
                <p class="text-suau small mb-3">Amb el teu usuari de domini o el compte local que t'han assignat.</p>

                <?php if ($error): ?>
                    <div class="avis mb-3" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div><?= e($error) ?></div></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= campCsrf() ?>
                    <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
                    <div class="mb-3">
                        <label class="form-label" for="usuari">Usuari</label>
                        <input type="text" class="form-control" id="usuari" name="usuari" value="<?= e($nomUsuari) ?>"
                               autocomplete="username" autocapitalize="off" autocorrect="off" spellcheck="false" required <?= $nomUsuari === '' ? 'autofocus' : '' ?>>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="contrasenya">Contrasenya</label>
                        <input type="password" class="form-control" id="contrasenya" name="contrasenya"
                               autocomplete="current-password" required <?= $nomUsuari !== '' ? 'autofocus' : '' ?>>
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-box-arrow-in-right me-1"></i>Entra</button>
                </form>
            </div>
        </div>
        <p class="text-suau small mt-3 mb-0">&copy; <?= date('Y') ?> Ajuntament d'Olot</p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
