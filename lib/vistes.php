<?php

/**
 * Ajudes de presentació: formats (dates, imports), etiquetes, paginació,
 * ordenació de taules i pas de dades al JS. Totes retornen HTML ja escapat.
 */

function asset(string $ruta): string
{
    $fitxer = __DIR__ . '/../public/assets/' . $ruta;

    return 'assets/' . $ruta . (is_file($fitxer) ? '?v=' . filemtime($fitxer) : '');
}

function formatData(?string $data, bool $ambHora = false): string
{
    if ($data === null || $data === '') {
        return '';
    }
    $moment = strtotime($data);

    return $moment === false ? '' : date($ambHora ? 'd/m/Y H:i' : 'd/m/Y', $moment);
}

function formatImport(float|int|string|null $import, int $decimals = 2): string
{
    return number_format((float) $import, $decimals, ',', '.') . ' €';
}

function formatNombre(float|int $nombre, int $decimals = 0): string
{
    return number_format((float) $nombre, $decimals, ',', '.');
}

function inicials(string $nom): string
{
    $nom = trim($nom);

    return $nom === '' ? '?' : mb_strtoupper(mb_substr($nom, 0, 1));
}

/**
 * Variants: primari, neutra, exit, alerta, perill, info.
 */
function etiqueta(string $text, string $variant = 'neutra', ?string $icona = null): string
{
    $htmlIcona = $icona ? '<i class="bi ' . e($icona) . ' me-1"></i>' : '';

    return '<span class="etiqueta etiqueta-' . e($variant) . '">' . $htmlIcona . e($text) . '</span>';
}

function etiquetaRol(string $rol): string
{
    $text = (string) (config('rols')[$rol] ?? $rol);

    return etiqueta($text, match ($rol) {
        'admin'  => 'primari',
        'gestor' => 'neutra',
        default  => 'contorn',
    });
}

/**
 * Codifica dades per a un atribut data-* (el JS les llegeix amb JSON.parse).
 */
function dadesJs(mixed $dades): string
{
    return e((string) json_encode($dades, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
}

/**
 * URL de la pàgina actual amb alguns paràmetres canviats (null = treu-lo).
 *
 * @param array<string, scalar|null> $canvis
 */
function urlAmb(array $canvis): string
{
    $query = array_merge($_GET, $canvis);
    $query = array_filter($query, fn ($v) => $v !== null && $v !== '');
    $pagina = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));

    return $pagina . ($query ? '?' . http_build_query($query) : '');
}

/**
 * @return array{pagina:int, pagines:int, per_pagina:int, offset:int, total:int, desde:int, fins:int}
 */
function paginacio(int $total, int $pagina, int $perPagina = 10): array
{
    $pagines = max(1, (int) ceil($total / $perPagina));
    $pagina = min(max(1, $pagina), $pagines);
    $offset = ($pagina - 1) * $perPagina;

    return [
        'pagina'     => $pagina,
        'pagines'    => $pagines,
        'per_pagina' => $perPagina,
        'offset'     => $offset,
        'total'      => $total,
        'desde'      => $total === 0 ? 0 : $offset + 1,
        'fins'       => min($offset + $perPagina, $total),
    ];
}

/**
 * @param array{pagina:int, pagines:int} $p
 */
function renderPaginacio(array $p): string
{
    if ($p['pagines'] <= 1) {
        return '';
    }
    $enllac = function (int $pagina, string $text, bool $actiu = false, bool $desactivat = false, string $etiqueta = ''): string {
        $classe = 'page-item' . ($actiu ? ' active' : '') . ($desactivat ? ' disabled' : '');
        $aria = $etiqueta !== '' ? ' aria-label="' . e($etiqueta) . '"' : '';
        $actual = $actiu ? ' aria-current="page"' : '';

        return '<li class="' . $classe . '"><a class="page-link" href="' . e(urlAmb(['pagina' => $pagina])) . '"' . $aria . $actual . '>' . $text . '</a></li>';
    };

    $html = '<nav aria-label="Paginació"><ul class="pagination pagination-sm mb-0">';
    $html .= $enllac($p['pagina'] - 1, '<i class="bi bi-chevron-left"></i>', false, $p['pagina'] === 1, 'Anterior');
    $inici = max(1, $p['pagina'] - 2);
    $fi = min($p['pagines'], $p['pagina'] + 2);
    for ($i = $inici; $i <= $fi; $i++) {
        $html .= $enllac($i, (string) $i, $i === $p['pagina']);
    }
    $html .= $enllac($p['pagina'] + 1, '<i class="bi bi-chevron-right"></i>', false, $p['pagina'] === $p['pagines'], 'Següent');

    return $html . '</ul></nav>';
}

/**
 * Capçalera de columna ordenable. $columna ha de ser a la llista blanca de la pàgina.
 */
function capcaleraOrdenable(string $columna, string $text, string $ordreActual, string $dirActual): string
{
    $actiu = $columna === $ordreActual;
    $dirNova = $actiu && $dirActual === 'asc' ? 'desc' : 'asc';
    $icona = $actiu ? ($dirActual === 'asc' ? 'bi-caret-up-fill' : 'bi-caret-down-fill') : 'bi-chevron-expand';

    return '<a class="ordenable' . ($actiu ? ' actiu' : '') . '" href="' . e(urlAmb(['ordre' => $columna, 'dir' => $dirNova, 'pagina' => null])) . '">'
        . e($text) . ' <i class="bi ' . $icona . '"></i></a>';
}

/**
 * Descarrega un CSV que Excel obre bé (BOM UTF-8 + punt i coma).
 *
 * @param string[] $capcalera
 * @param array<int, array<int, scalar|null>> $files
 */
function descarregaCsv(string $nomFitxer, array $capcalera, array $files): never
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $nomFitxer) . '"');
    $sortida = fopen('php://output', 'w');
    fwrite($sortida, "\xEF\xBB\xBF");
    fputcsv($sortida, $capcalera, ';', '"', '');
    foreach ($files as $fila) {
        // Evita la injecció de fórmules a Excel (=, +, -, @ al principi d'una cel·la).
        $fila = array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $fila);
        fputcsv($sortida, $fila, ';', '"', '');
    }
    fclose($sortida);
    exit;
}
