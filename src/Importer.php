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
 *  - TAQUILLA:      pais, cp, pax  (i producte, si hi és)
 *
 * El procés té dues fases:
 *  1) llegir(): interpreta el fitxer en memòria, sense tocar la BD. La fa
 *     servir ControlQualitat per mostrar l'informe de validació abans de
 *     desar res.
 *  2) importar(): torna a llegir el fitxer i el desa dins una transacció.
 */
class Importer
{
    private PDO $pdo;
    private ?bool $teSignatura = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Llegeix el fitxer sense escriure res a la BD.
     *
     * @return array{data_inici:?string, data_fi:?string, anymes:?int, multi_mes:bool,
     *               registres:list<array<string,mixed>>, ignorades:int, cp_completats:int}
     */
    public function llegir(string $filepath): array
    {
        $reader = new SimpleXlsxReader($filepath);
        $rows = $reader->llegirFull($this->trobarNomFull($reader->nomsFulls()));

        // Data de visita (text lliure a les primeres files, tipus
        // "Fecha visita: 2022-10-01~2022-10-31")
        [$dataInici, $dataFi] = $this->extreureDataVisita($rows);

        $headerIdx = $this->trobarFilaCapcalera($rows);
        if ($headerIdx === null) {
            throw new RuntimeException("No s'ha trobat la fila de capçaleres. És un Excel exportat de Clorian?");
        }
        $colIdx = array_flip(array_map(fn($h) => mb_strtolower(trim((string)$h)), $rows[$headerIdx]));
        $col = function (string ...$noms) use ($colIdx): ?int {
            foreach ($noms as $nom) {
                if (isset($colIdx[$nom])) return $colIdx[$nom];
            }
            return null;
        };

        $cNom       = $col('nombre');
        $cProducte  = $col('producto', 'producte');
        // Nota RGPD: el correu NOMÉS es llegeix en memòria per detectar
        // reserves repetides dins el mateix fitxer. No es desa mai.
        $cEmail     = $col('email', 'correo', 'correu');
        $cIdioma    = $col('idioma');
        $cPais      = $col('procedencia', 'pais');
        $cComunitat = $col('comunidad');
        $cProvincia = $col('provincia');
        $cCp        = $col('código postal', 'codigo postal', 'cp');
        $cPax       = $col('pax');

        if ($cPax === null || $cCp === null) {
            throw new RuntimeException("Format d'Excel no reconegut: hi falten les columnes de pax o de codi postal.");
        }

        // Valor de text d'una cel·la, net i tallat a la mida de la columna de la BD
        $txt = static function (array $fila, ?int $c, int $max): string {
            if ($c === null) return '';
            return mb_substr(trim((string)($fila[$c] ?? '')), 0, $max);
        };

        $registres = [];
        $ignorades = 0;
        $cpCompletats = 0;
        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            if (trim(implode('', array_map('strval', $r))) === '') continue; // fila buida
            $pax = (int)($r[$cPax] ?? 0);
            if ($pax <= 0) { $ignorades++; continue; }

            $pais = $txt($r, $cPais, 80);
            $esEspanya = in_array(mb_strtolower($pais), ['españa', 'espanya', 'spain'], true) ? 1 : 0;

            // Protecció defensiva: un valor anormalment llarg (fila de totals,
            // cel·la fusionada...) es talla en lloc de trencar la importació.
            $cp = $txt($r, $cCp, 30);
            if ($cp === '0') $cp = '';
            // Excel sovint perd el zero inicial dels CP espanyols (08500 -> 8500)
            if ($esEspanya && strlen($cp) === 4 && ctype_digit($cp)) {
                $cp = '0' . $cp;
                $cpCompletats++;
            }

            $producte = $txt($r, $cProducte, 150);

            $registres[] = [
                'fila'       => $i + 1, // número de fila a l'Excel
                'nom'        => $txt($r, $cNom, 200),
                'email'      => mb_strtolower($txt($r, $cEmail, 200)),
                'producte'   => $producte !== '' ? $producte : 'Taquilla',
                'idioma'     => $txt($r, $cIdioma, 30),
                'pais'       => $pais,
                'comunitat'  => $txt($r, $cComunitat, 80),
                'provincia'  => $txt($r, $cProvincia, 80),
                'cp'         => $cp,
                'pax'        => $pax,
                'es_espanya' => $esEspanya,
            ];
        }

        $anymes = null;
        $multiMes = false;
        if ($dataInici) {
            $anymes = (int)date('Ym', strtotime($dataInici));
            $multiMes = $dataFi !== null && (int)date('Ym', strtotime($dataFi)) !== $anymes;
        }

        return [
            'data_inici'    => $dataInici,
            'data_fi'       => $dataFi,
            'anymes'        => $anymes,
            'multi_mes'     => $multiMes,
            'registres'     => $registres,
            'ignorades'     => $ignorades,
            'cp_completats' => $cpCompletats,
        ];
    }

    /**
     * @param string $mode 'preguntar' (per defecte: llança ImportDuplicatException
     *                     si ja hi ha dades per aquest canal+període),
     *                     'substituir' (esborra les dades existents d'aquest
     *                     canal+període abans d'importar les noves), o
     *                     'afegir' (importa igualment, sumant-se a les existents).
     * @param int|null $anymesForcat període triat a mà quan el fitxer no el porta
     * @param bool $ometreDuplicats deixa només una fila de cada grup de files repetides
     */
    public function importar(string $filepath, string $canalCodi, string $nomFitxer, string $mode = 'preguntar',
                             ?int $anymesForcat = null, bool $ometreDuplicats = false): array
    {
        $lectura = $this->llegir($filepath);
        $anymes = $lectura['anymes'] ?? $anymesForcat;
        if ($anymes === null || $anymes % 100 < 1 || $anymes % 100 > 12) {
            throw new RuntimeException("No s'ha pogut saber de quin mes són les dades. Tria el període abans d'importar.");
        }
        $any = intdiv($anymes, 100);
        $mes = $anymes % 100;
        $dataInici = $lectura['data_inici'] ?? sprintf('%04d-%02d-01', $any, $mes);
        $dataFi = $lectura['data_fi'] ?? date('Y-m-t', strtotime($dataInici));

        $registres = $lectura['registres'];
        $omeses = 0;
        if ($ometreDuplicats) {
            $vistos = [];
            $filtrats = [];
            foreach ($registres as $r) {
                $clau = ControlQualitat::clauDuplicat($canalCodi, $r);
                if (isset($vistos[$clau])) { $omeses++; continue; }
                $vistos[$clau] = true;
                $filtrats[] = $r;
            }
            $registres = $filtrats;
        }
        if (!$registres) {
            throw new RuntimeException('El fitxer no té cap fila amb pax per importar.');
        }

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
        try {
            if ((int)$existents['files'] > 0 && $mode === 'substituir') {
                $this->pdo->prepare('DELETE FROM entrades WHERE canal_id = ? AND anymes = ? AND es_manual = 0')
                    ->execute([$canalId, $anymes]);
                // Neteja importacions que han quedat òrfenes (sense cap entrada associada)
                $this->pdo->exec(
                    'DELETE FROM importacions WHERE id NOT IN (SELECT DISTINCT importacio_id FROM entrades)'
                );
            }

            if ($this->teSignatura()) {
                $this->pdo->prepare(
                    'INSERT INTO importacions (canal_id, nom_fitxer, data_visita_inici, data_visita_fi, signatura)
                     VALUES (?, ?, ?, ?, ?)'
                )->execute([$canalId, $nomFitxer, $dataInici, $dataFi, ControlQualitat::signatura($canalCodi, $registres)]);
            } else {
                $this->pdo->prepare(
                    'INSERT INTO importacions (canal_id, nom_fitxer, data_visita_inici, data_visita_fi)
                     VALUES (?, ?, ?, ?)'
                )->execute([$canalId, $nomFitxer, $dataInici, $dataFi]);
            }
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
            // i no té cap ús a l'aplicació. El correu no es guarda mai.
            $conservarNom = ($canalCodi === 'CGR');
            $nul = static fn(string $v): ?string => $v === '' ? null : $v;

            foreach ($registres as $r) {
                [$provinciaDerivada, $comarcaDerivada] = $this->derivarProvinciaComarca($r['cp'], $r['es_espanya']);
                $insert->execute([
                    $importacioId,
                    $canalId,
                    $this->obtenirProducteId($r['producte']),
                    $conservarNom ? $nul($r['nom']) : null,
                    $nul($r['idioma']),
                    $nul($r['pais']),
                    $nul($r['comunitat']),
                    $nul($r['provincia']),
                    $nul($r['cp']),
                    $r['pax'],
                    $any,
                    $mes,
                    $anymes,
                    $provinciaDerivada,
                    $comarcaDerivada,
                    $r['es_espanya'],
                ]);
            }

            $this->pdo->prepare('UPDATE importacions SET files_importades = ? WHERE id = ?')
                ->execute([count($registres), $importacioId]);

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        return [
            'importacio_id' => $importacioId,
            'files'         => count($registres),
            'pax'           => array_sum(array_column($registres, 'pax')),
            'anymes'        => $anymes,
            'omeses'        => $omeses,
        ];
    }

    /** La columna `signatura` arriba amb database/migracio_control.sql; sense ella, s'importa igual. */
    public function teSignatura(): bool
    {
        if ($this->teSignatura === null) {
            $this->teSignatura = (bool)$this->pdo->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'importacions' AND COLUMN_NAME = 'signatura'"
            )->fetchColumn();
        }
        return $this->teSignatura;
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
            if (str_contains($joined, 'pax') && (str_contains($joined, 'producto') || str_contains($joined, 'producte')
                    || str_contains($joined, 'pais') || str_contains($joined, 'cp'))) {
                return $idx;
            }
        }
        return null;
    }

    private function obtenirProducteId(string $nomOriginal): int
    {
        static $cache = [];
        if (isset($cache[$nomOriginal])) return $cache[$nomOriginal];

        $stmt = $this->pdo->prepare('SELECT id FROM productes WHERE nom_original = ?');
        $stmt->execute([$nomOriginal]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            $this->pdo->prepare('INSERT INTO productes (nom_original) VALUES (?)')->execute([$nomOriginal]);
            $id = $this->pdo->lastInsertId();
        }
        return $cache[$nomOriginal] = (int)$id;
    }

    /** Retorna [provincia, comarca] a partir del codi postal (amb les correccions manuals aplicades) */
    public function derivarProvinciaComarca(string $cp, int $esEspanya): array
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

    /** Hi ha una correcció manual (Gestió d'errors) per a aquest CP? */
    public function teCorreccio(string $cp): bool
    {
        if (strlen($cp) !== 5 || !ctype_digit($cp)) return false;
        static $cache = null;
        if ($cache === null) {
            $cache = array_flip($this->pdo->query('SELECT codi_postal FROM correccions_cp')->fetchAll(PDO::FETCH_COLUMN));
        }
        return isset($cache[$cp]);
    }
}
