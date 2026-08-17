<?php

/**
 * Importa el format consolidat "general": un únic Excel amb TOTS els
 * canals i períodes ja units en una sola taula (típicament un full
 * anomenat "Dades completades" o similar), amb columnes ja calculades:
 * pais, cp, pax, ANYMES, NomProv (província), Comarca, ORIGEN (producte),
 * CANAL, GRUP-ORIGEN (grup del producte).
 *
 * A diferència de l'Importer normal (que processa un fitxer = un
 * canal + un període), aquest pot contenir desenes de períodes i els
 * tres canals alhora, útil per carregar tot l'històric d'un cop.
 */
class ImportadorGeneral
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Analitza el fitxer sense escriure res a la BD. Retorna un resum
     * dels períodes trobats i quins ja tenen dades existents, perquè la
     * pantalla d'importació pugui demanar confirmació abans d'actuar.
     */
    public function previsualitzar(string $filepath): array
    {
        [$registres, ] = $this->llegirIProcessar($filepath);
        return $this->resumPerPeriodeCanal($registres);
    }

    /**
     * @param string $mode 'saltar_existents' (per defecte: no toca els
     *                     canal+període que ja tinguin dades),
     *                     'substituir_existents' (esborra i recarrega
     *                     els canal+període que ja tinguin dades),
     *                     'afegir_tot' (importa igualment, sumant-se).
     */
    public function importar(string $filepath, string $nomFitxer, string $mode = 'saltar_existents'): array
    {
        [$registres, $totalFilesLlegides] = $this->llegirIProcessar($filepath);
        $resum = $this->resumPerPeriodeCanal($registres);

        $grupsAIncloure = [];
        foreach ($resum as $r) {
            $clau = $r['canal'] . '|' . $r['anymes'];
            if ($r['existents_files'] > 0 && $mode === 'saltar_existents') continue;
            $grupsAIncloure[$clau] = true;
        }

        $this->pdo->beginTransaction();
        try {
            if ($mode === 'substituir_existents') {
                foreach ($resum as $r) {
                    if ($r['existents_files'] === 0) continue;
                    $canalId = $this->obtenirCanalId($r['canal']);
                    $this->pdo->prepare('DELETE FROM entrades WHERE canal_id = ? AND anymes = ? AND es_manual = 0')
                        ->execute([$canalId, $r['anymes']]);
                }
                $this->pdo->exec('DELETE FROM importacions WHERE id NOT IN (SELECT DISTINCT importacio_id FROM entrades)');
            }

            // Un registre d'importació per cada combinació canal+període
            // present al fitxer (així l'historial d'importacions les pot
            // desfer individualment igual que les importacions normals).
            $importacioIdPerGrup = [];
            foreach ($resum as $r) {
                $clau = $r['canal'] . '|' . $r['anymes'];
                if (!isset($grupsAIncloure[$clau])) continue;
                $canalId = $this->obtenirCanalId($r['canal']);
                $any = intdiv((int)$r['anymes'], 100);
                $mesN = (int)$r['anymes'] % 100;
                $dataAprox = sprintf('%04d-%02d-01', $any, max(1, min(12, $mesN)));
                $stmt = $this->pdo->prepare(
                    'INSERT INTO importacions (canal_id, nom_fitxer, data_visita_inici, data_visita_fi)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$canalId, $nomFitxer . " (general, {$r['anymes']})", $dataAprox, $dataAprox]);
                $importacioIdPerGrup[$clau] = (int)$this->pdo->lastInsertId();
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO entrades
                 (importacio_id, canal_id, producte_id, nom_client, pais, comunitat, provincia,
                  codi_postal, pax, any_visita, mes_visita, anymes, provincia_derivada,
                  comarca_derivada, pais_es_espanya, es_manual)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0)'
            );

            $comptadorPerGrup = [];
            $inserides = 0;

            foreach ($registres as $reg) {
                $clau = $reg['canal'] . '|' . $reg['anymes'];
                if (!isset($grupsAIncloure[$clau])) continue;

                $canalId = $this->obtenirCanalId($reg['canal']);
                $producteId = $this->obtenirProducteId($reg['origen']);
                $this->registrarGrupProducte($reg['origen'], $reg['grup']);

                [$provinciaDerivada, $comarcaDerivada] = $this->completarGeografia($reg);

                $insert->execute([
                    $importacioIdPerGrup[$clau],
                    $canalId,
                    $producteId,
                    null, // RGPD: aquest format consolidat no porta noms de client
                    $reg['pais'],
                    null,
                    $reg['nomprov'],
                    $reg['cp'],
                    $reg['pax'],
                    intdiv((int)$reg['anymes'], 100),
                    (int)$reg['anymes'] % 100,
                    $reg['anymes'],
                    $provinciaDerivada,
                    $comarcaDerivada,
                    $reg['es_espanya'],
                ]);
                $inserides++;
                $comptadorPerGrup[$clau] = ($comptadorPerGrup[$clau] ?? 0) + 1;
            }

            foreach ($comptadorPerGrup as $clau => $n) {
                $this->pdo->prepare('UPDATE importacions SET files_importades = ? WHERE id = ?')
                    ->execute([$n, $importacioIdPerGrup[$clau]]);
            }

            $this->pdo->commit();

            return [
                'files_llegides' => $totalFilesLlegides,
                'files_importades' => $inserides,
                'periodes_importats' => count($grupsAIncloure),
                'periodes_saltats' => count($resum) - count($grupsAIncloure),
                'resum' => $resum,
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Llegeix el fitxer i normalitza cada fila vàlida a un array associatiu */
    private function llegirIProcessar(string $filepath): array
    {
        $reader = new SimpleXlsxReader($filepath);
        $nomFull = $this->trobarFullGeneral($reader->nomsFulls());
        $rows = $reader->llegirFull($nomFull);

        $headerIdx = 0; // aquest format porta la capçalera sempre a la primera fila
        $headers = array_map(fn($h) => $this->netejaCapcalera((string)$h), $rows[$headerIdx]);
        $colIdx = array_flip($headers);

        $col = fn(string ...$noms) => $this->primeraColumnaExistent($colIdx, $noms);

        $cPais    = $col('pais');
        $cCp      = $col('cp');
        $cPax     = $col('pax');
        $cAnymes  = $col('anymes');
        $cNomProv = $col('nomprov');
        $cComarca = $col('comarca');
        $cOrigen  = $col('origen');
        $cCanal   = $col('canal');
        $cGrup    = $col('grupOrigen', 'grup');

        if ($cPax === null || $cAnymes === null || $cCanal === null) {
            throw new RuntimeException('Aquest Excel no sembla tenir el format consolidat esperat (falten columnes pax/ANYMES/CANAL).');
        }

        $registres = [];
        $totalFilesLlegides = 0;

        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $r = $rows[$i];
            $totalFilesLlegides++;

            $pax = (int)($r[$cPax] ?? 0);
            $anymes = (int)($r[$cAnymes] ?? 0);
            $canal = strtoupper(trim((string)($r[$cCanal] ?? '')));

            if ($pax <= 0 || $anymes <= 0 || !in_array($canal, ['TAQUILLA', 'ONLINE', 'CGR'], true)) {
                continue; // fila de plantilla buida o canal desconegut
            }

            $pais = $cPais !== null ? trim((string)($r[$cPais] ?? '')) : '';
            $esEspanya = in_array(mb_strtolower($pais), ['españa', 'espanya'], true) ? 1 : 0;

            $cp = $cCp !== null ? trim((string)($r[$cCp] ?? '')) : '';
            if ($cp !== '' && $cp !== '0' && ctype_digit($cp)) {
                $cp = str_pad($cp, 5, '0', STR_PAD_LEFT);
            } elseif ($cp === '0' || $cp === '#N/A') {
                $cp = '';
            }

            $nomProv = $cNomProv !== null ? trim((string)($r[$cNomProv] ?? '')) : '';
            if ($nomProv === '#N/A') $nomProv = '';

            $comarca = $cComarca !== null ? trim((string)($r[$cComarca] ?? '')) : '';
            if ($comarca === '#N/A') $comarca = '';

            $origen = $cOrigen !== null ? trim((string)($r[$cOrigen] ?? '')) : '';
            if ($origen === '' || $origen === '0') $origen = 'Sense especificar';

            $grup = $cGrup !== null ? trim((string)($r[$cGrup] ?? '')) : '';
            if ($grup === '' || $grup === '#N/A') $grup = $origen;

            $registres[] = [
                'canal' => $canal,
                'pais' => $pais ?: null,
                'es_espanya' => $esEspanya,
                'cp' => $cp ?: null,
                'pax' => $pax,
                'anymes' => $anymes,
                'nomprov' => $nomProv ?: null,
                'comarca' => $comarca ?: null,
                'origen' => $origen,
                'grup' => $grup,
            ];
        }

        return [$registres, $totalFilesLlegides];
    }

    private function resumPerPeriodeCanal(array $registres): array
    {
        $agrupat = [];
        foreach ($registres as $r) {
            $clau = $r['canal'] . '|' . $r['anymes'];
            if (!isset($agrupat[$clau])) {
                $agrupat[$clau] = ['canal' => $r['canal'], 'anymes' => $r['anymes'], 'files' => 0, 'pax' => 0];
            }
            $agrupat[$clau]['files']++;
            $agrupat[$clau]['pax'] += $r['pax'];
        }

        foreach ($agrupat as &$g) {
            $canalId = $this->obtenirCanalId($g['canal']);
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*) AS files, COALESCE(SUM(pax),0) AS pax
                 FROM entrades WHERE canal_id = ? AND anymes = ? AND es_manual = 0'
            );
            $stmt->execute([$canalId, $g['anymes']]);
            $existent = $stmt->fetch();
            $g['existents_files'] = (int)$existent['files'];
            $g['existents_pax'] = (int)$existent['pax'];
        }
        unset($g);

        usort($agrupat, fn($a, $b) => $a['anymes'] <=> $b['anymes'] ?: strcmp($a['canal'], $b['canal']));
        return array_values($agrupat);
    }

    private function completarGeografia(array $reg): array
    {
        $provincia = $reg['nomprov'];
        $comarca = $reg['comarca'];

        // Si el fitxer ja porta província/comarca calculades, les fem
        // servir directament (evita reprocessar 21.000 files). Només si
        // falten i tenim CP espanyol, les derivem com faria l'Importer normal.
        if (($provincia === null || $comarca === null) && $reg['es_espanya'] && $reg['cp']) {
            [$provDerivada, $comDerivada] = $this->derivarDeCp($reg['cp']);
            $provincia = $provincia ?? $provDerivada;
            $comarca = $comarca ?? $comDerivada;
        }

        // Correccions manuals (Gestió d'errors) tenen sempre la darrera paraula
        if ($reg['cp']) {
            $stmt = $this->pdo->prepare('SELECT provincia, comarca FROM correccions_cp WHERE codi_postal = ?');
            $stmt->execute([$reg['cp']]);
            if ($corr = $stmt->fetch()) {
                if (!empty($corr['provincia'])) $provincia = $corr['provincia'];
                if (!empty($corr['comarca'])) $comarca = $corr['comarca'];
            }
        }

        return [$provincia, $comarca];
    }

    private function derivarDeCp(string $cpNorm): array
    {
        $prefix = substr($cpNorm, 0, 2);
        $stmt = $this->pdo->prepare('SELECT nom FROM provincies WHERE codi_prefix = ?');
        $stmt->execute([$prefix]);
        $prov = $stmt->fetchColumn() ?: null;

        $stmt = $this->pdo->prepare(
            'SELECT c.nom FROM cp_municipis cm
             JOIN municipis m ON m.nom_municipi = cm.nom_municipi
             JOIN comarques c ON c.codi = m.codi_comarca
             WHERE cm.codi_postal = ? LIMIT 1'
        );
        $stmt->execute([$cpNorm]);
        $com = $stmt->fetchColumn() ?: null;

        return [$prov, $com];
    }

    private function obtenirCanalId(string $codi): int
    {
        static $cache = [];
        if (!isset($cache[$codi])) {
            $stmt = $this->pdo->prepare('SELECT id FROM canals WHERE codi = ?');
            $stmt->execute([$codi]);
            $cache[$codi] = (int)$stmt->fetchColumn();
        }
        return $cache[$codi];
    }

    private function obtenirProducteId(string $nomOriginal): int
    {
        static $cache = [];
        if (isset($cache[$nomOriginal])) return $cache[$nomOriginal];

        $stmt = $this->pdo->prepare('SELECT id FROM productes WHERE nom_original = ?');
        $stmt->execute([$nomOriginal]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            $ins = $this->pdo->prepare('INSERT INTO productes (nom_original) VALUES (?)');
            $ins->execute([$nomOriginal]);
            $id = (int)$this->pdo->lastInsertId();
        }
        return $cache[$nomOriginal] = (int)$id;
    }

    /** Registra el grup del producte si encara no en tenia cap assignat
     *  (mai sobreescriu una assignació que ja existeixi, per no trepitjar
     *  ajustos fets manualment a "Gestió de productes"). */
    private function registrarGrupProducte(string $nomOriginal, string $grup): void
    {
        static $fets = [];
        if (isset($fets[$nomOriginal])) return;
        $fets[$nomOriginal] = true;

        $stmt = $this->pdo->prepare(
            'INSERT INTO mapeig_productes (nom_original, grup) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE nom_original = nom_original'
        );
        $stmt->execute([$nomOriginal, $grup]);
    }

    private function trobarFullGeneral(array $nomsFulls): string
    {
        foreach ($nomsFulls as $nom) {
            $t = mb_strtolower($nom);
            if (str_contains($t, 'completad') || str_contains($t, 'general') || str_contains($t, 'consolidad')) {
                return $nom;
            }
        }
        return $nomsFulls[0] ?? throw new RuntimeException('El fitxer no té cap full de dades.');
    }

    private function netejaCapcalera(string $h): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower(trim($h)));
    }

    private function primeraColumnaExistent(array $colIdx, array $noms): ?int
    {
        foreach ($noms as $nom) {
            $clau = $this->netejaCapcalera($nom);
            if (isset($colIdx[$clau])) return $colIdx[$clau];
        }
        return null;
    }
}
