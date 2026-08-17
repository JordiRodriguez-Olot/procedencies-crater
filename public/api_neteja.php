<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
// La neteja PARCIAL la pot fer qualsevol usuari amb sessió iniciada
// (són ells qui coneixen el dia a dia de les dades). L'esborrat TOTAL
// es restringeix més avall, cas per cas, a Auth::requerirAdminApi().
Auth::requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$accio = $_GET['accio'] ?? $_POST['accio'] ?? '';

function respon($dades): void { echo json_encode($dades, JSON_UNESCAPED_UNICODE); }

/** Construeix la clàusula WHERE i els paràmetres a partir dels filtres rebuts */
function construirFiltre(array $input): array
{
    $where = [];
    $params = [];

    if (!empty($input['any'])) {
        $where[] = 'e.any_visita = ?';
        $params[] = (int)$input['any'];
    }
    if (!empty($input['mes'])) {
        $where[] = 'e.mes_visita = ?';
        $params[] = (int)$input['mes'];
    }
    if (!empty($input['canal'])) {
        $where[] = 'c.codi = ?';
        $params[] = $input['canal'];
    }
    if (!empty($input['grup'])) {
        $where[] = 'COALESCE(mp.grup, p.nom_original) = ?';
        $params[] = $input['grup'];
    }
    if (empty($input['incloure_manual'])) {
        $where[] = 'e.es_manual = 0';
    }

    // Seguretat: si no s'ha indicat CAP filtre, no permetem continuar per
    // aquesta via (per a "eliminar-ho tot" hi ha una acció pròpia i més
    // vigilada — no volem que un buit accidental es converteixi en un
    // esborrat total per aquí).
    if (empty($where)) {
        throw new RuntimeException('Cal indicar almenys un criteri (any, mes, canal o producte) per a un esborrat parcial.');
    }

    return [implode(' AND ', $where), $params];
}

function descripcioFiltre(array $input): string
{
    $parts = [];
    if (!empty($input['any'])) $parts[] = 'Any ' . $input['any'];
    if (!empty($input['mes'])) $parts[] = 'Mes ' . $input['mes'];
    if (!empty($input['canal'])) $parts[] = 'Canal ' . $input['canal'];
    if (!empty($input['grup'])) $parts[] = 'Producte "' . $input['grup'] . '"';
    $parts[] = !empty($input['incloure_manual']) ? '(incloent entrades manuals)' : '(sense entrades manuals)';
    return implode(', ', $parts);
}

switch ($accio) {

    case 'previsualitzar_parcial':
        try {
            [$where, $params] = construirFiltre($_GET);
            $sql = "SELECT COUNT(*) AS files, COALESCE(SUM(e.pax),0) AS pax
                    FROM entrades e
                    LEFT JOIN productes p ON p.id = e.producte_id
                    LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
                    LEFT JOIN canals c ON c.id = e.canal_id
                    WHERE $where";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $r = $stmt->fetch();
            respon(['ok' => true, 'files' => (int)$r['files'], 'pax' => (int)$r['pax'], 'descripcio' => descripcioFiltre($_GET)]);
        } catch (Throwable $e) {
            http_response_code(400);
            respon(['error' => $e->getMessage()]);
        }
        break;

    case 'eliminar_parcial':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        if (trim($_POST['confirmacio'] ?? '') !== 'ELIMINAR') {
            respon(['error' => 'Cal escriure exactament "ELIMINAR" per confirmar.']); break;
        }
        try {
            [$where, $params] = construirFiltre($_POST);

            // Reconstruïm la mateixa consulta amb els alies necessaris
            // per al DELETE amb JOIN (MySQL exigeix indicar quina taula
            // es vol esborrar quan hi ha JOINs)
            $sqlCount = "SELECT COUNT(*) AS files, COALESCE(SUM(e.pax),0) AS pax
                    FROM entrades e
                    LEFT JOIN productes p ON p.id = e.producte_id
                    LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
                    LEFT JOIN canals c ON c.id = e.canal_id
                    WHERE $where";
            $stmt = $pdo->prepare($sqlCount);
            $stmt->execute($params);
            $abans = $stmt->fetch();

            if ((int)$abans['files'] === 0) {
                respon(['ok' => true, 'files_eliminades' => 0, 'pax_eliminats' => 0]);
                break;
            }

            $pdo->beginTransaction();

            $sqlDelete = "DELETE e FROM entrades e
                    LEFT JOIN productes p ON p.id = e.producte_id
                    LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
                    LEFT JOIN canals c ON c.id = e.canal_id
                    WHERE $where";
            $pdo->prepare($sqlDelete)->execute($params);

            // Neteja d'importacions que hagin quedat òrfenes (sense cap entrada)
            $pdo->exec('DELETE FROM importacions WHERE id NOT IN (SELECT DISTINCT importacio_id FROM entrades)');

            $registre = $pdo->prepare(
                'INSERT INTO registre_neteja (usuari_id, nom_usuari, tipus, criteris, files_eliminades, pax_eliminats)
                 VALUES (?, ?, \'parcial\', ?, ?, ?)'
            );
            $registre->execute([
                Auth::usuariId(), Auth::nomUsuari(), descripcioFiltre($_POST),
                (int)$abans['files'], (int)$abans['pax'],
            ]);

            $pdo->commit();
            respon(['ok' => true, 'files_eliminades' => (int)$abans['files'], 'pax_eliminats' => (int)$abans['pax']]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            respon(['error' => $e->getMessage()]);
        }
        break;

    case 'previsualitzar_total':
        Auth::requerirAdminApi();
        $r = $pdo->query('SELECT COUNT(*) AS files, COALESCE(SUM(pax),0) AS pax FROM entrades')->fetch();
        respon(['files' => (int)$r['files'], 'pax' => (int)$r['pax']]);
        break;

    case 'eliminar_total':
        Auth::requerirAdminApi();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        if (trim($_POST['confirmacio'] ?? '') !== 'ESBORRAR TOTES LES DADES') {
            respon(['error' => 'Cal escriure exactament la frase de confirmació indicada.']); break;
        }
        try {
            $abans = $pdo->query('SELECT COUNT(*) AS files, COALESCE(SUM(pax),0) AS pax FROM entrades')->fetch();

            $pdo->beginTransaction();
            $pdo->exec('DELETE FROM entrades');
            $pdo->exec('DELETE FROM importacions');
            $pdo->exec('DELETE FROM errors_descartats');

            $registre = $pdo->prepare(
                'INSERT INTO registre_neteja (usuari_id, nom_usuari, tipus, criteris, files_eliminades, pax_eliminats)
                 VALUES (?, ?, \'total\', \'Esborrat total (totes les entrades i importacions)\', ?, ?)'
            );
            $registre->execute([Auth::usuariId(), Auth::nomUsuari(), (int)$abans['files'], (int)$abans['pax']]);

            $pdo->commit();
            respon(['ok' => true, 'files_eliminades' => (int)$abans['files'], 'pax_eliminats' => (int)$abans['pax']]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            respon(['error' => $e->getMessage()]);
        }
        break;

    case 'historial':
        respon($pdo->query(
            'SELECT id, nom_usuari, tipus, criteris, files_eliminades, pax_eliminats, data_eliminacio
             FROM registre_neteja ORDER BY data_eliminacio DESC LIMIT 100'
        )->fetchAll());
        break;

    default:
        http_response_code(400);
        respon(['error' => 'Acció desconeguda']);
}
