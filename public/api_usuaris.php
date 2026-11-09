<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Registre.php';
Auth::requerirAdminApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$accio = $_GET['accio'] ?? $_POST['accio'] ?? '';

function respon($dades): void { echo json_encode($dades, JSON_UNESCAPED_UNICODE); }

switch ($accio) {

    case 'llistar':
        $sql = "SELECT id, nom_usuari, nom, correu, origen, rol, actiu, data_creacio, ultim_acces FROM usuaris ORDER BY nom_usuari";
        respon($pdo->query($sql)->fetchAll());
        break;

    case 'crear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $usuari = trim($_POST['usuari'] ?? '');
        $contrasenya = $_POST['contrasenya'] ?? '';
        $rol = $_POST['rol'] ?? 'usuari';

        if (mb_strlen($usuari) < 3) { respon(['error' => 'El nom d\'usuari ha de tenir almenys 3 caràcters.']); break; }
        if (strlen($contrasenya) < 6) { respon(['error' => 'La contrasenya ha de tenir almenys 6 caràcters.']); break; }
        if (!in_array($rol, ['admin', 'usuari'], true)) { respon(['error' => 'Rol no vàlid.']); break; }

        try {
            $hash = password_hash($contrasenya, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO usuaris (nom_usuari, contrasenya_hash, rol) VALUES (?, ?, ?)');
            $stmt->execute([$usuari, $hash, $rol]);
            Registre::afegir($pdo, 'usuari_creat', "Usuari \"$usuari\" creat amb rol \"$rol\"");
            respon(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Throwable $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate') ? 'Ja existeix un usuari amb aquest nom.' : $e->getMessage();
            http_response_code(500); respon(['error' => $msg]);
        }
        break;

    // Reinicia la contrasenya d'un usuari (sense correu: l'admin l'hi comunica en persona)
    case 'resetejar_contrasenya':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $id = (int)($_POST['id'] ?? 0);
        $novaContrasenya = $_POST['contrasenya'] ?? '';
        if (strlen($novaContrasenya) < 6) { respon(['error' => 'La contrasenya ha de tenir almenys 6 caràcters.']); break; }
        try {
            $hash = password_hash($novaContrasenya, PASSWORD_DEFAULT);
            // Els comptes del domini es validen contra l'AD: no tenen contrasenya local.
            $afectats = $pdo->prepare("UPDATE usuaris SET contrasenya_hash = ? WHERE id = ? AND origen = 'local'");
            $afectats->execute([$hash, $id]);
            if ($afectats->rowCount() === 0) { respon(['error' => 'Aquest compte és del domini: la contrasenya es gestiona a l\'Active Directory.']); break; }
            Registre::afegir($pdo, 'contrasenya_reiniciada', "Contrasenya reiniciada per a l'usuari #$id (no es registra la contrasenya)");
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'canviar_rol':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $id = (int)($_POST['id'] ?? 0);
        $rol = $_POST['rol'] ?? '';
        if (!in_array($rol, ['admin', 'usuari'], true)) { respon(['error' => 'Rol no vàlid.']); break; }
        if ($id === Auth::usuariId() && $rol !== 'admin') {
            respon(['error' => 'No et pots treure el rol d\'administrador a tu mateix.']); break;
        }
        try {
            $pdo->prepare('UPDATE usuaris SET rol = ? WHERE id = ?')->execute([$rol, $id]);
            Registre::afegir($pdo, 'rol_canviat', "Usuari #$id -> rol \"$rol\"");
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    // Activa/desactiva un compte (en lloc d'esborrar-lo, per mantenir traçabilitat)
    case 'canviar_actiu':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $id = (int)($_POST['id'] ?? 0);
        $actiu = (int)($_POST['actiu'] ?? 1);
        if ($id === Auth::usuariId() && !$actiu) {
            respon(['error' => 'No et pots desactivar a tu mateix.']); break;
        }
        try {
            $pdo->prepare('UPDATE usuaris SET actiu = ? WHERE id = ?')->execute([$actiu ? 1 : 0, $id]);
            Registre::afegir($pdo, 'usuari_actiu_canviat', "Usuari #$id -> " . ($actiu ? 'activat' : 'desactivat'));
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        respon(['error' => 'Acció desconeguda']);
}
