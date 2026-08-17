<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLogin();
require __DIR__ . '/../src/SimpleXlsxReader.php';
require __DIR__ . '/../src/Importer.php';

$missatges = [];
$confirmacio = null; // dades per mostrar la pantalla de confirmació de duplicat
$tmpDir = __DIR__ . '/../storage/tmp';

// Neteja fitxers temporals abandonats (més d'1 hora, mai confirmats)
foreach (glob($tmpDir . '/*.xlsx') ?: [] as $f) {
    if (is_file($f) && (time() - filemtime($f)) > 3600) @unlink($f);
}

function nomMes(int $m): string {
    return ['','Gen','Feb','Mar','Abr','Mai','Jun','Jul','Ago','Set','Oct','Nov','Des'][$m] ?? (string)$m;
}

// ---------- Pas 1: pujada inicial d'un fitxer ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel']) && ($_POST['pas'] ?? '') === 'pujar') {
    $canal = $_POST['canal'] ?? '';
    if (!in_array($canal, ['TAQUILLA', 'ONLINE', 'CGR'], true)) {
        $missatges[] = ['error', 'Canal no vàlid.'];
    } elseif ($_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
        $missatges[] = ['error', 'Error pujant el fitxer.'];
    } else {
        $nomOriginal = $_FILES['excel']['name'];
        $token = bin2hex(random_bytes(8)) . '.xlsx';
        $desti = $tmpDir . '/' . $token;
        move_uploaded_file($_FILES['excel']['tmp_name'], $desti);

        try {
            $importer = new Importer(getPDO());
            $res = $importer->importar($desti, $canal, $nomOriginal, 'preguntar');
            $missatges[] = ['success', "Importades {$res['files']} files per al període {$res['anymes']} (canal $canal)."];
            @unlink($desti);
        } catch (ImportDuplicatException $e) {
            $confirmacio = [
                'token' => $token,
                'nom_original' => $nomOriginal,
                'canal' => $canal,
                'anymes' => $e->anymes,
                'files_existents' => $e->filesExistents,
                'pax_existents' => $e->paxExistents,
            ];
        } catch (Throwable $e) {
            @unlink($desti);
            $missatges[] = ['error', 'Error important: ' . $e->getMessage()];
        }
    }
}

// ---------- Pas 2: confirmació (substituir / afegir / cancel·lar) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pas'] ?? '') === 'confirmar') {
    $token = basename($_POST['token'] ?? '');
    $canal = $_POST['canal'] ?? '';
    $nomOriginal = $_POST['nom_original'] ?? 'fitxer.xlsx';
    $decisio = $_POST['decisio'] ?? '';
    $fitxer = $tmpDir . '/' . $token;

    if ($decisio === 'cancelar') {
        @unlink($fitxer);
        $missatges[] = ['error', 'Importació cancel·lada. No s\'ha modificat cap dada.'];
    } elseif (in_array($decisio, ['substituir', 'afegir'], true) && is_file($fitxer)) {
        try {
            $importer = new Importer(getPDO());
            $res = $importer->importar($fitxer, $canal, $nomOriginal, $decisio);
            $accioTxt = $decisio === 'substituir' ? 'substituint les dades anteriors d\'aquest període' : 'afegint-se a les dades existents';
            $missatges[] = ['success', "Importades {$res['files']} files per al període {$res['anymes']} (canal $canal), $accioTxt."];
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
<title>Importar dades - Espai Cràter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; }
  body { font-family:'Inter',system-ui,sans-serif; background:#f5f3f0; }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
  .avis-dup { background:#fff8e1; border-left:4px solid var(--ember); border-radius:8px; padding:1rem; }
</style>
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:640px">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Importar Excel de Clorian</h1>
    <a href="importacions.php" class="btn btn-outline-secondary btn-sm">📋 Historial d'importacions</a>
  </div>
  <p class="text-muted" style="font-size:.85rem">
    Tens un Excel amb <b>tot l'històric consolidat</b> (tots els canals i períodes junts)?
    Fes servir la <a href="import_general.php">importació general</a> en lloc d'aquesta.
  </p>

  <?php foreach ($missatges as [$tipus, $text]): ?>
    <div class="alert alert-<?= $tipus === 'error' ? 'danger' : 'success' ?>"><?= htmlspecialchars($text) ?></div>
  <?php endforeach; ?>

  <?php if ($confirmacio): ?>
    <div class="avis-dup mb-4">
      <h5>⚠ Ja hi ha dades importades per aquest període</h5>
      <p class="mb-2">
        El fitxer <b><?= htmlspecialchars($confirmacio['nom_original']) ?></b> correspon al període
        <b><?= nomMes((int)substr($confirmacio['anymes'],4,2)) . ' ' . substr($confirmacio['anymes'],0,4) ?></b>
        del canal <b><?= htmlspecialchars($confirmacio['canal']) ?></b>, i ja hi ha
        <b><?= (int)$confirmacio['files_existents'] ?> files</b> importades
        (<b><?= number_format((int)$confirmacio['pax_existents'], 0, ',', '.') ?> pax</b>) per aquest mateix període i canal.
      </p>
      <p class="mb-3">Què vols fer?</p>
      <form method="post" class="d-flex gap-2 flex-wrap">
        <input type="hidden" name="pas" value="confirmar">
        <input type="hidden" name="token" value="<?= htmlspecialchars($confirmacio['token']) ?>">
        <input type="hidden" name="canal" value="<?= htmlspecialchars($confirmacio['canal']) ?>">
        <input type="hidden" name="nom_original" value="<?= htmlspecialchars($confirmacio['nom_original']) ?>">
        <button type="submit" name="decisio" value="substituir" class="btn btn-lava btn-sm">🔁 Substituir les dades anteriors</button>
        <button type="submit" name="decisio" value="afegir" class="btn btn-outline-secondary btn-sm">➕ Afegir igualment (pot duplicar)</button>
        <button type="submit" name="decisio" value="cancelar" class="btn btn-outline-danger btn-sm">✕ Cancel·lar</button>
      </form>
      <p class="mt-2 mb-0 text-muted" style="font-size:.82rem">
        "Substituir" esborra només les entrades d'aquest canal i període concrets (les entrades manuals no es toquen).
      </p>
    </div>
  <?php else: ?>
    <form method="post" enctype="multipart/form-data" class="card p-4 shadow-sm">
      <input type="hidden" name="pas" value="pujar">
      <div class="mb-3">
        <label class="form-label">Canal</label>
        <select name="canal" class="form-select" required>
          <option value="TAQUILLA">Taquilla</option>
          <option value="ONLINE">Online</option>
          <option value="CGR">CGR</option>
        </select>
      </div>
      <div class="mb-3">
        <label class="form-label">Fitxer Excel (.xlsx)</label>
        <input type="file" name="excel" accept=".xlsx" class="form-control" required>
      </div>
      <button class="btn btn-lava" type="submit">Importar</button>
      <a href="index.php" class="btn btn-link">Anar al dashboard →</a>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
