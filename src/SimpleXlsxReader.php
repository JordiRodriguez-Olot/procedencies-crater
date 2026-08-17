<?php

/**
 * Lector mínim de fitxers .xlsx sense dependències externes.
 * Un .xlsx és un ZIP amb XMLs a dins; això evita necessitar Composer
 * / PhpSpreadsheet, útil quan hi ha problemes de xarxa/SSL per instal·lar
 * paquets.
 *
 * Suporta: cadenes compartides (sharedStrings), cadenes inline, números
 * i booleans. No suporta fórmules complexes ni estils (no els necessitem
 * per llegir dades exportades de Clorian).
 */
class SimpleXlsxReader
{
    private ZipArchive $zip;
    private array $sharedStrings = [];
    private array $sheets = []; // nom del full => ruta dins el zip

    public function __construct(string $filepath)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($filepath) !== true) {
            throw new RuntimeException("No s'ha pogut obrir l'Excel: $filepath");
        }
        $this->carregarSharedStrings();
        $this->carregarFullsDeCalcul();
    }

    public function __destruct()
    {
        $this->zip->close();
    }

    public function nomsFulls(): array
    {
        return array_keys($this->sheets);
    }

    /** Retorna les files del full com a array 0-indexat de files i columnes */
    public function llegirFull(string $nomFull): array
    {
        $path = $this->sheets[$nomFull] ?? null;
        if ($path === null) {
            throw new RuntimeException("Full no trobat dins l'Excel: $nomFull");
        }

        $xml = $this->carregarXml($path);
        $files = [];
        $maxCol = 0;

        if (!isset($xml->sheetData->row)) {
            return [];
        }

        foreach ($xml->sheetData->row as $row) {
            $fila = [];
            foreach ($row->c as $c) {
                $refCel = (string)$c['r'];
                if ($refCel === '') continue;
                $colIdx = $this->columnaAIndex($refCel);
                $tipus = (string)$c['t'];

                if ($tipus === 's') {
                    $idx = isset($c->v) ? (int)$c->v : -1;
                    $valor = $this->sharedStrings[$idx] ?? '';
                } elseif ($tipus === 'inlineStr') {
                    $valor = isset($c->is->t) ? (string)$c->is->t : '';
                } elseif ($tipus === 'b') {
                    $valor = (isset($c->v) && (string)$c->v === '1');
                } elseif ($tipus === 'str') {
                    $valor = isset($c->v) ? (string)$c->v : '';
                } else {
                    // numèric per defecte
                    $valor = isset($c->v) ? (string)$c->v : null;
                    if ($valor !== null && $valor !== '' && is_numeric($valor)) {
                        $valor = $valor + 0; // int o float automàticament
                    }
                }

                $fila[$colIdx] = $valor;
                if ($colIdx > $maxCol) $maxCol = $colIdx;
            }
            $files[] = $fila;
        }

        // Omplir columnes buides perquè totes les files tinguin la mateixa mida
        foreach ($files as &$fila) {
            for ($i = 0; $i <= $maxCol; $i++) {
                if (!array_key_exists($i, $fila)) $fila[$i] = null;
            }
            ksort($fila);
        }
        unset($fila);

        return array_values($files);
    }

    private function carregarXml(string $pathDinsZip): SimpleXMLElement
    {
        $content = $this->zip->getFromName($pathDinsZip);
        if ($content === false) {
            throw new RuntimeException("No s'ha trobat $pathDinsZip dins l'Excel");
        }
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            throw new RuntimeException("XML invàlid a $pathDinsZip");
        }
        return $xml;
    }

    private function carregarSharedStrings(): void
    {
        $content = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($content === false) return; // alguns Excels no en tenen

        $xml = simplexml_load_string($content);
        if ($xml === false) return;

        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $this->sharedStrings[] = (string)$si->t;
            } else {
                // text "ric" amb diversos <r><t>...
                $text = '';
                if (isset($si->r)) {
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                }
                $this->sharedStrings[] = $text;
            }
        }
    }

    private function carregarFullsDeCalcul(): void
    {
        $workbook = $this->carregarXml('xl/workbook.xml');

        $relsContent = $this->zip->getFromName('xl/_rels/workbook.xml.rels');
        $relMap = [];
        if ($relsContent !== false) {
            $rels = simplexml_load_string($relsContent);
            foreach ($rels->Relationship as $rel) {
                $relMap[(string)$rel['Id']] = (string)$rel['Target'];
            }
        }

        $ns = $workbook->getNamespaces(true);
        $rNs = $ns['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        foreach ($workbook->sheets->sheet as $sheet) {
            $nom = (string)$sheet['name'];
            $attrsR = $sheet->attributes($rNs);
            $rId = (string)($attrsR['id'] ?? '');
            $target = $relMap[$rId] ?? null;
            if ($target !== null) {
                $path = str_starts_with($target, '/')
                    ? ltrim($target, '/')
                    : 'xl/' . preg_replace('#^\./#', '', $target);
                $this->sheets[$nom] = $path;
            }
        }
    }

    private function columnaAIndex(string $refCel): int
    {
        preg_match('/^([A-Z]+)\d+$/', $refCel, $m);
        $lletres = $m[1] ?? 'A';
        $index = 0;
        for ($i = 0; $i < strlen($lletres); $i++) {
            $index = $index * 26 + (ord($lletres[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
