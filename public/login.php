<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::iniciar();

// Si ja hi ha sessió, no cal tornar a mostrar el login
if (Auth::esLogat()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuari = trim($_POST['usuari'] ?? '');
    $contrasenya = $_POST['contrasenya'] ?? '';

    if ($usuari === '' || $contrasenya === '') {
        $error = 'Cal omplir usuari i contrasenya.';
    } else {
        $resultat = Auth::intentarLogin(getPDO(), $usuari, $contrasenya);
        if ($resultat['ok']) {
            $desti = $_GET['redirect'] ?? 'index.php';
            // Evitar "open redirect": només acceptem rutes internes relatives
            if (!preg_match('#^[a-zA-Z0-9_\-\./]+\.php(\?.*)?$#', ltrim($desti, '/'))) {
                $desti = 'index.php';
            }
            header('Location: ' . $desti);
            exit;
        } else {
            $error = $resultat['error'];
        }
    }
}
?>
<!doctype html>
<html lang="ca">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sessió · Espai Cràter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  :root { --lava:#c0392b; --lava-dark:#7b241c; --ember:#e67e22; }
  * { font-family:'Inter',system-ui,sans-serif; }
  body {
    background: linear-gradient(135deg, #2c3e50, #7b241c 60%, #e67e22);
    min-height: 100vh; display:flex; align-items:center; justify-content:center;
  }
  .card-login { max-width: 380px; width:100%; border:none; border-radius:18px; box-shadow:0 20px 50px rgba(0,0,0,.35); }
  .btn-lava { background:var(--lava); color:#fff; font-weight:600; border:none; }
  .btn-lava:hover { background:var(--lava-dark); color:#fff; }
</style>
</head>
<body>
<div class="card card-login p-4 p-md-5">
  <div class="text-center mb-4">
    <img src="assets/logo.png" alt="Espai Cràter" style="height:120px">
    <p class="text-muted mt-3 mb-0" style="font-size:.9rem">Control de procedències</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2" style="font-size:.9rem"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if (isset($_GET['expirat'])): ?>
    <div class="alert alert-warning py-2" style="font-size:.9rem">La sessió ha caducat per inactivitat. Torna a iniciar sessió.</div>
  <?php endif; ?>
  <?php if (isset($_GET['creat'])): ?>
    <div class="alert alert-success py-2" style="font-size:.9rem">Compte creat correctament. Ja pots iniciar sessió.</div>
  <?php endif; ?>

  <form method="post">
    <div class="mb-3">
      <label class="form-label">Usuari</label>
      <input type="text" name="usuari" class="form-control" required autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label">Contrasenya</label>
      <input type="password" name="contrasenya" class="form-control" required>
    </div>
    <button type="submit" class="btn btn-lava w-100">Entrar</button>
  </form>

  <p class="text-center text-muted mt-3 mb-0" style="font-size:.78rem">
    Accés restringit — intranet de l'ajuntament.<br>
    Si no tens compte o has oblidat la contrasenya, contacta amb un administrador.
  </p>
</div>
</body>
</html>
