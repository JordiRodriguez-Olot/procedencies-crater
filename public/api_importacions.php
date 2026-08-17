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
        $sql = "SELECT i.id, i.nom_fitxer, i.data_visita_inici, i.data_visita_fi,
                       i.data_importacio, c.nom AS canal, c.codi AS canal_codi,
                       COUNT(e.id) AS files_actuals, COALESCE(SUM(e.pax),0) AS pax_actuals,
                       MIN(e.anymes) AS anymes
                FROM importacions i
                JOIN canals c ON c.id = i.canal_id
                LEFT JOIN entrades e ON e.importacio_id = i.id
                GROUP BY i.id, i.nom_fitxer, i.data_visita_inici, i.data_visita_fi,
                         i.data_importacio, c.nom, c.codi
                ORDER BY i.data_importacio DESC";
        respon($pdo->query($sql)->fetchAll());
        break;

    case 'desfer':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { respon(['error' => 'ID no vàlid']); break; }
        try {
            $stmtInfo = $pdo->prepare('SELECT nom_fitxer FROM importacions WHERE id = ?');
            $stmtInfo->execute([$id]);
            $nomFitxer = $stmtInfo->fetchColumn() ?: "#$id";

            $pdo->beginTransaction();
            $del = $pdo->prepare('DELETE FROM entrades WHERE importacio_id = ?');
            $del->execute([$id]);
            $esborrades = $del->rowCount();
            $pdo->prepare('DELETE FROM importacions WHERE id = ?')->execute([$id]);
            $pdo->commit();
            Registre::afegir($pdo, 'importacio_desfeta', "\"$nomFitxer\" desfeta ($esborrades entrades esborrades)");
            respon(['ok' => true, 'entrades_esborrades' => $esborrades]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            respon(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        respon(['error' => 'Acció desconeguda']);
}
