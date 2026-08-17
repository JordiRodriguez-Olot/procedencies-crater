<?php

/** Excepció llançada quan ja existeixen dades per aquest canal + període */
class ImportDuplicatException extends RuntimeException
{
    public function __construct(
        public readonly int $anymes,
        public readonly int $filesExistents,
        public readonly int $paxExistents,
    ) {
        parent::__construct("Ja existeixen dades importades per aquest canal i període ($anymes).");
    }
}

/**
 * Importa els Excels exportats per Clorian per als 3 canals:
 *  - CGR i ONLINE:  Nombre, Producto, Email, Idioma, Procedencia,
 *                   Comunidad, Provincia, Código postal, Pax
 *                   (Email mai es guarda; Nombre només per a CGR — vegeu
 *                   la nota RGPD més avall)
 *  - TAQUILLA:      pais, cp, pax   (sense producte ni dades de client)
 *
 * L'importador detecta automàticament la fila de capçaleres cercant
 * paraules clau, i és tolerant a petites variacions de columnes.
 */
class Importer
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $mode 'preguntar' (per defecte: llança ImportDuplicatException
     *                     si ja hi ha dades per aquest canal+període),
     *                     'substituir' (esborra les dades existents d'aquest
     *                     canal+període abans d'importar les noves), o
     *                     'afegir' (importa igualment, sumant-se a les existents).
     */
    public function importar(string $filepath, string $canalCodi, string $nomFitxer, string $mode = 'preguntar'): array
    {
        $reader = new SimpleXlsxReader($filepath);
        $nomFull = $this->trobarNomFull($reader->nomsFulls());
        $rows = $reader->llegirFull($nomFull);

        // Data de visita (text lliure a les primeres files, tipus
        // "Fecha visita: 2022-10-01~2022-10-31")
        [$dataInici, $dataFi] = $this->extreureDataVisita($rows);

        $headerIdx = $this->trobarFilaCapcalera($rows);
        if ($headerIdx === null) {
            throw new RuntimeException('No s\'ha trobat la fila de capçaleres a ' . $nomFitxer);
        }
        $headers = array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[$headerIdx]);
        $colIdx = array_flip($headers);

        $col = function (string ...$noms) use ($colIdx) {
            foreach ($noms as $nom) {
                if (isset($colIdx[$nom])) return $colIdx[$nom];
            }
            return null;
        };

        $cNom       = $col('nombre');
        $cProducte  = $col('producto');
        // Nota RGPD: NO llegim la columna "Email" de l'Excel; el correu
        // electrònic no s'utilitza enlloc de l'aplicació i no s'ha de
        // guardar mai (principi de minimització de dades).
        $cIdioma    = $col('idioma');
        $cPais      = $col('procedencia', 'pais');
        $cComunitat = $col('comunidad');
        $cProvincia = $col('provincia');
        $cCp        = $col('código postal', 'codigo postal', 'cp');
        $cPax       = $col('pax');

        if ($cPax === null || $cCp === null) {
            throw new RuntimeException('Format d\'Excel no reconegut a ' . $nomFitxer);
        }

        $any = (int)date('Y', strtotime($dataInici ?? 'now'));
        $mes = (int)date('n', strtotime($dataInici ?? 'now'));
        $anymes = $any * 100 + $mes;

        $canalId = (int)$this->pdo->query(
            "SELECT id FROM canals WHERE codi = " . $this->pdo->quote($canalCodi)
        )->fetchColumn();

        // Comprovació de duplicats: ja hi ha dades d'aquest canal+període?
        $stmtExist = $this->pdo->prepare(
            'SELECT COUNT(*) AS files, COALESCE(SUM(pax),0) AS pax
             FROM entrades WHERE canal_id = ? AND anymes = ? AND es_manual = 0'
        );
        $stmtExist->execute([$canalId, $anymes]);
        $existents = $stmtExist->fetch();

        if ((int)$existents['files'] > 0 && $mode === 'preguntar') {
            throw new ImportDuplicatException($anymes, (int)$existents['files'], (int)$existents['pax']);
        }

        $this->pdo->beginTransaction();

        if ((int)$existents['files'] > 0 && $mode === 'substituir') {
            $this->pdo->prepare('DELETE FROM entrades WHERE canal_id = ? AND anymes = ? AND es_manual = 0')
                ->execute([$canalId, $anymes]);
            // Neteja importacions que han quedat òrfenes (sense cap entrada associada)
            $this->pdo->exec(
                'DELETE FROM importacions WHERE id NOT IN (SELECT DISTINCT importacio_id FROM entrades)'
            );
        }

        $stmtImport = $this->pdo->prepare(
            'INSERT INTO importacions (canal_id, nom_fitxer, data_visita_inici, data_visita_fi)
             SELECT id, ?, ?, ? FROM canals WHERE codi = ?'
        );
        $stmtImport->execute([$nomFitxer, $dataInici, $dataFi, $canalCodi]);
        $importacioId = (int)$this->pdo->lastInsertId();

        $insert = $this->pdo->prepare(
            'INSERT INTO entrades
             (importacio_id, canal_id, producte_id, nom_client, idioma, pais,
              comunitat, provincia, codi_postal, pax, any_visita, mes_visita, anymes,
              provincia_derivada, comarca_derivada, pais_es_espanya)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        // RGPD: el nom del client només es conserva per al canal CGR
        // (habitualment nom d'entitat/escola, necessari per detectar
        // duplicats de grups escolars). Per a Online i Taquilla mai es
        // guarda, ja que correspondria a una persona física individual
        // i no té cap ús a l'aplicació.
        $conservarNom = ($canalCodi === 'CGR');

        $comptador = 0;

        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $pax = $cPax !== null ? (int)($r[$cPax] ?? 0) : 0;
            if ($pax <= 0) continue;

            $cp = $cCp !== null ? trim((string)($r[$cCp] ?? '')) : '';
            // Protecció defensiva: si per algun motiu (fila de totals,
            // cel·la fusionada...) el valor és anormalment llarg, el
            // tallem enlloc de deixar que trenqui tota la importació.
            if (strlen($cp) > 30) $cp = substr($cp, 0, 30);

            $pais = $cPais !== null ? trim((string)($r[$cPais] ?? '')) : '';
            $esEspanya = (mb_strtolower($pais) === 'españa' || mb_strtolower($pais) === 'espanya') ? 1 : 0;

            $producteNom = $cProducte !== null ? trim((string)($r[$cProducte] ?? '')) : 'Taquilla';
            if ($producteNom === '') $producteNom = 'Taquilla';
            $producteId = $this->obtenirProducteId($producteNom);

            [$provinciaDerivada, $comarcaDerivada] = $this->derivarProvinciaComarca($cp, $esEspanya);

            $insert->execute([
                $importacioId,
                $canalId,
                $producteId,
                ($conservarNom && $cNom !== null) ? trim((string)($r[$cNom] ?? '')) : null,
                $cIdioma !== null ? trim((string)($r[$cIdioma] ?? '')) : null,
                $pais ?: null,
                $cComunitat !== null ? trim((string)($r[$cComunitat] ?? '')) : null,
                $cProvincia !== null ? trim((string)($r[$cProvincia] ?? '')) : null,
                $cp ?: null,
                $pax,
                $any,
                $mes,
                $anymes,
                $provinciaDerivada,
                $comarcaDerivada,
                $esEspanya,
            ]);
            $comptador++;
        }

        $upd = $this->pdo->prepare('UPDATE importacions SET files_importades = ? WHERE id = ?');
        $upd->execute([$comptador, $importacioId]);

        $this->pdo->commit();

        return ['importacio_id' => $importacioId, 'files' => $comptador, 'anymes' => $anymes];
    }

    private function trobarNomFull(array $nomsFulls): string
    {
        foreach ($nomsFulls as $nom) {
            $t = mb_strtolower($nom);
            if (str_contains($t, 'detalle') || str_contains($t, 'resumen')) {
                return $nom;
            }
        }
        return $nomsFulls[0] ?? throw new RuntimeException('El fitxer Excel no té cap full de dades.');
    }

    private function extreureDataVisita(array $rows): array
    {
        foreach (array_slice($rows, 0, 10) as $row) {
            $text = (string)($row[0] ?? '');
            if (preg_match('/(\d{4}-\d{2}-\d{2})~(\d{4}-\d{2}-\d{2})/', $text, $m)) {
                return [$m[1], $m[2]];
            }
        }
        return [null, null];
    }

    private function trobarFilaCapcalera(array $rows): ?int
    {
        foreach ($rows as $idx => $row) {
            $joined = mb_strtolower(implode('|', array_map('strval', $row)));
            if (str_contains($joined, 'pax') && (str_contains($joined, 'producto') || str_contains($joined, 'pais') || str_contains($joined, 'cp'))) {
                return $idx;
            }
        }
        return null;
    }

    private function obtenirProducteId(string $nomOriginal): int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM productes WHERE nom_original = ?');
        $stmt->execute([$nomOriginal]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $ins = $this->pdo->prepare('INSERT INTO productes (nom_original) VALUES (?)');
        $ins->execute([$nomOriginal]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Retorna [provincia, comarca] a partir del codi postal */
    private function derivarProvinciaComarca(string $cp, int $esEspanya): array
    {
        if (!$esEspanya || strlen($cp) < 2 || !ctype_digit($cp)) {
            return [null, null];
        }
        $prefix = substr(str_pad($cp, 5, '0', STR_PAD_LEFT), 0, 2);
        $cpNorm = str_pad($cp, 5, '0', STR_PAD_LEFT);

        static $cacheProv = [];
        if (!isset($cacheProv[$prefix])) {
            $stmt = $this->pdo->prepare('SELECT nom FROM provincies WHERE codi_prefix = ?');
            $stmt->execute([$prefix]);
            $cacheProv[$prefix] = $stmt->fetchColumn() ?: null;
        }
        $provincia = $cacheProv[$prefix];

        static $cacheCom = [];
        if (!isset($cacheCom[$cpNorm])) {
            $stmt = $this->pdo->prepare(
                'SELECT c.nom FROM cp_municipis cm
                 JOIN municipis m ON m.nom_municipi = cm.nom_municipi
                 JOIN comarques c ON c.codi = m.codi_comarca
                 WHERE cm.codi_postal = ? LIMIT 1'
            );
            $stmt->execute([$cpNorm]);
            $cacheCom[$cpNorm] = $stmt->fetchColumn() ?: null;
        }
        $comarca = $cacheCom[$cpNorm];

        // Les correccions manuals tenen prioritat sobre la derivació
        // automàtica: si algú ha corregit aquest CP des de la gestió
        // d'errors, apliquem el seu valor.
        static $cacheCorr = [];
        if (!array_key_exists($cpNorm, $cacheCorr)) {
            $stmt = $this->pdo->prepare('SELECT provincia, comarca FROM correccions_cp WHERE codi_postal = ?');
            $stmt->execute([$cpNorm]);
            $cacheCorr[$cpNorm] = $stmt->fetch() ?: null;
        }
        if ($cacheCorr[$cpNorm]) {
            if (!empty($cacheCorr[$cpNorm]['provincia'])) $provincia = $cacheCorr[$cpNorm]['provincia'];
            if (!empty($cacheCorr[$cpNorm]['comarca']))   $comarca   = $cacheCorr[$cpNorm]['comarca'];
        }

        return [$provincia, $comarca];
    }
}
