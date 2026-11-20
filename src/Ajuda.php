<?php

/**
 * Bloc d'ajuda contextual «Com funciona?» sota el títol de cada pàgina.
 * Els textos són a config/ajuda.php.
 */
function renderAjuda(string $pagina): void
{
    static $textos = null;
    $textos ??= require __DIR__ . '/../config/ajuda.php';
    $a = $textos[$pagina] ?? null;
    if (!$a) return;

    // Text escapat, amb **negreta** permesa
    $fmt = static fn(string $t): string => preg_replace('/\*\*(.+?)\*\*/u', '<b>$1</b>', e($t));
    ?>
    <details class="app-ajuda mb-3">
        <summary>
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span class="app-ajuda-resum"><?= $fmt($a['resum']) ?></span>
            <span class="app-ajuda-obre">Com funciona?</span>
        </summary>
        <div class="app-ajuda-cos">
            <?php if (!empty($a['passos'])): ?>
                <ol><?php foreach ($a['passos'] as $p): ?><li><?= $fmt($p) ?></li><?php endforeach; ?></ol>
            <?php endif; ?>
            <?php if (!empty($a['consells'])): ?>
                <div class="app-ajuda-consells">
                    <div class="fw-semibold mb-1"><i class="bi bi-lightbulb" aria-hidden="true"></i> Consells</div>
                    <ul><?php foreach ($a['consells'] as $c): ?><li><?= $fmt($c) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
        </div>
    </details>
    <?php
}
