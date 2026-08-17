<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Registre.php';
Auth::requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$accio = $_GET['accio'] ?? $_POST['accio'] ?? '';

function respon($dades): void { echo json_encode($dades, JSON_UNESCAPED_UNICODE); }

switch ($accio) {

    case 'llistar':
        $sql = "SELECT p.id, p.nom_original, mp.grup,
                       COUNT(e.id) AS files, COALESCE(SUM(e.pax),0) AS pax
                FROM productes p
                LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
                LEFT JOIN entrades e ON e.producte_id = p.id
                GROUP BY p.id, p.nom_original, mp.grup
                ORDER BY (mp.grup IS NULL) DESC, pax DESC";
        respon($pdo->query($sql)->fetchAll());
        break;

    case 'previstos':
        $sql = "SELECT mp.nom_original, mp.grup
                FROM mapeig_productes mp
                LEFT JOIN productes p ON p.nom_original = mp.nom_original
                WHERE p.id IS NULL
                ORDER BY mp.nom_original";
        respon($pdo->query($sql)->fetchAll());
        break;

    case 'grups_existents':
        $sql = "SELECT DISTINCT grup FROM mapeig_productes ORDER BY grup";
        respon($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN));
        break;

    case 'assignar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $nomOriginal = trim($_POST['nom_original'] ?? '');
        $grup = trim($_POST['grup'] ?? '');
        if ($nomOriginal === '' || $grup === '') {
            respon(['error' => 'Cal indicar el nom del producte i el grup.']); break;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO mapeig_productes (nom_original, grup) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE grup = VALUES(grup)'
            );
            $stmt->execute([$nomOriginal, $grup]);
            Registre::afegir($pdo, 'producte_assignat', "\"$nomOriginal\" -> grup \"$grup\"");
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'eliminar_previst':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $nomOriginal = trim($_POST['nom_original'] ?? '');
        try {
            $stmt = $pdo->prepare('DELETE FROM mapeig_productes WHERE nom_original = ?');
            $stmt->execute([$nomOriginal]);
            if ($stmt->rowCount() > 0) {
                Registre::afegir($pdo, 'producte_desassignat', "\"$nomOriginal\" tret del seu grup (torna a ser \"sense grup\")");
            }
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'fusionar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $origen = trim($_POST['grup_origen'] ?? '');
        $desti = trim($_POST['grup_desti'] ?? '');
        if ($origen === '' || $desti === '' || $origen === $desti) {
            respon(['error' => 'Cal indicar dos grups diferents.']); break;
        }
        try {
            $stmt = $pdo->prepare('UPDATE mapeig_productes SET grup = ? WHERE grup = ?');
            $stmt->execute([$desti, $origen]);
            Registre::afegir($pdo, 'grups_fusionats', "Grup \"$origen\" fusionat a \"$desti\" ({$stmt->rowCount()} productes afectats)");
            respon(['ok' => true, 'afectats' => $stmt->rowCount()]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        respon(['error' => 'Acció desconeguda']);
}
