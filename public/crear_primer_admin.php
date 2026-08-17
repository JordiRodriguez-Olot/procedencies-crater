<?php
require __DIR__ . '/../config/config.php';

$pdo = getPDO();
$hiHaUsuaris = (int)$pdo->query('SELECT COUNT(*) FROM usuaris')->fetchColumn() > 0;

$error = '';
$creat = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$hiHaUsuaris) {
    $usuari = trim($_POST['usuari'] ?? '');
    $contrasenya = $_POST['contrasenya'] ?? '';
    $contrasenya2 = $_POST['contrasenya2'] ?? '';

    if ($usuari === '' || mb_strlen($usuari) < 3) {
        $error = 'El nom d\'usuari ha de tenir almenys 3 caràcters.';
    } elseif (strlen($contrasenya) < 6) {
        $error = 'La contrasenya ha de tenir almenys 6 caràcters.';
    } elseif ($contrasenya !== $contrasenya2) {
        $error = 'Les contrasenyes no coincideixen.';
    } else {
        $hash = password_hash($contrasenya, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO usuaris (nom_usuari, contrasenya_hash, rol) VALUES (?, ?, 'admin')");
        $stmt->execute([$usuari, $hash]);
        $creat = true;
        $hiHaUsuaris = true;
    }
}
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Crear primer administrador · Espai Cràter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  * { font-family:'Inter',system-ui,sans-serif; }
  body { background:#f5f3f0; min-height:100vh; display:flex; align-items:center; justify-content:center; }
  .card-setup { max-width:440px; width:100%; border:none; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.1); }
  .btn-lava { background:#c0392b; color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:#7b241c; color:#fff; }
</style>
</head>
<body>
<div class="card card-setup p-4">
  <h1 class="h5 mb-3">🌋 Configuració inicial</h1>

  <?php if ($creat): ?>
    <div class="alert alert-success">
      Compte administrador <b><?= htmlspecialchars($usuari) ?></b> creat correctament.
    </div>
    <a href="login.php" class="btn btn-lava w-100">Anar a iniciar sessió →</a>

  <?php elseif ($hiHaUsuaris): ?>
    <div class="alert alert-warning">
      Ja hi ha almenys un usuari creat a l'aplicació, així que aquest pas d'inicialització
      ja no és necessari (ni es permet, per seguretat, crear més comptes des d'aquí).
      Si necessites un nou usuari, demana-ho a un administrador des de "Gestió d'usuaris".
    </div>
    <a href="login.php" class="btn btn-outline-secondary w-100">Anar a iniciar sessió →</a>

  <?php else: ?>
    <p class="text-muted" style="font-size:.9rem">
      Encara no hi ha cap usuari a l'aplicació. Crea aquí el primer compte, que serà administrador.
      <b>Un cop creat, esborra o restringeix l'accés a aquest fitxer</b> (aquesta pàgina ja no
      permetrà crear més comptes mentre n'hi hagi almenys un, però és més segur eliminar-la).
    </p>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2" style="font-size:.9rem"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="mb-3">
        <label class="form-label">Nom d'usuari</label>
        <input type="text" name="usuari" class="form-control" required minlength="3" autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Contrasenya</label>
        <input type="password" name="contrasenya" class="form-control" required minlength="6">
      </div>
      <div class="mb-3">
        <label class="form-label">Repeteix la contrasenya</label>
        <input type="password" name="contrasenya2" class="form-control" required minlength="6">
      </div>
      <button type="submit" class="btn btn-lava w-100">Crear administrador</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
