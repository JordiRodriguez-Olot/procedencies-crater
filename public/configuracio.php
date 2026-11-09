<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirAdmin();

$usuari = usuariActual();
$pestanyes = ['ad'];

// --- POST: desar / provar (patró Post/Redirect/Get) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accio = (string) ($_POST['accio'] ?? '');
    try {
        verificaCsrf();
        switch ($accio) {
            case 'desa':
                $ad = validaConfiguracioAd($_POST);
                dbTransaccio(function () use ($ad, $usuari) {
                    foreach ($ad as $clau => $valor) {
                        configuracioDesa("ad.{$clau}", $valor, false, $usuari['usuari']);
                    }
                    configuracioDesaSecret('ad.password_consulta', (string) ($_POST['ad_password_consulta'] ?? ''), $usuari['usuari']);
                });
                auditoria('configuracio_desa', 'Active Directory');
                desaFlash('exit', "S'ha desat la configuració.");
                break;

            case 'prova_ad':
                $_SESSION['prova_ad'] = ldapProvaConnexio();
                auditoria('configuracio_prova_ad');
                break;

            default:
                throw new RuntimeException('Acció desconeguda.');
        }
    } catch (PDOException $e) {
        error_log((string) $e);
        desaFlash('error', "No s'ha pogut desar a la base de dades.");
    } catch (RuntimeException $e) {
        desaFlash('error', $e->getMessage());
    }
    redirigeix('configuracio.php');
}

function sensesSaltsDeLinia(string $valor, string $camp): string
{
    if (preg_match('/[\r\n\0]/', $valor)) {
        throw new RuntimeException("El camp «{$camp}» no pot contenir salts de línia.");
    }

    return trim($valor);
}

/**
 * Els atributs i el filtre van dins dels filtres LDAP: es validen amb
 * llista blanca perquè ningú hi pugui injectar condicions.
 *
 * @param array<string, mixed> $post
 * @return array<string, string>
 */
function validaConfiguracioAd(array $post): array
{
    $v = [
        'actiu'           => isset($post['ad_actiu']) ? '1' : '0',
        'servidor'        => trim((string) ($post['ad_servidor'] ?? '')),
        'port'            => trim((string) ($post['ad_port'] ?? '')),
        'seguretat'       => (string) ($post['ad_seguretat'] ?? ''),
        'verifica_cert'   => isset($post['ad_verifica_cert']) ? '1' : '0',
        'domini'          => trim((string) ($post['ad_domini'] ?? '')),
        'base_dn'         => sensesSaltsDeLinia((string) ($post['ad_base_dn'] ?? ''), 'DN base'),
        'compte_consulta' => sensesSaltsDeLinia((string) ($post['ad_compte_consulta'] ?? ''), 'Compte de consulta'),
        'filtre_usuaris'  => sensesSaltsDeLinia((string) ($post['ad_filtre_usuaris'] ?? ''), "Filtre d'usuaris"),
        'atribut_usuari'  => trim((string) ($post['ad_atribut_usuari'] ?? '')),
        'atribut_nom'     => trim((string) ($post['ad_atribut_nom'] ?? '')),
        'atribut_correu'  => trim((string) ($post['ad_atribut_correu'] ?? '')),
        'grup_admins'     => sensesSaltsDeLinia((string) ($post['ad_grup_admins'] ?? ''), "Grup d'administradors"),
    ];
    if ($v['servidor'] !== '' && !esNomServidor($v['servidor'])) {
        throw new RuntimeException("El servidor de l'AD no és un nom o una adreça IP vàlids.");
    }
    if ($v['port'] !== '' && (!ctype_digit($v['port']) || (int) $v['port'] < 1 || (int) $v['port'] > 65535)) {
        throw new RuntimeException("El port de l'AD ha de ser un número entre 1 i 65535.");
    }
    if (!in_array($v['seguretat'], ['cap', 'starttls', 'ldaps'], true)) {
        throw new RuntimeException('Opció de seguretat LDAP no vàlida.');
    }
    if ($v['domini'] !== '' && !esNomServidor($v['domini'])) {
        throw new RuntimeException('El domini (sufix UPN) no és vàlid.');
    }
    foreach (['atribut_usuari', 'atribut_nom', 'atribut_correu'] as $camp) {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9\-]{0,63}$/', $v[$camp])) {
            throw new RuntimeException('Els atributs LDAP només poden tenir lletres, xifres i guions.');
        }
    }
    if ($v['filtre_usuaris'] !== '' && !filtreLdapEquilibrat($v['filtre_usuaris'])) {
        throw new RuntimeException("El filtre d'usuaris ha de ser una o més condicions entre parèntesis, p. ex. (objectClass=user).");
    }
    if ($v['actiu'] === '1' && ($v['servidor'] === '' || $v['base_dn'] === '')) {
        throw new RuntimeException("Per activar l'Active Directory cal indicar almenys el servidor i el DN base.");
    }

    return $v;
}

// --- GET ---
$pestanya = 'ad';
$ad = ldapConfig(false);
$provaAd = $_SESSION['prova_ad'] ?? null;
unset($_SESSION['prova_ad']);
$teClau = true;
try {
    clauXifratge();
} catch (RuntimeException) {
    $teClau = false;
}

$marcat = fn (?string $valor) => $valor === '1' ? 'checked' : '';
$triat = fn (?string $valor, string $opcio) => $valor === $opcio ? 'selected' : '';
$placeholderSecret = fn (?string $valor) => $valor !== '' && $valor !== null ? '•••••••• (sense canvis)' : '';

renderCapcalera('Configuració', $usuari);
renderTitolPagina('Configuració', 'Integració amb l\'Active Directory: login amb el compte del domini i alta d\'usuaris.');
renderMissatges();
?>

<?php if (!$teClau): ?>
    <div class="avis avis-alerta mb-3"><i class="bi bi-key"></i>
        <div>Falta la clau de xifratge a <code>config/local.php</code> (<code>clau_xifratge</code>): sense ella no es poden desar contrasenyes. Vegeu <code>docs/SEGURETAT.md</code>.</div>
    </div>
<?php endif; ?>

<!-- Formulari únic: els camps s'hi associen amb form="form-config". -->
<form id="form-config" method="post">
    <?= campCsrf() ?>
    <input type="hidden" name="accio" value="desa">
    <input type="hidden" name="pestanya" id="pestanya-activa" value="<?= e($pestanya) ?>">
</form>

<div class="tab-content">
    <!-- ============ Active Directory ============ -->
    <div class="tab-pane fade<?= $pestanya === 'ad' ? ' show active' : '' ?>" id="pane-ad" role="tabpanel" aria-labelledby="tab-ad" tabindex="0">
        <div class="card mb-3">
            <div class="card-body p-4">
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" role="switch" id="ad_actiu" name="ad_actiu" form="form-config" <?= $marcat($ad['actiu']) ?>>
                    <label class="form-check-label" for="ad_actiu">Activa l'autenticació i la importació des de l'Active Directory</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="ad_servidor">Servidor</label>
                        <input type="text" class="form-control" id="ad_servidor" name="ad_servidor" form="form-config" value="<?= e($ad['servidor']) ?>" placeholder="dc01.domini.local">
                    </div>
                    <div class="col-md-6 col-xl-2">
                        <label class="form-label" for="ad_port">Port</label>
                        <input type="number" class="form-control" id="ad_port" name="ad_port" form="form-config" value="<?= e($ad['port']) ?>" min="1" max="65535">
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="ad_seguretat">Seguretat</label>
                        <select class="form-select" id="ad_seguretat" name="ad_seguretat" form="form-config">
                            <option value="starttls" <?= $triat($ad['seguretat'], 'starttls') ?>>STARTTLS (port 389)</option>
                            <option value="ldaps" <?= $triat($ad['seguretat'], 'ldaps') ?>>LDAPS (port 636)</option>
                            <option value="cap" <?= $triat($ad['seguretat'], 'cap') ?>>Cap (port 389, no recomanat)</option>
                        </select>
                    </div>
                    <div class="col-md-6 col-xl-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="ad_verifica_cert" name="ad_verifica_cert" form="form-config" <?= $marcat($ad['verifica_cert']) ?>>
                            <label class="form-check-label" for="ad_verifica_cert">Verifica el certificat</label>
                        </div>
                    </div>

                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="ad_domini">Domini (sufix UPN)</label>
                        <input type="text" class="form-control" id="ad_domini" name="ad_domini" form="form-config" value="<?= e($ad['domini']) ?>" placeholder="domini.local">
                        <div class="form-text">S'afegeix a l'usuari en iniciar sessió: <code>usuari@domini</code>.</div>
                    </div>
                    <div class="col-md-6 col-xl-8">
                        <label class="form-label" for="ad_base_dn">DN base de cerca</label>
                        <input type="text" class="form-control" id="ad_base_dn" name="ad_base_dn" form="form-config" value="<?= e($ad['base_dn']) ?>" placeholder="OU=...,DC=...,DC=local">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="ad_compte_consulta">Compte de consulta</label>
                        <input type="text" class="form-control" id="ad_compte_consulta" name="ad_compte_consulta" form="form-config" value="<?= e($ad['compte_consulta']) ?>" autocomplete="off">
                        <div class="form-text">Compte de només lectura per cercar i importar usuaris.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ad_password_consulta">Contrasenya del compte de consulta</label>
                        <input type="password" class="form-control" id="ad_password_consulta" name="ad_password_consulta" form="form-config"
                               placeholder="<?= e($placeholderSecret($ad['password_consulta'] ?? '')) ?>" autocomplete="new-password">
                        <div class="form-text">Es desa xifrada. En blanc = conserva l'actual.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="ad_filtre_usuaris">Filtre d'usuaris</label>
                        <input type="text" class="form-control font-monospace" id="ad_filtre_usuaris" name="ad_filtre_usuaris" form="form-config" value="<?= e($ad['filtre_usuaris']) ?>">
                        <div class="form-text">Condicions internes del filtre LDAP; l'aplicació les embolcalla amb <code>(&amp; … )</code>.</div>
                    </div>

                    <div class="col-md-4 col-xl-2">
                        <label class="form-label" for="ad_atribut_usuari">Atribut d'usuari</label>
                        <input type="text" class="form-control" id="ad_atribut_usuari" name="ad_atribut_usuari" form="form-config" value="<?= e($ad['atribut_usuari']) ?>">
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <label class="form-label" for="ad_atribut_nom">Atribut del nom</label>
                        <input type="text" class="form-control" id="ad_atribut_nom" name="ad_atribut_nom" form="form-config" value="<?= e($ad['atribut_nom']) ?>">
                    </div>
                    <div class="col-md-4 col-xl-2">
                        <label class="form-label" for="ad_atribut_correu">Atribut del correu</label>
                        <input type="text" class="form-control" id="ad_atribut_correu" name="ad_atribut_correu" form="form-config" value="<?= e($ad['atribut_correu']) ?>">
                    </div>
                    <div class="col-12 col-xl-6 d-flex align-items-end">
                        <div class="avis w-100 mb-0"><i class="bi bi-info-circle"></i>
                            <div>Els comptes del domini no es creen sols: cal donar-los d'alta des de <a href="usuaris_ad.php">Cerca a l'Active Directory</a>.</div>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="ad_grup_admins">Grup d'administradors (DN complet)</label>
                        <input type="text" class="form-control" id="ad_grup_admins" name="ad_grup_admins" form="form-config" value="<?= e($ad['grup_admins']) ?>" placeholder="CN=App-Admins,OU=Grups,DC=domini,DC=local">
                        <div class="form-text">Els membres d'aquest grup reben el rol d'administrador automàticament (i el perden si en surten). Deixa-ho en blanc per assignar els rols manualment.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-4">
            <button type="submit" form="form-config" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Desa la configuració</button>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <h2 class="titol-seccio">Prova de connexió</h2>
                <p class="small text-suau">Comprova la connexió, el compte de consulta i el filtre amb els valors ja desats.</p>
                <?php if ($provaAd !== null): ?>
                    <ul class="list-unstyled small mb-3">
                        <?php foreach ($provaAd as $pas): ?>
                            <li class="mb-1 <?= $pas['ok'] ? 'text-success' : 'text-danger' ?>">
                                <i class="bi <?= $pas['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> me-1"></i><?= e($pas['text']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <form method="post">
                    <?= campCsrf() ?>
                    <input type="hidden" name="accio" value="prova_ad">
                    <input type="hidden" name="pestanya" value="ad">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-plug me-1"></i>Prova la connexió</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php renderPeu(); ?>
