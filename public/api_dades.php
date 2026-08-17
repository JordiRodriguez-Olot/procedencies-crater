<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../src/Auth.php';
Auth::requerirLoginApi();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();
$accio = $_GET['accio'] ?? '';

// ---------- Filtres comuns ----------
$grups = $_GET['grups'] ?? [];      // array de noms de grup de producte
$anys  = $_GET['anys'] ?? [];       // array d'anys (ex: [2023,2024])
$mesos = $_GET['mesos'] ?? [];      // array de mesos (1-12)

$where = [];
$params = [];

if (!empty($grups)) {
    $in = implode(',', array_fill(0, count($grups), '?'));
    $where[] = "COALESCE(mp.grup, p.nom_original) IN ($in)";
    $params = array_merge($params, $grups);
}
if (!empty($anys)) {
    $in = implode(',', array_fill(0, count($anys), '?'));
    $where[] = "e.any_visita IN ($in)";
    $params = array_merge($params, $anys);
}
if (!empty($mesos)) {
    $in = implode(',', array_fill(0, count($mesos), '?'));
    $where[] = "e.mes_visita IN ($in)";
    $params = array_merge($params, $mesos);
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$fromBase = "FROM entrades e
    LEFT JOIN productes p ON p.id = e.producte_id
    LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
    LEFT JOIN canals c ON c.id = e.canal_id
    $whereSql";

function query(PDO $pdo, string $sql, array $params): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function respon($dades): void
{
    echo json_encode($dades, JSON_UNESCAPED_UNICODE);
}

switch ($accio) {

    case 'productes_disponibles':
        respon(query($pdo, "
            SELECT DISTINCT COALESCE(mp.grup, p.nom_original) AS grup
            FROM productes p
            LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
            ORDER BY grup
        ", []));
        break;

    case 'periodes_disponibles':
        respon(query($pdo, "
            SELECT DISTINCT any_visita AS any_, mes_visita AS mes
            FROM entrades ORDER BY any_ DESC, mes DESC
        ", []));
        break;

    case 'usuaris_per_pais':
        respon(query($pdo, "
            SELECT e.pais AS etiqueta, SUM(e.pax) AS total
            $fromBase " . ($where ? 'AND' : 'WHERE') . " e.pais IS NOT NULL AND e.pais <> ''
            GROUP BY e.pais ORDER BY total DESC
        ", $params));
        break;

    case 'usuaris_per_pais_sense_espanya':
        respon(query($pdo, "
            SELECT e.pais AS etiqueta, SUM(e.pax) AS total
            $fromBase " . ($where ? 'AND' : 'WHERE') . " e.pais_es_espanya = 0 AND e.pais IS NOT NULL AND e.pais <> ''
            GROUP BY e.pais ORDER BY total DESC
        ", $params));
        break;

    case 'usuaris_per_provincia':
        respon(query($pdo, "
            SELECT e.provincia_derivada AS etiqueta, SUM(e.pax) AS total
            $fromBase " . ($where ? 'AND' : 'WHERE') . " e.provincia_derivada IS NOT NULL
            GROUP BY e.provincia_derivada ORDER BY total DESC
        ", $params));
        break;

    case 'usuaris_per_comarca':
        respon(query($pdo, "
            SELECT e.comarca_derivada AS etiqueta, SUM(e.pax) AS total
            $fromBase " . ($where ? 'AND' : 'WHERE') . " e.comarca_derivada IS NOT NULL
            GROUP BY e.comarca_derivada ORDER BY total DESC
        ", $params));
        break;

    case 'percentatge_canals':
        respon(query($pdo, "
            SELECT c.nom AS etiqueta, SUM(e.pax) AS total
            $fromBase
            GROUP BY c.nom
        ", $params));
        break;

    // Evolució mensual (respecta el filtre de producte, ignora any/mes
    // perquè l'objectiu és veure tota la sèrie temporal disponible)
    case 'evolucio_temporal':
        $whereGrup = [];
        $paramsGrup = [];
        if (!empty($grups)) {
            $in = implode(',', array_fill(0, count($grups), '?'));
            $whereGrup[] = "COALESCE(mp.grup, p.nom_original) IN ($in)";
            $paramsGrup = $grups;
        }
        $whereGrupSql = $whereGrup ? ('WHERE ' . implode(' AND ', $whereGrup)) : '';
        respon(query($pdo, "
            SELECT e.anymes AS anymes, SUM(e.pax) AS total
            FROM entrades e
            LEFT JOIN productes p ON p.id = e.producte_id
            LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
            $whereGrupSql
            GROUP BY e.anymes ORDER BY e.anymes ASC
        ", $paramsGrup));
        break;

    // Comparativa entre dos períodes concrets (per als KPIs comparatius).
    // Rep: anymes_a i anymes_b (format YYYYMM). Respecta el filtre de producte.
    case 'comparativa':
        $anymesA = (int)($_GET['anymes_a'] ?? 0);
        $anymesB = (int)($_GET['anymes_b'] ?? 0);
        if ($anymesA <= 0 || $anymesB <= 0) {
            http_response_code(400);
            respon(['error' => 'Cal indicar anymes_a i anymes_b']);
            break;
        }

        $wg = [];
        $pg = [];
        if (!empty($grups)) {
            $in = implode(',', array_fill(0, count($grups), '?'));
            $wg[] = "COALESCE(mp.grup, p.nom_original) IN ($in)";
            $pg = $grups;
        }
        $wgSql = $wg ? (' AND ' . implode(' AND ', $wg)) : '';

        $metriques = function (int $anymes) use ($pdo, $wgSql, $pg) {
            $sql = "SELECT
                        COALESCE(SUM(e.pax),0) AS total,
                        COALESCE(SUM(CASE WHEN e.pais_es_espanya = 0 THEN e.pax ELSE 0 END),0) AS internacional,
                        COUNT(DISTINCT e.pais) AS paisos,
                        COUNT(DISTINCT e.comarca_derivada) AS comarques
                    FROM entrades e
                    LEFT JOIN productes p ON p.id = e.producte_id
                    LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
                    WHERE e.anymes = ? $wgSql";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$anymes], $pg));
            return $stmt->fetch();
        };

        respon([
            'a' => ['anymes' => $anymesA] + $metriques($anymesA),
            'b' => ['anymes' => $anymesB] + $metriques($anymesB),
        ]);
        break;

    // Comparativa entre dos ANYS COMPLETS (tots els mesos de cada any),
    // per a informes anuals. Mateixa forma de resposta que 'comparativa'.
    case 'comparativa_any':
        $anyA = (int)($_GET['any_a'] ?? 0);
        $anyB = (int)($_GET['any_b'] ?? 0);
        if ($anyA <= 0 || $anyB <= 0) {
            http_response_code(400);
            respon(['error' => 'Cal indicar any_a i any_b']);
            break;
        }

        $wg = [];
        $pg = [];
        if (!empty($grups)) {
            $in = implode(',', array_fill(0, count($grups), '?'));
            $wg[] = "COALESCE(mp.grup, p.nom_original) IN ($in)";
            $pg = $grups;
        }
        $wgSql = $wg ? (' AND ' . implode(' AND ', $wg)) : '';

        $metriquesAny = function (int $any) use ($pdo, $wgSql, $pg) {
            $sql = "SELECT
                        COALESCE(SUM(e.pax),0) AS total,
                        COALESCE(SUM(CASE WHEN e.pais_es_espanya = 0 THEN e.pax ELSE 0 END),0) AS internacional,
                        COUNT(DISTINCT e.pais) AS paisos,
                        COUNT(DISTINCT e.comarca_derivada) AS comarques
                    FROM entrades e
                    LEFT JOIN productes p ON p.id = e.producte_id
                    LEFT JOIN mapeig_productes mp ON mp.nom_original = p.nom_original
                    WHERE e.any_visita = ? $wgSql";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge([$any], $pg));
            return $stmt->fetch();
        };

        respon([
            'a' => ['any' => $anyA] + $metriquesAny($anyA),
            'b' => ['any' => $anyB] + $metriquesAny($anyB),
        ]);
        break;

    default:
        http_response_code(400);
        respon(['error' => 'Acció desconeguda']);
}
