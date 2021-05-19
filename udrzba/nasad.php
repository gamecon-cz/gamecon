<?php

/**
 * Nahraje aktuální stav na ostrou i beta verzi webu.
 *
 * Tento skript je možné použít jako git pre-push hook -- stačí vytvořit
 * spustitelný soubor '.git/hooks/pre-push' s obsahem:
 *
 *   #!/usr/bin/php
 *   <?php
 *   require 'udrzba/nasad.php'; // cwd je vždy root gitu
 *
 */

require_once __DIR__ . '/_pomocne.php';

$nastaveni = require __DIR__ . '/../nastaveni/nastaveni-nasazovani.php';

chdir(__DIR__ . '/../');

// testování větve před pushem a čistoty repa, aby se na FTP nedostalo smetí
exec('git rev-parse --abbrev-ref HEAD', $out);
$vetev = $out[0];
if (!($vetev === 'master' || $vetev === 'blackarrow')) {
    echo "You're not on automatically deployed branch, deployment skipped\n";
    exit(0);
}
exec('git status', $out);
if (!preg_match('/^nothing to commit, working (tree|directory) clean$/', end($out))) {
    echo "error: working directory is not clean\n";
    exit(1);
}

// spuštění testů
call_check(['php', __DIR__ . '/testuj.php']);

// nasazení
if ($vetev === 'master') {
    nasad([
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['ostra']['ftp'],
        'urlMigrace' => $nastaveni['ostra']['urlMigrace'],
        'hesloMigrace' => $nastaveni['ostra']['hesloMigrace'],
        'souborNastaveni' => 'nastaveni-produkce.php',
    ]);
} elseif ($vetev === 'beta') {
    nasad([
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['beta']['ftp'],
        'urlMigrace' => $nastaveni['beta']['urlMigrace'],
        'hesloMigrace' => $nastaveni['beta']['hesloMigrace'],
        'souborNastaveni' => 'nastaveni-beta.php',
    ]);
} elseif ($vetev === 'blackarrow') {
    nasad([
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['blackarrow']['ftp'],
        'urlMigrace' => $nastaveni['blackarrow']['urlMigrace'],
        'hesloMigrace' => $nastaveni['blackarrow']['hesloMigrace'],
        'log' => $nastaveni['blackarrow']['log'],
        'souborNastaveni' => 'nastaveni-blackarrow.php',
    ]);
} else {
    echo "error: unexpected branch '$vetev'\n";
    exit(1);
}
