<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$accio = $_GET['accio'] ?? '';

function respon($dades): void { echo json_encode($dades, JSON_UNESCAPED_UNICODE); }

switch ($accio) {

    case 'historial':
        $tipus = trim($_GET['tipus'] ?? '');

        $condNeteja = '';
        $condActivitat = '';
        $paramsNeteja = [];
        $paramsActivitat = [];

        if ($tipus === '') {
            // sense filtre: totes dues taules senceres
        } elseif (str_starts_with($tipus, 'neteja_')) {
            // vol nomes un subtipus de neteja (parcial/total)
            $subtipus = substr($tipus, strlen('neteja_'));
            $condNeteja = 'WHERE tipus = ?';
            $paramsNeteja[] = $subtipus;
            $condActivitat = 'WHERE 1=0';
        } else {
            // vol nomes un tipus concret d'activitat general
            $condNeteja = 'WHERE 1=0';
            $condActivitat = 'WHERE tipus = ?';
            $paramsActivitat[] = $tipus;
        }

        $sql = "
            (SELECT data_eliminacio AS data, nom_usuari,
                    CONCAT('neteja_', tipus) AS tipus,
                    CONCAT(criteris, ' — ', files_eliminades, ' files / ', pax_eliminats, ' pax') AS detall
             FROM registre_neteja $condNeteja)
            UNION ALL
            (SELECT data_hora AS data, nom_usuari, tipus, detall
             FROM registre_activitat $condActivitat)
            ORDER BY data DESC
            LIMIT 300
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($paramsNeteja, $paramsActivitat));
        respon($stmt->fetchAll());
        break;

    case 'tipus_disponibles':
        $r1 = $pdo->query("SELECT DISTINCT tipus FROM registre_activitat")->fetchAll(PDO::FETCH_COLUMN);
        $r2 = $pdo->query("SELECT DISTINCT CONCAT('neteja_', tipus) FROM registre_neteja")->fetchAll(PDO::FETCH_COLUMN);
        $tots = array_values(array_unique(array_merge($r1, $r2)));
        sort($tots);
        respon($tots);
        break;

    default:
        http_response_code(400);
        respon(['error' => 'Acció desconeguda']);
}
