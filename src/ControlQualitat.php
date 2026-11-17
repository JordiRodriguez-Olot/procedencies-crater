<?php

/**
 * Controls de qualitat de les dades:
 *  - analitzarFitxer(): informe de validació d'un Excel abans d'importar-lo
 *    (no escriu res a la BD).
 *  - controlMensual(): quadre mes × canal per veure si falta algun mes, si
 *    n'hi ha algun de massa diferent del normal o si s'ha pujat dues vegades.
 *
 * Els criteris de CP són els mateixos que fa servir Gestió d'errors, perquè
 * el que avisa aquí és exactament el que després hi sortirà pendent.
 */
final class ControlQualitat
{
    /** Pax per fila a partir del qual una reserva es considera inusual. Taquilla
     *  no en té: cada fila ja és la suma d'un país/CP de tot el mes. */
    public const PAX_ALT = ['ONLINE' => 15, 'CGR' => 150];

    /** Desviació respecte el mateix mes de l'any anterior que fa saltar l'avís */
    public const DESVIACIO = 0.5;

    /** Per sota d'aquests pax l'any anterior, la comparació no és significativa */
    public const BASE_MINIMA = 100;

    private const PREFIXOS_CAT = ['08', '17', '25', '43'];
    private const MOSTRES = 8;

    public function __construct(private PDO $pdo, private Importer $importer)
    {
    }

    /**
     * Clau per detectar files repetides dins un mateix fitxer. A Online i CGR
     * hi entren nom i correu (només en memòria; no es desen mai). Una fila
     * sense nom ni correu no es pot comparar i mai es considera repetida.
     */
    public static function clauDuplicat(string $canal, array $r): string
    {
        if ($canal === 'TAQUILLA') {
            return implode('|', [mb_strtolower($r['pais']), $r['cp'], mb_strtolower($r['producte']), $r['pax']]);
        }
        if ($r['nom'] === '' && $r['email'] === '') {
            return 'fila:' . $r['fila'];
        }
        return implode('|', [mb_strtolower(preg_replace('/\s+/u', ' ', $r['nom'])), $r['email'],
            mb_strtolower($r['producte']), $r['cp'], $r['pax']]);
    }

    /** Empremta del contingut (sense dades personals) per detectar el mateix fitxer pujat dos cops */
    public static function signatura(string $canal, array $registres): string
    {
        $linies = array_map(
            fn($r) => implode('|', [mb_strtolower($r['producte']), mb_strtolower($r['pais']), $r['cp'], $r['pax'], $r['idioma']]),
            $registres
        );
        sort($linies);
        return hash('sha256', $canal . "\n" . implode("\n", $linies));
    }

    /**
     * @param array $lectura resultat d'Importer::llegir()
     * @param int|null $anymes període (el detectat o el triat a mà)
     */
    public function analitzarFitxer(array $lectura, string $canal, ?int $anymes): array
    {
        $regs = $lectura['registres'];
        $pax = array_sum(array_column($regs, 'pax'));

        // ---- Controls fila a fila ----
        $controls = [
            'pais_buit'    => ['avis', 'País buit', 'No se sap la procedència d\'aquestes reserves.'],
            'cp_buit'      => ['avis', 'Espanya sense codi postal', 'Comptaran a Espanya però no a cap província ni comarca.'],
            'cp_format'    => ['avis', 'Codi postal mal format', 'No té 5 xifres (punts, lletres, xifres de més o de menys).'],
            'provincia_no' => ['avis', 'Província no reconeguda', 'El CP té 5 xifres però el prefix no és de cap província. Sovint és un CP francès marcat com a Espanya.'],
            'comarca_no'   => ['avis', 'CP català sense comarca', 'El CP comença per 08, 17, 25 o 43 però no és a la taula de municipis.'],
            'pais_cp'      => ['avis', 'País estranger amb CP català', 'El CP existeix a Catalunya però el país no és Espanya: probablement el país està mal triat.'],
            'pax_alt'      => ['avis', 'Pax inusualment alt', ''],
            'cp_completat' => ['info', 'CP completats amb un zero', 'Tenien 4 xifres (Excel n\'havia perdut el zero inicial) i s\'han completat: 8500 → 08500.'],
            'correccio'    => ['info', 'Correccions de CP aplicades', 'Codis postals que ja vas corregir a Gestió d\'errors: s\'hi aplicarà la mateixa correcció.'],
        ];
        $llindar = self::PAX_ALT[$canal] ?? null;
        if ($llindar !== null) {
            $controls['pax_alt'][2] = "Files amb més de $llindar pax. Pot ser correcte (un grup gran) o un error en teclejar.";
        }

        $acc = [];
        foreach (array_keys($controls) as $k) $acc[$k] = ['files' => 0, 'pax' => 0, 'grups' => []];
        $afegeix = function (string $k, array $r, string $etiqueta) use (&$acc): void {
            $acc[$k]['files']++;
            $acc[$k]['pax'] += $r['pax'];
            $g = &$acc[$k]['grups'][$etiqueta];
            $g = ['text' => $etiqueta, 'files' => ($g['files'] ?? 0) + 1, 'pax' => ($g['pax'] ?? 0) + $r['pax']];
        };

        foreach ($regs as $r) {
            $cp = $r['cp'];
            $etiquetaCp = ($cp !== '' ? "CP $cp" : 'sense CP') . ' · ' . ($r['pais'] !== '' ? $r['pais'] : 'país buit');
            $cinc = (bool)preg_match('/^\d{5}$/', $cp);
            [$prov, $com] = $this->importer->derivarProvinciaComarca($cp, $r['es_espanya']);

            if ($r['pais'] === '') $afegeix('pais_buit', $r, $etiquetaCp);
            if ($r['es_espanya'] && $cp === '') $afegeix('cp_buit', $r, $etiquetaCp);
            if ($cp !== '' && !$cinc) $afegeix('cp_format', $r, $etiquetaCp);
            if ($r['es_espanya'] && $cinc && $prov === null) $afegeix('provincia_no', $r, $etiquetaCp);
            $prefixCat = $cinc && in_array(substr($cp, 0, 2), self::PREFIXOS_CAT, true);
            if ($prefixCat && !$r['es_espanya'] && $r['pais'] !== ''
                && $this->importer->derivarProvinciaComarca($cp, 1)[1] !== null) {
                // CP català real amb un país estranger: el diagnòstic és el país, no la comarca
                $afegeix('pais_cp', $r, $etiquetaCp);
            } elseif ($prefixCat && $com === null) {
                $afegeix('comarca_no', $r, $etiquetaCp);
            }
            if ($llindar !== null && $r['pax'] > $llindar) {
                $afegeix('pax_alt', $r, "Fila {$r['fila']} · {$r['producte']} · {$r['pax']} pax");
            }
            if ($this->importer->teCorreccio($cp)) $afegeix('correccio', $r, "CP $cp");
        }
        $acc['cp_completat']['files'] = $lectura['cp_completats'];

        $sortida = [];
        foreach ($controls as $k => [$nivell, $titol, $explicacio]) {
            if ($acc[$k]['files'] === 0) continue;
            $grups = array_values($acc[$k]['grups']);
            usort($grups, fn($a, $b) => $b['pax'] <=> $a['pax']);
            $sortida[] = [
                'clau' => $k, 'nivell' => $nivell, 'titol' => $titol, 'explicacio' => $explicacio,
                'files' => $acc[$k]['files'], 'pax' => $acc[$k]['pax'],
                'mostres' => array_slice($grups, 0, self::MOSTRES), 'mes_mostres' => max(0, count($grups) - self::MOSTRES),
            ];
        }

        // ---- Files repetides dins el fitxer ----
        $perClau = [];
        foreach ($regs as $r) $perClau[self::clauDuplicat($canal, $r)][] = $r;
        $dupGrups = array_values(array_filter($perClau, fn($g) => count($g) > 1));
        $sobrants = 0;
        $paxSobrants = 0;
        $mostresDup = [];
        foreach ($dupGrups as $g) {
            $sobrants += count($g) - 1;
            $paxSobrants += $g[0]['pax'] * (count($g) - 1);
            $qui = $canal === 'TAQUILLA'
                ? ($g[0]['pais'] ?: 'país buit') . ($g[0]['cp'] !== '' ? ' · CP ' . $g[0]['cp'] : '')
                : ($g[0]['nom'] !== '' ? $g[0]['nom'] : '(sense nom)');
            $mostresDup[] = [
                'text' => 'Files ' . implode(', ', array_column($g, 'fila')) . " · $qui · {$g[0]['producte']} · {$g[0]['pax']} pax",
                'files' => count($g), 'pax' => $g[0]['pax'] * count($g),
            ];
        }
        usort($mostresDup, fn($a, $b) => $b['pax'] <=> $a['pax']);

        // ---- Productes ----
        $existents = array_flip($this->pdo->query('SELECT nom_original FROM productes')->fetchAll(PDO::FETCH_COLUMN));
        $ambGrup = array_flip($this->pdo->query('SELECT nom_original FROM mapeig_productes')->fetchAll(PDO::FETCH_COLUMN));
        $productes = array_unique(array_column($regs, 'producte'));
        $nous = array_values(array_filter($productes, fn($p) => !isset($existents[$p])));
        $senseGrup = array_values(array_filter($productes, fn($p) => !isset($ambGrup[$p]) && $p !== 'Taquilla'));

        // ---- Període: dades existents, comparativa i fitxers repetits ----
        $canalId = (int)$this->pdo->query('SELECT id FROM canals WHERE codi = ' . $this->pdo->quote($canal))->fetchColumn();
        $periode = null;
        if ($anymes !== null) {
            $suma = $this->pdo->prepare(
                'SELECT COUNT(*) AS files, COALESCE(SUM(pax),0) AS pax FROM entrades
                 WHERE canal_id = ? AND anymes = ? AND es_manual = ?'
            );
            $consulta = function (int $am, int $manual = 0) use ($suma, $canalId): array {
                $suma->execute([$canalId, $am, $manual]);
                $f = $suma->fetch();
                return ['anymes' => $am, 'files' => (int)$f['files'], 'pax' => (int)$f['pax']];
            };
            $anterior = $anymes % 100 === 1 ? $anymes - 89 : $anymes - 1;
            $anyAnterior = $consulta($anymes - 100);
            $periode = [
                'existents'   => $consulta($anymes),
                'manuals'     => $consulta($anymes, 1),
                'mes_anterior'=> $consulta($anterior),
                'any_anterior'=> $anyAnterior,
                'desviacio'   => $anyAnterior['pax'] >= self::BASE_MINIMA
                    ? round(($pax - $anyAnterior['pax']) / $anyAnterior['pax'], 3) : null,
            ];
        }

        $repetits = [];
        if ($this->importer->teSignatura() && $regs) {
            $st = $this->pdo->prepare(
                'SELECT i.id, i.nom_fitxer, i.data_importacio, MIN(e.anymes) AS anymes
                 FROM importacions i LEFT JOIN entrades e ON e.importacio_id = i.id
                 WHERE i.signatura = ? AND i.canal_id = ?
                 GROUP BY i.id, i.nom_fitxer, i.data_importacio'
            );
            $st->execute([self::signatura($canal, $regs), $canalId]);
            $repetits = $st->fetchAll();
        }

        return [
            'canal'         => $canal,
            'anymes'        => $anymes,
            'detectat'      => $lectura['anymes'] !== null,
            'multi_mes'     => $lectura['multi_mes'],
            'data_inici'    => $lectura['data_inici'],
            'data_fi'       => $lectura['data_fi'],
            'files'         => count($regs),
            'pax'           => $pax,
            'ignorades'     => $lectura['ignorades'],
            'periode'       => $periode,
            'repetits'      => $repetits,
            'controls'      => $sortida,
            'duplicats'     => ['grups' => count($dupGrups), 'sobrants' => $sobrants, 'pax_sobrants' => $paxSobrants,
                                'mostres' => array_slice($mostresDup, 0, self::MOSTRES),
                                'mes_mostres' => max(0, count($mostresDup) - self::MOSTRES)],
            'productes_nous'      => $nous,
            'productes_sense_grup'=> $senseGrup,
        ];
    }

    /**
     * Quadre mes × canal des del primer mes amb dades fins al mes passat.
     * Estat de cada cel·la: ok, falta, pendent (mes en curs), desviacio,
     * multiple (més d'una importació) o repetit (mateix contingut que una altra).
     */
    public function controlMensual(): array
    {
        $canals = $this->pdo->query('SELECT id, codi FROM canals ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

        $dades = [];
        foreach ($this->pdo->query(
            'SELECT canal_id, anymes, COUNT(*) AS files, SUM(pax) AS pax, SUM(es_manual) AS manuals,
                    COUNT(DISTINCT CASE WHEN es_manual = 0 THEN importacio_id END) AS importacions
             FROM entrades GROUP BY canal_id, anymes'
        ) as $f) {
            $dades[$canals[$f['canal_id']]][(int)$f['anymes']] = $f;
        }

        // Importacions amb el mateix contingut que una altra (mateixa signatura)
        $repetits = [];
        if ($this->importer->teSignatura()) {
            foreach ($this->pdo->query(
                'SELECT i.canal_id, e.anymes FROM importacions i
                 JOIN (SELECT signatura FROM importacions WHERE signatura IS NOT NULL
                       GROUP BY signatura HAVING COUNT(*) > 1) d ON d.signatura = i.signatura
                 JOIN entrades e ON e.importacio_id = i.id
                 GROUP BY i.id, i.canal_id, e.anymes'
            ) as $f) {
                $repetits[$canals[$f['canal_id']]][(int)$f['anymes']] = true;
            }
        }

        $minim = (int)$this->pdo->query('SELECT MIN(anymes) FROM entrades')->fetchColumn();
        $mesActual = (int)date('Ym');
        $mesos = [];
        $resum = ['ok' => 0, 'falta' => 0, 'desviacio' => 0, 'multiple' => 0, 'repetit' => 0, 'pendent' => 0];
        if ($minim > 0) {
            for ($am = $mesActual; $am >= $minim; $am = $am % 100 === 1 ? $am - 89 : $am - 1) {
                $fila = ['anymes' => $am, 'canals' => []];
                foreach ($canals as $codi) {
                    $d = $dades[$codi][$am] ?? null;
                    $pax = (int)($d['pax'] ?? 0);
                    $base = (int)($dades[$codi][$am - 100]['pax'] ?? 0);
                    $desv = $base >= self::BASE_MINIMA ? ($pax - $base) / $base : null;
                    $avisos = [];

                    if (!empty($repetits[$codi][$am])) {
                        $estat = 'repetit';
                        $avisos[] = 'Té el mateix contingut que una altra importació: probablement s\'ha pujat el mateix fitxer dues vegades.';
                    } elseif ($d === null || (int)$d['files'] === (int)$d['manuals']) {
                        $estat = $am === $mesActual ? 'pendent' : 'falta';
                        $avisos[] = $am === $mesActual ? 'Mes en curs: encara no toca.' : 'No hi ha cap importació d\'aquest mes.';
                    } elseif ((int)$d['importacions'] > 1) {
                        $estat = 'multiple';
                        $avisos[] = "{$d['importacions']} importacions per al mateix mes: comprova que no se n'hagi afegit una a sobre de l'altra.";
                    } elseif ($desv !== null && abs($desv) > self::DESVIACIO) {
                        $estat = 'desviacio';
                    } else {
                        $estat = 'ok';
                    }
                    if ($desv !== null && abs($desv) > self::DESVIACIO && $estat !== 'falta') {
                        $avisos[] = sprintf('%+d%% respecte el mateix mes de l\'any anterior (%s pax).',
                            round($desv * 100), number_format($base, 0, ',', '.'));
                    }
                    if ((int)($d['manuals'] ?? 0) > 0) {
                        $avisos[] = "Inclou {$d['manuals']} entrades manuals.";
                    }
                    $resum[$estat]++;
                    $fila['canals'][$codi] = [
                        'estat' => $estat, 'files' => (int)($d['files'] ?? 0), 'pax' => $pax,
                        'desviacio' => $desv === null ? null : round($desv, 3), 'avisos' => $avisos,
                    ];
                }
                $mesos[] = $fila;
            }
        }

        return [
            'canals' => array_values($canals),
            'mesos' => $mesos,
            'resum' => $resum,
            'llindar_desviacio' => self::DESVIACIO,
            'te_signatura' => $this->importer->teSignatura(),
        ];
    }
}
