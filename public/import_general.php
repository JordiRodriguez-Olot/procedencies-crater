<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
require __DIR__ . '/../src/SimpleXlsxReader.php';
require __DIR__ . '/../src/ImportadorGeneral.php';

$missatges = [];
$previsualitzacio = null;
$tmpDir = __DIR__ . '/../storage/tmp';

foreach (glob($tmpDir . '/*.xlsx') ?: [] as $f) {
    if (is_file($f) && (time() - filemtime($f)) > 3600) @unlink($f);
}

function nomMes(int $m): string {
    return ['','Gen','Feb','Mar','Abr','Mai','Jun','Jul','Ago','Set','Oct','Nov','Des'][$m] ?? (string)$m;
}
function periode(int $anymes): string {
    return nomMes($anymes % 100) . ' ' . intdiv($anymes, 100);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel']) && ($_POST['pas'] ?? '') === 'pujar') {
    if ($_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        $missatges[] = ['error', 'Error pujant el fitxer.'];
    } else {
        $nomOriginal = $_FILES['excel']['name'];
        $token = bin2hex(random_bytes(8)) . '.xlsx';
        $desti = $tmpDir . '/' . $token;
        move_uploaded_file($_FILES['excel']['tmp_name'], $desti);

        try {
            $importador = new ImportadorGeneral(getPDO());
            $resum = $importador->previsualitzar($desti);
            $previsualitzacio = ['token' => $token, 'nom_original' => $nomOriginal, 'resum' => $resum];
        } catch (Throwable $e) {
            @unlink($desti);
            $missatges[] = ['error', 'Error llegint el fitxer: ' . $e->getMessage()];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pas'] ?? '') === 'confirmar') {
    $token = basename($_POST['token'] ?? '');
    $nomOriginal = $_POST['nom_original'] ?? 'fitxer.xlsx';
    $decisio = $_POST['decisio'] ?? '';
    $fitxer = $tmpDir . '/' . $token;

    if ($decisio === 'cancelar') {
        @unlink($fitxer);
        $missatges[] = ['error', 'Importació cancel·lada. No s\'ha modificat cap dada.'];
    } elseif (in_array($decisio, ['saltar_existents', 'substituir_existents', 'afegir_tot'], true) && is_file($fitxer)) {
        try {
            $importador = new ImportadorGeneral(getPDO());
            $res = $importador->importar($fitxer, $nomOriginal, $decisio);
            $missatges[] = ['success', "Importades {$res['files_importades']} files de {$res['files_llegides']} llegides, repartides en {$res['periodes_importats']} períodes/canals (n'hi ha {$res['periodes_saltats']} saltats)."];
        } catch (Throwable $e) {
            $missatges[] = ['error', 'Error important: ' . $e->getMessage()];
        } finally {
            @unlink($fitxer);
        }
    } else {
        $missatges[] = ['error', 'No s\'ha trobat el fitxer temporal. Torna a pujar l\'Excel.'];
    }
}
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Importació general · Espai Cràter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; }
  body { font-family:'Inter',system-ui,sans-serif; background:#f5f3f0; }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .avis { background:#fff8e1; border-left:4px solid var(--ember); border-radius:8px; padding:1rem; }
  table.taula-resum { font-size:.85rem; }
  table.taula-resum td.ja-existeix { color:#b8860b; font-weight:600; }
</style>
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:820px">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Importació general (fitxer consolidat)</h1>
    <div class="d-flex gap-2">
      <a href="import.php" class="btn btn-outline-secondary btn-sm">Importació normal (un canal/mes)</a>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Dashboard</a>
    </div>
  </div>

  <p class="text-muted" style="font-size:.9rem">
    Fes servir aquesta opció quan tinguis un Excel <b>consolidat</b> amb tots els canals i períodes
    ja units en una sola taula (per exemple, un full "Dades completades" amb columnes
    pais/cp/pax/ANYMES/NomProv/Comarca/ORIGEN/CANAL/GRUP-ORIGEN). Per a Excels normals de Clorian
    (un canal, un mes), fes servir la <a href="import.php">importació normal</a>.
  </p>

  <?php foreach ($missatges as [$tipus, $text]): ?>
    <div class="alert alert-<?= $tipus === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars($text) ?></div>
  <?php endforeach; ?>

  <?php if ($previsualitzacio): ?>
    <?php
      $resum = $previsualitzacio['resum'];
      $totalFiles = array_sum(array_column($resum, 'files'));
      $totalPax = array_sum(array_column($resum, 'pax'));
      $ambExistents = array_filter($resum, fn($r) => $r['existents_files'] > 0);
    ?>
    <div class="avis mb-4">
      <h5>📄 <?= htmlspecialchars($previsualitzacio['nom_original']) ?></h5>
      <p class="mb-2">
        S'han trobat <b><?= count($resum) ?></b> combinacions de canal+període,
        amb un total de <b><?= number_format($totalFiles, 0, ',', '.') ?> files</b>
        (<b><?= number_format($totalPax, 0, ',', '.') ?> pax</b>).
        <?php if ($ambExistents): ?>
          <span class="text-warning fw-semibold"><?= count($ambExistents) ?> d'aquests períodes ja tenen dades importades.</span>
        <?php else: ?>
          Cap d'aquests períodes té dades prèvies — es pot importar tot amb seguretat.
        <?php endif; ?>
      </p>

      <div class="table-responsive mb-3" style="max-height:320px; overflow:auto">
        <table class="table table-sm taula-resum mb-0">
          <thead><tr><th>Període</th><th>Canal</th><th class="text-end">Files noves</th><th class="text-end">Pax nous</th><th>Ja existent?</th></tr></thead>
          <tbody>
          <?php foreach ($resum as $r): ?>
            <tr>
              <td><?= periode((int)$r['anymes']) ?></td>
              <td><?= htmlspecialchars($r['canal']) ?></td>
              <td class="text-end"><?= number_format($r['files'], 0, ',', '.') ?></td>
              <td class="text-end"><?= number_format($r['pax'], 0, ',', '.') ?></td>
              <td class="<?= $r['existents_files'] > 0 ? 'ja-existeix' : '' ?>">
                <?= $r['existents_files'] > 0 ? '⚠ ' . number_format($r['existents_files'],0,',','.') . ' files existents' : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <p class="mb-2">Què vols fer amb els períodes que ja tenen dades?</p>
      <form method="post" class="d-flex gap-2 flex-wrap">
        <input type="hidden" name="pas" value="confirmar">
        <input type="hidden" name="token" value="<?= htmlspecialchars($previsualitzacio['token']) ?>">
        <input type="hidden" name="nom_original" value="<?= htmlspecialchars($previsualitzacio['nom_original']) ?>">
        <button type="submit" name="decisio" value="saltar_existents" class="btn btn-lava btn-sm">
          ✓ Importar només els períodes nous (recomanat)
        </button>
        <button type="submit" name="decisio" value="substituir_existents" class="btn btn-outline-secondary btn-sm">
          🔁 Substituir també els que ja existeixen
        </button>
        <button type="submit" name="decisio" value="afegir_tot" class="btn btn-outline-secondary btn-sm">
          ➕ Afegir-ho tot igualment (pot duplicar)
        </button>
        <button type="submit" name="decisio" value="cancelar" class="btn btn-outline-danger btn-sm">
          ✕ Cancel·lar
        </button>
      </form>
      <p class="mt-2 mb-0 text-muted" style="font-size:.8rem">
        Nota RGPD: aquest format consolidat no porta noms de client, així que la detecció de
        duplicats escolars no s'hi aplica retroactivament — només afecta les importacions normals.
      </p>
    </div>
  <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="card p-4 shadow-sm">
      <input type="hidden" name="pas" value="pujar">
      <div class="mb-3">
        <label class="form-label">Fitxer Excel consolidat (.xlsx)</label>
        <input type="file" name="excel" accept=".xlsx" class="form-control" required>
      </div>
      <button class="btn btn-lava" type="submit">Analitzar fitxer</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
