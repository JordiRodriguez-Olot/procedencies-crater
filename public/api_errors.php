<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
require __DIR__ . '/../src/Registre.php';
Auth::requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$accio = $_GET['accio'] ?? $_POST['accio'] ?? '';

function respon($dades): void { echo json_encode($dades, JSON_UNESCAPED_UNICODE); }

// Prefixos de codi postal de les 4 províncies catalanes
const PREFIXOS_CAT = "'08','17','25','43'";

// Departaments francesos (codi de 2 dígits -> nom), útils per detectar
// codis postals francesos mal marcats com a "Espanya" — molt habitual
// en una zona fronterera com la Garrotxa amb molt turisme francès.
const DEPARTAMENTS_FR = [
    '01'=>'Ain','02'=>'Aisne','03'=>'Allier','04'=>'Alpes-de-Haute-Provence',
    '05'=>'Hautes-Alpes','06'=>'Alpes-Maritimes','07'=>'Ardèche','08'=>'Ardennes',
    '09'=>'Ariège','10'=>'Aube','11'=>'Aude','12'=>'Aveyron','13'=>'Bouches-du-Rhône',
    '14'=>'Calvados','15'=>'Cantal','16'=>'Charente','17'=>'Charente-Maritime',
    '18'=>'Cher','19'=>'Corrèze','21'=>'Côte-d\'Or','22'=>'Côtes-d\'Armor',
    '23'=>'Creuse','24'=>'Dordogne','25'=>'Doubs','26'=>'Drôme','27'=>'Eure',
    '28'=>'Eure-et-Loir','29'=>'Finistère','30'=>'Gard','31'=>'Haute-Garonne',
    '32'=>'Gers','33'=>'Gironde','34'=>'Hérault','35'=>'Ille-et-Vilaine',
    '36'=>'Indre','37'=>'Indre-et-Loire','38'=>'Isère','39'=>'Jura','40'=>'Landes',
    '41'=>'Loir-et-Cher','42'=>'Loire','43'=>'Haute-Loire','44'=>'Loire-Atlantique',
    '45'=>'Loiret','46'=>'Lot','47'=>'Lot-et-Garonne','48'=>'Lozère',
    '49'=>'Maine-et-Loire','50'=>'Manche','51'=>'Marne','52'=>'Haute-Marne',
    '53'=>'Mayenne','54'=>'Meurthe-et-Moselle','55'=>'Meuse','56'=>'Morbihan',
    '57'=>'Moselle','58'=>'Nièvre','59'=>'Nord','60'=>'Oise','61'=>'Orne',
    '62'=>'Pas-de-Calais','63'=>'Puy-de-Dôme','64'=>'Pyrénées-Atlantiques',
    '65'=>'Hautes-Pyrénées','66'=>'Pyrénées-Orientales','67'=>'Bas-Rhin',
    '68'=>'Haut-Rhin','69'=>'Rhône','70'=>'Haute-Saône','71'=>'Saône-et-Loire',
    '72'=>'Sarthe','73'=>'Savoie','74'=>'Haute-Savoie','75'=>'Paris',
    '76'=>'Seine-Maritime','77'=>'Seine-et-Marne','78'=>'Yvelines','79'=>'Deux-Sèvres',
    '80'=>'Somme','81'=>'Tarn','82'=>'Tarn-et-Garonne','83'=>'Var','84'=>'Vaucluse',
    '85'=>'Vendée','86'=>'Vienne','87'=>'Haute-Vienne','88'=>'Vosges','89'=>'Yonne',
    '90'=>'Territoire de Belfort','91'=>'Essonne','92'=>'Hauts-de-Seine',
    '93'=>'Seine-Saint-Denis','94'=>'Val-de-Marne','95'=>'Val-d\'Oise',
];

/** Comprova si un CP de 5 dígits té un prefix vàlid a Espanya (existeix
 *  a la taula de províncies) */
function prefixEspanyolValid(PDO $pdo, string $cp5): bool
{
    if (strlen($cp5) !== 5 || !ctype_digit($cp5)) return false;
    static $cache = [];
    $prefix = substr($cp5, 0, 2);
    if (!isset($cache[$prefix])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM provincies WHERE codi_prefix = ?');
        $stmt->execute([$prefix]);
        $cache[$prefix] = (bool)$stmt->fetchColumn();
    }
    return $cache[$prefix];
}

/** Si un CP de 6 dígits té exactament un candidat de 5 dígits vàlid
 *  (provant de suprimir cada posició), el retorna. Si no n'hi ha cap
 *  o n'hi ha més d'un d'ambigu, retorna null. */
function candidatPerSupressio(PDO $pdo, string $digits6): ?string
{
    if (strlen($digits6) !== 6) return null;
    $candidats = [];
    for ($i = 0; $i < 6; $i++) {
        $cand = substr($digits6, 0, $i) . substr($digits6, $i + 1);
        if (prefixEspanyolValid($pdo, $cand)) $candidats[] = $cand;
    }
    $unics = array_unique($candidats);
    return count($unics) === 1 ? $unics[0] : null;
}

// Condicions SQL que defineixen cada tipus d'error
function condicioError(string $tipus): string
{
    return match ($tipus) {
        'pais_buit'    => "(pais IS NULL OR pais = '')",
        'cp_buit'      => "pais_es_espanya = 1 AND (codi_postal IS NULL OR codi_postal = '')",
        'cp_format'    => "codi_postal IS NOT NULL AND codi_postal <> '' AND codi_postal NOT REGEXP '^[0-9]{5}$'",
        'provincia_no' => "pais_es_espanya = 1 AND codi_postal REGEXP '^[0-9]{5}$' AND provincia_derivada IS NULL",
        'comarca_no'   => "codi_postal REGEXP '^[0-9]{5}$' AND LEFT(LPAD(codi_postal,5,'0'),2) IN (" . PREFIXOS_CAT . ") AND comarca_derivada IS NULL",
        default        => "1=0",
    };
}

/** Fragment SQL que exclou els grups (codi_postal, pais) ja descartats
 *  conscientment per aquest tipus d'error a la revisió massiva. */
function filtreDescartats(PDO $pdo, string $tipus): string
{
    return "NOT EXISTS (
        SELECT 1 FROM errors_descartats ed
        WHERE ed.tipus = " . $pdo->quote($tipus) . "
          AND ed.codi_postal <=> entrades.codi_postal
          AND ed.pais <=> entrades.pais
    )";
}

switch ($accio) {

    // Resum: nombre d'entrades afectades per cada tipus d'error
    case 'resum':
        $tipus = ['pais_buit','cp_buit','cp_format','provincia_no','comarca_no'];
        $out = [];
        foreach ($tipus as $t) {
            $sql = "SELECT COALESCE(SUM(pax),0) AS pax, COUNT(*) AS files FROM entrades
                    WHERE " . condicioError($t) . " AND " . filtreDescartats($pdo, $t);
            $r = $pdo->query($sql)->fetch();
            $out[$t] = ['files' => (int)$r['files'], 'pax' => (int)$r['pax']];
        }
        // Duplicats escolars (lògica pròpia)
        $dups = detectarDuplicatsEscolars($pdo);
        $out['dup_escolar'] = [
            'files' => count($dups),
            'pax' => array_sum(array_map(fn($d) => (int)$d['pax_sospitos'], $dups)),
        ];
        respon($out);
        break;

    // Detall d'un tipus d'error, agrupat per (codi_postal, pais)
    case 'detall':
        $tipus = $_GET['tipus'] ?? '';

        // Cas especial: duplicats escolars (lògica pròpia)
        if ($tipus === 'dup_escolar') {
            respon(detectarDuplicatsEscolars($pdo));
            break;
        }

        $cond = condicioError($tipus) . " AND " . filtreDescartats($pdo, $tipus);
        $sql = "SELECT codi_postal, pais, provincia_derivada, comarca_derivada,
                       COUNT(*) AS files, SUM(pax) AS pax
                FROM entrades
                WHERE $cond
                GROUP BY codi_postal, pais, provincia_derivada, comarca_derivada
                ORDER BY pax DESC";
        $files = $pdo->query($sql)->fetchAll();

        // Afegim suggeriments per proximitat de CP quan falta província o comarca
        if (in_array($tipus, ['comarca_no', 'provincia_no', 'cp_format'], true)) {
            foreach ($files as &$f) {
                $sug = suggerirPerCp($pdo, $f['codi_postal'] ?? '');
                $f['sug_provincia']   = $sug['provincia'];
                $f['sug_comarca']     = $sug['comarca'];
                $f['sug_cp_ref']      = $sug['cp_ref'];
                $f['sug_municipi']    = $sug['municipi'];

                // Per a "província no assignada": si el prefix no existeix a
                // Espanya però sí que és un departament francès real, avisem
                // que probablement el país s'ha marcat malament com a Espanya
                // (molt habitual en zona fronterera com la Garrotxa).
                if ($tipus === 'provincia_no' && !$f['sug_provincia']) {
                    $cp = $f['codi_postal'] ?? '';
                    if (strlen($cp) >= 2) {
                        $prefix = substr(str_pad($cp, 5, '0', STR_PAD_LEFT), 0, 2);
                        if (isset(DEPARTAMENTS_FR[$prefix])) {
                            $f['sug_pais_estranger'] = 'Francia';
                            $f['sug_departament_fr'] = DEPARTAMENTS_FR[$prefix];
                        }
                    }
                }
            }
            unset($f);
        }
        respon($files);
        break;

    // Catàlegs per als desplegables (províncies, comarques, productes, canals)
    case 'catalegs':
        respon([
            'provincies' => $pdo->query("SELECT nom FROM provincies ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN),
            'comarques'  => $pdo->query("SELECT nom FROM comarques ORDER BY nom")->fetchAll(PDO::FETCH_COLUMN),
            'productes'  => $pdo->query("SELECT nom_original FROM productes ORDER BY nom_original")->fetchAll(PDO::FETCH_COLUMN),
            'canals'     => $pdo->query("SELECT codi, nom FROM canals ORDER BY nom")->fetchAll(),
        ]);
        break;

    // Afegeix una entrada manual (cas excepcional no cobert per la importació)
    case 'afegir_manual':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        try {
            $canalCodi = trim($_POST['canal'] ?? '');
            $producteNom = trim($_POST['producte'] ?? '');
            $pais = trim($_POST['pais'] ?? '');
            $cp = trim($_POST['codi_postal'] ?? '');
            $pax = (int)($_POST['pax'] ?? 0);
            $any = (int)($_POST['any'] ?? 0);
            $mes = (int)($_POST['mes'] ?? 0);
            $nota = trim($_POST['nota'] ?? '');
            $nomClient = trim($_POST['nom_client'] ?? '');

            if ($canalCodi === '' || $producteNom === '' || $pax <= 0 || $any < 2000 || $mes < 1 || $mes > 12) {
                respon(['error' => 'Falten camps obligatoris (canal, producte, pax, any, mes).']); break;
            }
            if ($nota === '') {
                respon(['error' => 'Cal indicar un motiu per a l\'entrada manual, per traçabilitat.']); break;
            }

            $pdo->beginTransaction();

            $canalId = (int)$pdo->query("SELECT id FROM canals WHERE codi = " . $pdo->quote($canalCodi))->fetchColumn();
            if (!$canalId) throw new RuntimeException('Canal no vàlid.');

            $stmt = $pdo->prepare('SELECT id FROM productes WHERE nom_original = ?');
            $stmt->execute([$producteNom]);
            $producteId = $stmt->fetchColumn();
            if (!$producteId) {
                $ins = $pdo->prepare('INSERT INTO productes (nom_original) VALUES (?)');
                $ins->execute([$producteNom]);
                $producteId = (int)$pdo->lastInsertId();
            }

            $insImport = $pdo->prepare(
                'INSERT INTO importacions (canal_id, nom_fitxer, files_importades)
                 VALUES (?, ?, 1)'
            );
            $insImport->execute([$canalId, 'Entrada manual: ' . $nota]);
            $importacioId = (int)$pdo->lastInsertId();

            $esEspanya = in_array(mb_strtolower($pais), ['españa', 'espanya', 'spain'], true) ? 1 : 0;

            $provinciaDerivada = null;
            $comarcaDerivada = null;
            $cpNorm = null;
            if ($cp !== '' && ctype_digit($cp)) {
                $cpNorm = str_pad($cp, 5, '0', STR_PAD_LEFT);
                if ($esEspanya) {
                    [$provinciaDerivada, $comarcaDerivada] = derivarDeCp($pdo, $cpNorm);
                    $stmt = $pdo->prepare('SELECT provincia, comarca FROM correccions_cp WHERE codi_postal = ?');
                    $stmt->execute([$cpNorm]);
                    if ($corr = $stmt->fetch()) {
                        if (!empty($corr['provincia'])) $provinciaDerivada = $corr['provincia'];
                        if (!empty($corr['comarca']))   $comarcaDerivada   = $corr['comarca'];
                    }
                }
            }

            $insEntrada = $pdo->prepare(
                'INSERT INTO entrades
                 (importacio_id, canal_id, producte_id, nom_client, pais, codi_postal, pax,
                  any_visita, mes_visita, anymes, provincia_derivada, comarca_derivada,
                  pais_es_espanya, es_manual, nota_manual)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)'
            );
            $insEntrada->execute([
                $importacioId, $canalId, $producteId,
                $nomClient !== '' ? $nomClient : null,
                $pais !== '' ? $pais : null,
                $cpNorm,
                $pax, $any, $mes, $any * 100 + $mes,
                $provinciaDerivada, $comarcaDerivada, $esEspanya,
                $nota,
            ]);

            $pdo->commit();
            Registre::afegir($pdo, 'entrada_manual_afegida',
                "Canal $canalCodi, $producteNom, $pax pax ($any/$mes). Motiu: $nota");
            respon(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            respon(['error' => $e->getMessage()]);
        }
        break;

    case 'llistar_manuals':
        $sql = "SELECT e.id, e.nom_client, e.pais, e.codi_postal, e.pax, e.any_visita, e.mes_visita,
                       e.nota_manual, e.provincia_derivada, e.comarca_derivada,
                       p.nom_original AS producte, c.nom AS canal
                FROM entrades e
                LEFT JOIN productes p ON p.id = e.producte_id
                LEFT JOIN canals c ON c.id = e.canal_id
                WHERE e.es_manual = 1
                ORDER BY e.id DESC";
        respon($pdo->query($sql)->fetchAll());
        break;

    case 'eliminar_manual':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare('DELETE FROM entrades WHERE id = ? AND es_manual = 1');
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                Registre::afegir($pdo, 'entrada_manual_eliminada', "Entrada manual #$id eliminada");
            }
            respon(['ok' => true, 'eliminades' => $stmt->rowCount()]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'corregir':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }

        $cpOrig    = ($_POST['cp_orig']   ?? '') === '' ? null : $_POST['cp_orig'];
        $paisOrig  = ($_POST['pais_orig'] ?? '') === '' ? null : $_POST['pais_orig'];
        $nouCp     = trim($_POST['nou_cp'] ?? '');
        $nouPais   = trim($_POST['nou_pais'] ?? '');
        $novaProv  = trim($_POST['nova_provincia'] ?? '');
        $novaCom   = trim($_POST['nova_comarca'] ?? '');
        $persistir = ($_POST['persistir'] ?? '0') === '1';

        try {
            $pdo->beginTransaction();

            $sets = [];
            $params = [];

            $cpEfectiu = $nouCp !== '' ? str_pad($nouCp, 5, '0', STR_PAD_LEFT) : ($cpOrig !== null ? str_pad($cpOrig, 5, '0', STR_PAD_LEFT) : null);

            if ($nouCp !== '') {
                $sets[] = "codi_postal = ?";
                $params[] = $cpEfectiu;
            }
            if ($nouPais !== '') {
                $sets[] = "pais = ?";
                $params[] = $nouPais;
                $esEsp = in_array(mb_strtolower($nouPais), ['españa','espanya','spain']) ? 1 : 0;
                $sets[] = "pais_es_espanya = ?";
                $params[] = $esEsp;
            }
            if ($novaProv !== '') {
                $sets[] = "provincia_derivada = ?";
                $params[] = $novaProv;
            }
            if ($novaCom !== '') {
                $sets[] = "comarca_derivada = ?";
                $params[] = $novaCom;
            }

            if ($nouCp !== '' && ($novaProv === '' || $novaCom === '')) {
                [$provDeriv, $comDeriv] = derivarDeCp($pdo, $cpEfectiu);
                if ($novaProv === '' && $provDeriv !== null) { $sets[] = "provincia_derivada = ?"; $params[] = $provDeriv; }
                if ($novaCom === ''  && $comDeriv  !== null) { $sets[] = "comarca_derivada = ?";   $params[] = $comDeriv; }
            }

            if (empty($sets)) { $pdo->rollBack(); respon(['error' => 'No s\'ha indicat cap valor a corregir.']); break; }

            $sql = "UPDATE entrades SET " . implode(', ', $sets) . " WHERE codi_postal <=> ? AND pais <=> ?";
            $params[] = $cpOrig;
            $params[] = $paisOrig;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $afectades = $stmt->rowCount();

            if ($persistir && $cpEfectiu !== null && ($novaProv !== '' || $novaCom !== '')) {
                $up = $pdo->prepare(
                    "INSERT INTO correccions_cp (codi_postal, provincia, comarca)
                     VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                       provincia = COALESCE(VALUES(provincia), provincia),
                       comarca = COALESCE(VALUES(comarca), comarca),
                       data_correccio = NOW()"
                );
                $up->execute([$cpEfectiu, $novaProv ?: null, $novaCom ?: null]);
            }

            $pdo->commit();
            $canvis = [];
            if ($nouCp !== '') $canvis[] = "CP -> $nouCp";
            if ($nouPais !== '') $canvis[] = "país -> $nouPais";
            if ($novaProv !== '') $canvis[] = "província -> $novaProv";
            if ($novaCom !== '') $canvis[] = "comarca -> $novaCom";
            Registre::afegir($pdo, 'correccio_cp',
                "CP/país original ($cpOrig / $paisOrig): " . implode(', ', $canvis) . " ($afectades files afectades)");
            respon(['ok' => true, 'afectades' => $afectades]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            respon(['error' => $e->getMessage()]);
        }
        break;

    case 'rederivar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        try {
            $pdo->beginTransaction();

            $pdo->exec(
                "UPDATE entrades e
                 JOIN provincies p ON p.codi_prefix = LEFT(LPAD(e.codi_postal,5,'0'),2)
                 SET e.provincia_derivada = p.nom
                 WHERE e.pais_es_espanya = 1 AND e.codi_postal REGEXP '^[0-9]{5}$'"
            );

            $pdo->exec(
                "UPDATE entrades e
                 JOIN cp_municipis cm ON cm.codi_postal = LPAD(e.codi_postal,5,'0')
                 JOIN municipis m ON m.nom_municipi = cm.nom_municipi
                 JOIN comarques c ON c.codi = m.codi_comarca
                 SET e.comarca_derivada = c.nom
                 WHERE e.codi_postal REGEXP '^[0-9]{5}$'"
            );

            $pdo->exec(
                "UPDATE entrades e
                 JOIN correccions_cp cc ON cc.codi_postal = LPAD(e.codi_postal,5,'0')
                 SET e.provincia_derivada = COALESCE(cc.provincia, e.provincia_derivada),
                     e.comarca_derivada   = COALESCE(cc.comarca, e.comarca_derivada)"
            );

            $pdo->commit();
            Registre::afegir($pdo, 'rederivar_tot', 'Recalculada província/comarca de totes les entrades');
            respon(['ok' => true]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            respon(['error' => $e->getMessage()]);
        }
        break;

    case 'editar_pax':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $id = (int)($_POST['entrada_id'] ?? 0);
        $nouPax = (int)($_POST['nou_pax'] ?? -1);
        if ($id <= 0 || $nouPax < 0) { respon(['error' => 'Paràmetres invàlids']); break; }
        try {
            $stmt = $pdo->prepare('UPDATE entrades SET pax = ? WHERE id = ?');
            $stmt->execute([$nouPax, $id]);
            Registre::afegir($pdo, 'pax_editat', "Entrada #$id -> pax = $nouPax (probable duplicat escolar corregit)");
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    // ---------- Revisió massiva ----------

    case 'aplicar_tots_suggeriments':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $tipus = $_POST['tipus'] ?? '';
        if (!in_array($tipus, ['provincia_no', 'comarca_no'], true)) {
            respon(['error' => 'Tipus no vàlid per aquesta acció.']); break;
        }
        try {
            $cond = condicioError($tipus) . " AND " . filtreDescartats($pdo, $tipus);
            $sql = "SELECT codi_postal, pais FROM entrades WHERE $cond
                    GROUP BY codi_postal, pais";
            $grups = $pdo->query($sql)->fetchAll();

            $pdo->beginTransaction();
            $aplicats = 0;
            $senseSuggeriment = 0;

            foreach ($grups as $g) {
                $sug = suggerirPerCp($pdo, $g['codi_postal'] ?? '');
                $sets = [];
                $params = [];
                if ($tipus === 'provincia_no' && $sug['provincia']) {
                    $sets[] = 'provincia_derivada = ?'; $params[] = $sug['provincia'];
                }
                if ($tipus === 'comarca_no' && $sug['comarca']) {
                    $sets[] = 'comarca_derivada = ?'; $params[] = $sug['comarca'];
                    if ($sug['provincia']) { $sets[] = 'provincia_derivada = COALESCE(provincia_derivada, ?)'; $params[] = $sug['provincia']; }
                }
                if (empty($sets)) { $senseSuggeriment++; continue; }

                $sqlUpd = "UPDATE entrades SET " . implode(', ', $sets) . " WHERE codi_postal <=> ? AND pais <=> ?";
                $params[] = $g['codi_postal'];
                $params[] = $g['pais'];
                $pdo->prepare($sqlUpd)->execute($params);
                $aplicats++;

                if ($g['codi_postal'] && ctype_digit((string)$g['codi_postal'])) {
                    $cpNorm = str_pad($g['codi_postal'], 5, '0', STR_PAD_LEFT);
                    $pdo->prepare(
                        "INSERT INTO correccions_cp (codi_postal, provincia, comarca) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                           provincia = COALESCE(VALUES(provincia), provincia),
                           comarca = COALESCE(VALUES(comarca), comarca)"
                    )->execute([$cpNorm, $sug['provincia'], $sug['comarca']]);
                }
            }

            $pdo->commit();
            Registre::afegir($pdo, 'suggeriments_massius', "Tipus: $tipus. Aplicats: $aplicats, sense suggeriment: $senseSuggeriment");
            respon(['ok' => true, 'aplicats' => $aplicats, 'sense_suggeriment' => $senseSuggeriment]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'netejar_cp_automatic':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        try {
            $cond = condicioError('cp_format') . " AND " . filtreDescartats($pdo, 'cp_format');
            $sql = "SELECT DISTINCT codi_postal, pais FROM entrades WHERE $cond";
            $grups = $pdo->query($sql)->fetchAll();

            $pdo->beginTransaction();
            $netejats = 0;
            $ambigus = 0;

            foreach ($grups as $g) {
                $digits = preg_replace('/[^0-9]/', '', (string)$g['codi_postal']);
                $net = null;

                if (strlen($digits) === 5) {
                    $net = $digits;
                } elseif (strlen($digits) === 6) {
                    $net = candidatPerSupressio($pdo, $digits);
                }

                if ($net === null) { $ambigus++; continue; }

                [$prov, $com] = [null, null];
                $esEsp = in_array(mb_strtolower((string)$g['pais']), ['españa', 'espanya'], true);
                if ($esEsp) {
                    $prefix = substr($net, 0, 2);
                    $prov = $pdo->query("SELECT nom FROM provincies WHERE codi_prefix = " . $pdo->quote($prefix))->fetchColumn() ?: null;
                    $stmt = $pdo->prepare(
                        'SELECT c.nom FROM cp_municipis cm
                         JOIN municipis m ON m.nom_municipi = cm.nom_municipi
                         JOIN comarques c ON c.codi = m.codi_comarca
                         WHERE cm.codi_postal = ? LIMIT 1'
                    );
                    $stmt->execute([$net]);
                    $com = $stmt->fetchColumn() ?: null;
                }

                $pdo->prepare(
                    'UPDATE entrades SET codi_postal = ?, provincia_derivada = ?, comarca_derivada = ?
                     WHERE codi_postal <=> ? AND pais <=> ?'
                )->execute([$net, $prov, $com, $g['codi_postal'], $g['pais']]);
                $netejats++;
            }

            $pdo->commit();
            Registre::afegir($pdo, 'neteja_cp_format', "Nets i corregits: $netejats, ambigus (sense tocar): $ambigus");
            respon(['ok' => true, 'netejats' => $netejats, 'ambigus' => $ambigus]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'reclassificar_cp_estrangers':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        try {
            $cond = condicioError('provincia_no') . " AND " . filtreDescartats($pdo, 'provincia_no');
            $sql = "SELECT DISTINCT codi_postal, pais FROM entrades WHERE $cond";
            $grups = $pdo->query($sql)->fetchAll();

            $pdo->beginTransaction();
            $reclassificats = 0;

            foreach ($grups as $g) {
                $cp = $g['codi_postal'] ?? '';
                if (strlen($cp) < 2) continue;
                $prefix = substr(str_pad($cp, 5, '0', STR_PAD_LEFT), 0, 2);
                if (!isset(DEPARTAMENTS_FR[$prefix])) continue;

                $pdo->prepare(
                    'UPDATE entrades
                     SET pais = ?, pais_es_espanya = 0, provincia_derivada = NULL, comarca_derivada = NULL
                     WHERE codi_postal <=> ? AND pais <=> ?'
                )->execute(['Francia', $g['codi_postal'], $g['pais']]);
                $reclassificats++;
            }

            $pdo->commit();
            Registre::afegir($pdo, 'reclassificacio_fr', "Reclassificats com a Francia: $reclassificats casos");
            respon(['ok' => true, 'reclassificats' => $reclassificats]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'descartar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $tipus = $_POST['tipus'] ?? '';
        $cp = ($_POST['codi_postal'] ?? '') === '' ? null : $_POST['codi_postal'];
        $pais = ($_POST['pais'] ?? '') === '' ? null : $_POST['pais'];
        $motiu = trim($_POST['motiu'] ?? '');
        try {
            $stmt = $pdo->prepare('INSERT INTO errors_descartats (tipus, codi_postal, pais, motiu) VALUES (?,?,?,?)');
            $stmt->execute([$tipus, $cp, $pais, $motiu ?: null]);
            Registre::afegir($pdo, 'error_descartat', "Tipus: $tipus, CP: $cp, país: $pais. Motiu: $motiu");
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    case 'llistar_descartats':
        respon($pdo->query(
            "SELECT id, tipus, codi_postal, pais, motiu, data_descart
             FROM errors_descartats ORDER BY data_descart DESC"
        )->fetchAll());
        break;

    case 'restaurar_descartat':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); respon(['error' => 'Cal POST']); break; }
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare('DELETE FROM errors_descartats WHERE id = ?')->execute([$id]);
            Registre::afegir($pdo, 'descart_restaurat', "Descart #$id restaurat (torna a ser un cas pendent)");
            respon(['ok' => true]);
        } catch (Throwable $e) {
            http_response_code(500); respon(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(400);
        respon(['error' => 'Acció desconeguda']);
}

/** Deriva [provincia, comarca] d'un CP normalitzat de 5 dígits */
function derivarDeCp(PDO $pdo, string $cpNorm): array
{
    $prefix = substr($cpNorm, 0, 2);
    $stmt = $pdo->prepare('SELECT nom FROM provincies WHERE codi_prefix = ?');
    $stmt->execute([$prefix]);
    $prov = $stmt->fetchColumn() ?: null;

    $stmt = $pdo->prepare(
        'SELECT c.nom FROM cp_municipis cm
         JOIN municipis m ON m.nom_municipi = cm.nom_municipi
         JOIN comarques c ON c.codi = m.codi_comarca
         WHERE cm.codi_postal = ? LIMIT 1'
    );
    $stmt->execute([$cpNorm]);
    $com = $stmt->fetchColumn() ?: null;

    return [$prov, $com];
}

/**
 * Suggereix província i comarca per a un CP que no s'ha pogut resoldre,
 * buscant el codi postal de referència numèricament més proper dins la
 * mateixa província (els CP són geogràficament seqüencials, així que el
 * més proper sol ser de la mateixa zona).
 */
function suggerirPerCp(PDO $pdo, ?string $cp): array
{
    $buit = ['provincia' => null, 'comarca' => null, 'cp_ref' => null, 'municipi' => null];
    if (!$cp || !ctype_digit($cp) || strlen($cp) < 4) return $buit;
    $cpNorm = str_pad($cp, 5, '0', STR_PAD_LEFT);
    $prefix = substr($cpNorm, 0, 2);

    $stmt = $pdo->prepare('SELECT nom FROM provincies WHERE codi_prefix = ?');
    $stmt->execute([$prefix]);
    $provincia = $stmt->fetchColumn() ?: null;

    $stmt = $pdo->prepare(
        "SELECT c.nom AS comarca, cm.codi_postal AS cp_ref, m.nom_municipi AS municipi
         FROM cp_municipis cm
         JOIN municipis m ON m.nom_municipi = cm.nom_municipi
         JOIN comarques c ON c.codi = m.codi_comarca
         WHERE LEFT(cm.codi_postal,2) = ?
         ORDER BY ABS(CAST(cm.codi_postal AS SIGNED) - CAST(? AS SIGNED))
         LIMIT 1"
    );
    $stmt->execute([$prefix, $cpNorm]);
    $ref = $stmt->fetch();

    return [
        'provincia' => $provincia,
        'comarca'   => $ref['comarca'] ?? null,
        'cp_ref'    => $ref['cp_ref'] ?? null,
        'municipi'  => $ref['municipi'] ?? null,
    ];
}

/**
 * Detecta possibles duplicats de grups escolars (canal CGR).
 *
 * Patró d'error: una mateixa escola registra la visita guiada (paquet o
 * visita escolar) I la visita al volcà Montsacopa amb pax elevat en totes
 * dues, en comptes de desdoblar-ho (p. ex. 49 + 1). Si el valor més petit
 * de les dues activitats és clarament gran, probablement s'ha comptat el
 * grup dues vegades.
 */
function detectarDuplicatsEscolars(PDO $pdo): array
{
    $LLINDAR = 3;

    $sql = "
        SELECT e.id, e.nom_client, e.pax, e.any_visita, e.mes_visita,
               p.nom_original AS producte
        FROM entrades e
        JOIN canals c ON c.id = e.canal_id
        LEFT JOIN productes p ON p.id = e.producte_id
        WHERE c.codi = 'CGR'
          AND e.nom_client IS NOT NULL AND e.nom_client <> ''
          AND (
                p.nom_original LIKE '%Montsacopa%'
             OR p.nom_original LIKE '%Taller%'
             OR p.nom_original LIKE '%escolar%'
             OR p.nom_original LIKE '%Visita guiada%'
          )
        ORDER BY e.nom_client, e.any_visita, e.mes_visita
    ";
    $rows = $pdo->query($sql)->fetchAll();

    $grups = [];
    foreach ($rows as $r) {
        $clau = mb_strtolower(trim($r['nom_client'])) . '|' . $r['any_visita'] . '|' . $r['mes_visita'];
        $grups[$clau][] = $r;
    }

    $sospitosos = [];
    foreach ($grups as $items) {
        if (count($items) < 2) continue;

        $montsacopa = null;
        $principal = null;
        foreach ($items as $it) {
            $prod = $it['producte'] ?? '';
            if (stripos($prod, 'Montsacopa') !== false) {
                if (!$montsacopa || $it['pax'] > $montsacopa['pax']) $montsacopa = $it;
            } else {
                if (!$principal || $it['pax'] > $principal['pax']) $principal = $it;
            }
        }
        if (!$montsacopa || !$principal) continue;

        $menor = min((int)$montsacopa['pax'], (int)$principal['pax']);
        if ($menor > $LLINDAR) {
            $sospitosos[] = [
                'escola'         => $principal['nom_client'],
                'any'            => (int)$principal['any_visita'],
                'mes'            => (int)$principal['mes_visita'],
                'principal_id'   => (int)$principal['id'],
                'principal_prod' => $principal['producte'],
                'principal_pax'  => (int)$principal['pax'],
                'montsacopa_id'  => (int)$montsacopa['id'],
                'montsacopa_prod'=> $montsacopa['producte'],
                'montsacopa_pax' => (int)$montsacopa['pax'],
                'pax_sospitos'   => $menor,
            ];
        }
    }

    usort($sospitosos, fn($a, $b) => $b['pax_sospitos'] - $a['pax_sospitos']);
    return $sospitosos;
}
