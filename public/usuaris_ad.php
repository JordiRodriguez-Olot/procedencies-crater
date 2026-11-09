<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirAdmin();

$usuari = usuariActual();
$rols = (array) config('rols');

// --- POST: donar d'alta un compte del domini ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $q = (string) ($_POST['q'] ?? '');
    try {
        verificaCsrf();
        $nomUsuari = trim((string) ($_POST['usuari'] ?? ''));
        $rol = (string) ($_POST['rol'] ?? '');
        if (!isset($rols[$rol])) {
            throw new RuntimeException('Rol no vàlid.');
        }
        if (usuariPerNom($nomUsuari) !== null) {
            throw new RuntimeException("«{$nomUsuari}» ja consta a la llista d'usuaris.");
        }
        // Es torna a llegir de l'AD: mai es confia en el nom o el correu que arriben pel formulari.
        $ad = ldapObteUsuari($nomUsuari) ?? throw new RuntimeException("No s'ha trobat «{$nomUsuari}» a l'Active Directory.");

        dbExecuta(
            "INSERT INTO usuaris (nom_usuari, nom, correu, rol, origen, actiu) VALUES (?, ?, ?, ?, 'ad', 1)",
            [$ad['usuari'], $ad['nom'], $ad['correu'], $rol]
        );
        actualitzaDesDeAd(dbUltimId(), $ad);
        auditoria('usuari_creat', "{$ad['usuari']} (AD, {$rol})");
        desaFlash('exit', "S'ha donat d'alta «{$ad['nom']}». Entrarà amb la contrasenya del domini.");
        redirigeix('usuaris.php');
    } catch (PDOException $e) {
        error_log((string) $e);
        desaFlash('error', "No s'ha pogut desar a la base de dades.");
    } catch (RuntimeException $e) {
        desaFlash('error', $e->getMessage());
    }
    redirigeix('usuaris_ad.php?q=' . urlencode($q));
}

// --- GET: cerca ---
$q = trim((string) ($_GET['q'] ?? ''));
$resultats = [];
$errorCerca = null;
if ($q !== '' && ldapActiu()) {
    try {
        $resultats = ldapCerca($q);
    } catch (RuntimeException $e) {
        $errorCerca = $e->getMessage();
    }
}
$existents = array_map('mb_strtolower', array_column(dbFiles('SELECT nom_usuari FROM usuaris'), 'nom_usuari'));

renderCapcalera("Cerca a l'Active Directory", $usuari);
?>
<nav aria-label="Ruta" class="small mb-2"><a href="usuaris.php"><i class="bi bi-arrow-left me-1"></i>Usuaris</a></nav>
<?php
renderTitolPagina("Cerca a l'Active Directory", "Dona d'alta comptes del domini. Entraran amb la seva contrasenya de sempre.");
renderMissatges();
?>

<?php if (!ldapActiu()): ?>
    <div class="avis avis-alerta mb-4"><i class="bi bi-exclamation-triangle"></i>
        <div>L'integració amb l'Active Directory està desactivada. Activa-la a <a href="configuracio.php?pestanya=ad">Configuració → Active Directory</a>.</div>
    </div>
<?php else: ?>
    <form class="card targeta-filtres mb-4" method="get" role="search">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-9 col-lg-6">
                    <label class="form-label" for="q">Nom, usuari o correu</label>
                    <input type="search" class="form-control" id="q" name="q" value="<?= e($q) ?>" minlength="2" required autofocus placeholder="p. ex. vilanova">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Cerca</button>
                </div>
            </div>
        </div>
    </form>

    <?php if ($errorCerca): ?>
        <div class="avis mb-4" role="alert"><i class="bi bi-exclamation-octagon"></i><div><?= e($errorCerca) ?></div></div>
    <?php elseif ($q !== ''): ?>
        <div class="card card-taula">
            <div class="table-responsive">
                <table class="table table-hover taula-app">
                    <thead>
                        <tr><th>Nom</th><th>Usuari</th><th>Adreça electrònica</th><th class="col-accions">Alta</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultats as $r): ?>
                            <tr>
                                <td><?= e($r['nom']) ?></td>
                                <td><code><?= e($r['usuari']) ?></code></td>
                                <td><?= e($r['correu']) ?></td>
                                <td class="col-accions">
                                    <?php if (in_array(mb_strtolower($r['usuari']), $existents, true)): ?>
                                        <?= etiqueta('Ja hi consta', 'exit', 'bi-check-lg') ?>
                                    <?php else: ?>
                                        <form method="post" class="d-flex gap-2 justify-content-end">
                                            <?= campCsrf() ?>
                                            <input type="hidden" name="usuari" value="<?= e($r['usuari']) ?>">
                                            <input type="hidden" name="q" value="<?= e($q) ?>">
                                            <select name="rol" class="form-select form-select-sm w-auto" aria-label="Rol per a <?= e($r['usuari']) ?>">
                                                <?php foreach ($rols as $clau => $text): ?>
                                                    <option value="<?= e($clau) ?>" <?= $clau === 'usuari' ? 'selected' : '' ?>><?= e($text) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-person-plus me-1"></i>Dona d'alta</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($resultats === []): ?>
                            <tr><td colspan="4"><div class="estat-buit">Cap compte del domini coincideix amb «<?= e($q) ?>».</div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer"><?= count($resultats) ?> resultat(s). Es mostren com a màxim 25: afina la cerca si no hi surt qui busques.</div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php renderPeu(); ?>
