<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
require __DIR__ . '/../src/SimpleXlsxReader.php';
require __DIR__ . '/../src/Importer.php';
require __DIR__ . '/../src/ControlQualitat.php';
require __DIR__ . '/../src/Registre.php';

/*
 * Importació mensual en dos passos:
 *  1) Pujar l'Excel  -> es llegeix i es mostra l'informe de validació (no es desa res).
 *  2) Confirmar      -> s'importa (o se substitueix) dins una transacció.
 * El fitxer queda a storage/tmp mentre es revisa; el canal i el nom es guarden a la sessió.
 */

$tmpDir = __DIR__ . '/../storage/tmp';
$pdo = getPDO();
$importer = new Importer($pdo);

// Neteja fitxers temporals abandonats (més d'1 hora, mai confirmats)
foreach (glob($tmpDir . '/*.xlsx') ?: [] as $f) {
    if (is_file($f) && (time() - filemtime($f)) > 3600) @unlink($f);
}

const MESOS = ['', 'Gener', 'Febrer', 'Març', 'Abril', 'Maig', 'Juny', 'Juliol', 'Agost', 'Setembre', 'Octubre', 'Novembre', 'Desembre'];
function nomPeriode(?int $anymes): string
{
    return $anymes ? MESOS[$anymes % 100] . ' ' . intdiv($anymes, 100) : '—';
}

/** "1 fila" / "3 files" */
function files(int $n): string
{
    return formatNombre($n) . ($n === 1 ? ' fila' : ' files');
}

/** Fitxer pendent de confirmar (es valida contra la sessió, mai contra el formulari) */
function pendent(string $token): ?array
{
    $p = $_SESSION['import_pendent'][$token] ?? null;
    $fitxer = __DIR__ . '/../storage/tmp/' . basename($token);
    return ($p && is_file($fitxer)) ? $p + ['fitxer' => $fitxer, 'token' => $token] : null;
}

function oblida(array $p): void
{
    @unlink($p['fitxer']);
    unset($_SESSION['import_pendent'][$p['token']]);
}

$informe = null;   // informe de validació a mostrar
$pend = null;      // fitxer pendent associat
$pas = $_POST['pas'] ?? '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verificaCsrf();
    }

    // ---------- Pas 1: pujar i validar ----------
    if ($pas === 'pujar') {
        $canal = $_POST['canal'] ?? '';
        if (!in_array($canal, ['TAQUILLA', 'ONLINE', 'CGR'], true)) throw new RuntimeException('Canal no vàlid.');
        if (($_FILES['excel']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('No s\'ha pogut pujar el fitxer.');
        if (strtolower(pathinfo((string)$_FILES['excel']['name'], PATHINFO_EXTENSION)) !== 'xlsx') throw new RuntimeException('Cal un fitxer .xlsx.');

        $token = bin2hex(random_bytes(12)) . '.xlsx';
        if (!move_uploaded_file($_FILES['excel']['tmp_name'], $tmpDir . '/' . $token)) throw new RuntimeException('No s\'ha pogut desar el fitxer temporal.');
        $_SESSION['import_pendent'][$token] = ['canal' => $canal, 'nom' => mb_substr(basename((string)$_FILES['excel']['name']), 0, 200), 'anymes' => null];
        $pend = pendent($token);
        try {
            $lectura = $importer->llegir($pend['fitxer']);
        } catch (Throwable $e) {
            oblida($pend);
            throw $e;
        }
        $_SESSION['import_pendent'][$token]['anymes'] = $lectura['anymes'];
        $pend['anymes'] = $lectura['anymes'];
        $informe = (new ControlQualitat($pdo, $importer))->analitzarFitxer($lectura, $canal, $lectura['anymes']);
    }

    // ---------- Pas 1b: triar el mes quan el fitxer no el porta ----------
    if ($pas === 'periode') {
        $pend = pendent((string)($_POST['token'] ?? '')) ?? throw new RuntimeException('El fitxer ja no hi és. Torna\'l a pujar.');
        $anymes = (int)($_POST['anymes'] ?? 0);
        if ($anymes % 100 < 1 || $anymes % 100 > 12 || $anymes < 200001 || $anymes > (int)date('Ym')) throw new RuntimeException('Període no vàlid.');
        $_SESSION['import_pendent'][$pend['token']]['anymes'] = $anymes;
        $pend['anymes'] = $anymes;
        $informe = (new ControlQualitat($pdo, $importer))->analitzarFitxer($importer->llegir($pend['fitxer']), $pend['canal'], $anymes);
    }

    // ---------- Pas 2: confirmar ----------
    if ($pas === 'confirmar') {
        $pend = pendent((string)($_POST['token'] ?? '')) ?? throw new RuntimeException('El fitxer ja no hi és. Torna\'l a pujar.');
        $decisio = $_POST['decisio'] ?? '';
        if ($decisio === 'cancelar') {
            oblida($pend);
            desaFlash('info', 'Importació cancel·lada. No s\'ha modificat cap dada.');
            redirigeix('import.php');
        }
        if (!in_array($decisio, ['importar', 'substituir', 'afegir'], true)) throw new RuntimeException('Acció no vàlida.');
        $ometre = ($_POST['ometre_duplicats'] ?? '') === '1';
        try {
            $res = $importer->importar($pend['fitxer'], $pend['canal'], $pend['nom'],
                $decisio === 'importar' ? 'preguntar' : $decisio, $pend['anymes'], $ometre);
        } catch (ImportDuplicatException) {
            throw new RuntimeException('Mentre revisaves s\'han importat dades d\'aquest mes. Torna a pujar el fitxer per veure-ho.');
        }
        oblida($pend);
        $detall = "{$pend['nom']}: {$res['files']} files, {$res['pax']} pax, " . nomPeriode($res['anymes']) . " ({$pend['canal']})"
            . ($decisio === 'substituir' ? ', substituint les anteriors' : '') . ($decisio === 'afegir' ? ', afegides a les existents' : '')
            . ($res['omeses'] ? ", {$res['omeses']} files repetides omeses" : '');
        Registre::afegir($pdo, 'importacio', $detall);
        desaFlash('exit', 'Importat ' . $detall . '.');
        redirigeix('importacions.php');
    }
} catch (Throwable $e) {
    if ($e instanceof PDOException) error_log('import.php: ' . $e->getMessage());
    desaFlash('error', $e instanceof PDOException ? 'Error de base de dades en importar. No s\'ha desat res.' : $e->getMessage());
    if ($pas === 'confirmar') redirigeix('import.php');
    $informe = null;
}
?>
<?php renderCapcalera('Importar dades', usuariActual()); ?>
<style>
  .informe .bloc { background:var(--app-superficie); border-radius:var(--app-radi); box-shadow:var(--app-ombra); padding:1rem 1.25rem; margin-bottom:1rem; }
  .xifra { font-size:1.6rem; font-weight:700; line-height:1.1; }
  .xifra-etiqueta { font-size:.78rem; color:var(--app-text-suau); text-transform:uppercase; letter-spacing:.3px; }
  .control { border-left:4px solid var(--app-vora); padding:.5rem .75rem; margin-bottom:.5rem; background:var(--app-fons); border-radius:0 .4rem .4rem 0; }
  .control.n-avis { border-left-color:#d97706; }
  .control.n-error { border-left-color:#dc2626; }
  .control.n-info { border-left-color:#94a3b8; }
  .control summary { cursor:pointer; list-style:none; display:flex; justify-content:space-between; gap:1rem; align-items:baseline; }
  .control summary::-webkit-details-marker { display:none; }
  .control ul { margin:.5rem 0 0; padding-left:1.1rem; font-size:.85rem; }
  .estat-ok { color:var(--app-exit); }
  .desv-pos { color:var(--app-exit); } .desv-neg { color:#b91c1c; }
</style>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div class="titol-pagina m-0"><h1>Importar Excel de Clorian</h1></div>
  <a href="importacions.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar3"></i> Control de càrrega i historial</a>
</div>

<?php renderMissatges(); ?>

<?php if (!$informe): ?>
  <div style="max-width:640px">
    <form method="post" enctype="multipart/form-data" class="card p-4 shadow-sm">
      <?= campCsrf() ?>
      <input type="hidden" name="pas" value="pujar">
      <div class="mb-3">
        <label class="form-label" for="canal">Canal</label>
        <select name="canal" id="canal" class="form-select" required>
          <option value="TAQUILLA">Taquilla</option>
          <option value="ONLINE">Online</option>
          <option value="CGR">CGR</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label" for="excel">Fitxer Excel (.xlsx)</label>
        <input type="file" name="excel" id="excel" accept=".xlsx" class="form-control" required>
      </div>
      <div><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Revisar el fitxer</button></div>
      <p class="text-muted mt-3 mb-0" style="font-size:.85rem">
        Primer es revisa el fitxer i se't mostra un informe (files, pax, codis postals, files repetides...).
        No es desa res fins que no confirmis.
      </p>
    </form>
    <p class="text-muted mt-3" style="font-size:.85rem">
      Tens un Excel amb <b>tot l'històric consolidat</b> (tots els canals i períodes junts)?
      Fes servir la <a href="import_general.php">importació general</a>.
    </p>
  </div>

<?php else:
  $p = $informe['periode'];
  $existeix = $p && $p['existents']['files'] > 0;
  $desviat = $p && $p['desviacio'] !== null && abs($p['desviacio']) > ControlQualitat::DESVIACIO;
  $avisos = count(array_filter($informe['controls'], fn($c) => $c['nivell'] !== 'info'))
          + ($informe['duplicats']['grups'] ? 1 : 0) + count($informe['repetits'])
          + ($informe['multi_mes'] ? 1 : 0) + ($desviat ? 1 : 0);
?>
<div class="informe">
  <div class="bloc">
    <div class="d-flex justify-content-between flex-wrap gap-3 align-items-start">
      <div>
        <div class="xifra-etiqueta">Fitxer</div>
        <div class="fw-semibold"><?= e($pend['nom']) ?></div>
        <div class="text-muted" style="font-size:.85rem">Canal <b><?= e($informe['canal']) ?></b> ·
          <?php if ($informe['detectat']): ?>
            període <b><?= e(nomPeriode($informe['anymes'])) ?></b> (<?= e($informe['data_inici']) ?> a <?= e($informe['data_fi']) ?>)
          <?php elseif ($informe['anymes']): ?>
            període <b><?= e(nomPeriode($informe['anymes'])) ?></b> (triat a mà)
          <?php else: ?>
            <span class="text-danger fw-semibold">el fitxer no diu de quin mes és</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-flex gap-4 flex-wrap">
        <div><div class="xifra"><?= formatNombre($informe['files']) ?></div><div class="xifra-etiqueta">files</div></div>
        <div><div class="xifra"><?= formatNombre($informe['pax']) ?></div><div class="xifra-etiqueta">pax</div></div>
        <div><div class="xifra <?= $avisos ? 'text-warning' : 'estat-ok' ?>"><?= $avisos ?></div><div class="xifra-etiqueta">avisos</div></div>
      </div>
    </div>
    <?php if ($informe['ignorades']): ?>
      <div class="text-muted mt-2" style="font-size:.85rem"><?= files($informe['ignorades']) ?> amb pax 0 o buit no s'importaran.</div>
    <?php endif; ?>
  </div>

  <?php if (!$informe['anymes']): ?>
    <div class="bloc">
      <h2 class="h6">De quin mes són aquestes dades?</h2>
      <p class="text-muted" style="font-size:.85rem">No hem trobat la línia «Fecha visita» a les primeres files de l'Excel. Tria el mes per continuar.</p>
      <div class="d-flex gap-2 flex-wrap align-items-end">
        <form method="post" class="d-flex gap-2">
          <?= campCsrf() ?>
          <input type="hidden" name="pas" value="periode">
          <input type="hidden" name="token" value="<?= e($pend['token']) ?>">
          <select name="anymes" class="form-select w-auto" required aria-label="Mes de les dades">
            <?php for ($i = 1; $i <= 24; $i++): $am = (int)date('Ym', strtotime(date('Y-m-01') . " -$i month")); ?>
              <option value="<?= $am ?>"><?= e(nomPeriode($am)) ?></option>
            <?php endfor; ?>
          </select>
          <button class="btn btn-primary">Continuar</button>
        </form>
        <form method="post">
          <?= campCsrf() ?>
          <input type="hidden" name="pas" value="confirmar">
          <input type="hidden" name="token" value="<?= e($pend['token']) ?>">
          <button name="decisio" value="cancelar" class="btn btn-outline-danger">Cancel·lar</button>
        </form>
      </div>
    </div>
  <?php else: ?>

    <div class="bloc">
      <h2 class="h6 mb-3">El mes en context</h2>
      <?php if ($informe['multi_mes']): ?>
        <div class="control n-avis"><b>El fitxer abasta més d'un mes</b> (<?= e($informe['data_inici']) ?> a <?= e($informe['data_fi']) ?>). Tot es desarà a <?= e(nomPeriode($informe['anymes'])) ?>. L'habitual és un fitxer per mes.</div>
      <?php endif; ?>
      <?php foreach ($informe['repetits'] as $r): ?>
        <div class="control n-error"><b>Aquest contingut ja s'havia importat</b>: «<?= e($r['nom_fitxer']) ?>» (<?= e(nomPeriode($r['anymes'] ? (int)$r['anymes'] : null)) ?>, importat el <?= e(formatData($r['data_importacio'], true)) ?>). Probablement és el mateix fitxer.</div>
      <?php endforeach; ?>
      <?php if ($existeix): ?>
        <div class="control n-avis"><b>Ja hi ha dades d'aquest mes i canal</b>: <?= files($p['existents']['files']) ?>, <?= formatNombre($p['existents']['pax']) ?> pax. Hauràs de triar si les substitueixes o hi afegeixes aquestes.</div>
      <?php endif; ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0" style="max-width:560px">
          <thead><tr><th>Comparat amb</th><th class="text-end">Pax</th><th class="text-end">Aquest fitxer</th></tr></thead>
          <tbody>
            <?php foreach ([['Mes anterior', $p['mes_anterior']], ['Mateix mes de l\'any anterior', $p['any_anterior']]] as [$etiq, $ref]):
              $d = $ref['pax'] > 0 ? ($informe['pax'] - $ref['pax']) / $ref['pax'] : null; ?>
              <tr>
                <td><?= e($etiq) ?> <span class="text-muted">(<?= e(nomPeriode($ref['anymes'])) ?>)</span></td>
                <td class="text-end"><?= $ref['pax'] ? formatNombre($ref['pax']) : '<span class="text-muted">sense dades</span>' ?></td>
                <td class="text-end <?= $d === null ? '' : ($d >= 0 ? 'desv-pos' : 'desv-neg') ?>"><?= $d === null ? '—' : sprintf('%+d%%', round($d * 100)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($desviat): ?>
        <div class="control n-avis mt-2 mb-0"><b>Molt diferent del mateix mes de l'any passat</b> (<?= sprintf('%+d%%', round($p['desviacio'] * 100)) ?>). Pot ser real, però comprova que el fitxer sigui del mes i canal correctes i que no estigui incomplet.</div>
      <?php endif; ?>
    </div>

    <div class="bloc">
      <h2 class="h6 mb-3">Revisió de les dades</h2>
      <?php if (!$informe['controls'] && !$informe['duplicats']['grups'] && !$informe['productes_nous'] && !$informe['productes_sense_grup']): ?>
        <p class="estat-ok mb-0"><i class="bi bi-check-circle"></i> No s'ha trobat cap problema.</p>
      <?php endif; ?>

      <?php if ($informe['duplicats']['grups']): $dup = $informe['duplicats']; ?>
        <details class="control n-avis">
          <summary><span><b>Files repetides dins el fitxer</b> · <?= $dup['grups'] ?> grups, <?= files($dup['sobrants']) ?> de més (<?= formatNombre($dup['pax_sobrants']) ?> pax)</span><span class="text-muted" style="font-size:.8rem">veure</span></summary>
          <div class="text-muted mt-1" style="font-size:.85rem">
            <?= $informe['canal'] === 'TAQUILLA'
                ? 'El mateix país, CP, producte i pax surt més d\'una vegada. A Taquilla cada fila ja és el total del mes, així que no s\'hauria de repetir.'
                : 'Mateix nom, correu, producte, CP i pax. Pot ser una reserva duplicada o dues reserves reals de la mateixa persona en dies diferents.' ?>
          </div>
          <ul><?php foreach ($dup['mostres'] as $m): ?><li><?= e($m['text']) ?></li><?php endforeach; ?>
            <?php if ($dup['mes_mostres']): ?><li class="text-muted">i <?= $dup['mes_mostres'] ?> grups més</li><?php endif; ?></ul>
        </details>
      <?php endif; ?>

      <?php foreach ($informe['controls'] as $c): ?>
        <details class="control n-<?= e($c['nivell']) ?>">
          <summary><span><b><?= e($c['titol']) ?></b> · <?= files($c['files']) ?><?= $c['pax'] ? ', ' . formatNombre($c['pax']) . ' pax' : '' ?></span><?php if ($c['mostres']): ?><span class="text-muted" style="font-size:.8rem">veure</span><?php endif; ?></summary>
          <div class="text-muted mt-1" style="font-size:.85rem"><?= e($c['explicacio']) ?></div>
          <?php if ($c['mostres']): ?>
            <ul><?php foreach ($c['mostres'] as $m): ?><li><?= e($m['text']) ?> <span class="text-muted">— <?= files($m['files']) ?>, <?= formatNombre($m['pax']) ?> pax</span></li><?php endforeach; ?>
              <?php if ($c['mes_mostres']): ?><li class="text-muted">i <?= $c['mes_mostres'] ?> més</li><?php endif; ?></ul>
          <?php endif; ?>
        </details>
      <?php endforeach; ?>

      <?php if ($informe['productes_nous'] || $informe['productes_sense_grup']): ?>
        <div class="control n-info">
          <?php if ($informe['productes_nous']): ?><b>Productes nous</b>: <?= e(implode(', ', $informe['productes_nous'])) ?>.<br><?php endif; ?>
          <?php if ($informe['productes_sense_grup']): ?><b>Sense grup assignat</b>: <?= e(implode(', ', $informe['productes_sense_grup'])) ?>. Els pots agrupar després a <a href="productes.php">Productes</a>.<?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($informe['controls']): ?>
        <p class="text-muted mb-0 mt-2" style="font-size:.82rem">Els avisos no impedeixen importar: després els trobaràs a <a href="errors.php">Gestió d'errors</a> per corregir-los.</p>
      <?php endif; ?>
    </div>

    <form method="post" class="bloc">
      <?= campCsrf() ?>
      <input type="hidden" name="pas" value="confirmar">
      <input type="hidden" name="token" value="<?= e($pend['token']) ?>">
      <?php if ($informe['duplicats']['sobrants']): ?>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="ometre_duplicats" value="1" id="ometre">
          <label class="form-check-label" for="ometre">Ometre <?= $informe['duplicats']['sobrants'] === 1 ? 'la fila repetida' : 'les ' . $informe['duplicats']['sobrants'] . ' files repetides' ?> (se'n deixa només una de cada grup)</label>
        </div>
      <?php endif; ?>
      <div class="d-flex gap-2 flex-wrap">
        <?php if ($existeix): ?>
          <button name="decisio" value="substituir" class="btn btn-primary"><i class="bi bi-arrow-repeat"></i> Substituir les dades d'aquest mes</button>
          <button name="decisio" value="afegir" class="btn btn-outline-secondary"><i class="bi bi-plus-lg"></i> Afegir-les (pot duplicar)</button>
        <?php else: ?>
          <button name="decisio" value="importar" class="btn btn-primary"><i class="bi bi-check-lg"></i> Importar <?= formatNombre($informe['files']) ?> files</button>
        <?php endif; ?>
        <button name="decisio" value="cancelar" class="btn btn-outline-danger"><i class="bi bi-x-lg"></i> Cancel·lar</button>
      </div>
      <?php if ($existeix): ?>
        <p class="text-muted mt-2 mb-0" style="font-size:.82rem">«Substituir» esborra només les entrades importades d'aquest canal i mes (les manuals no es toquen).</p>
      <?php endif; ?>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php renderPeu(); ?>
