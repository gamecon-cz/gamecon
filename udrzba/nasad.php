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
        'vetev' => $vetev,
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['ostra']['ftp'],
        'urlMigrace' => $nastaveni['ostra']['urlMigrace'],
        'hesloMigrace' => $nastaveni['ostra']['hesloMigrace'],
        'souborNastaveni' => basename(__DIR__ . '/../nastaveni/nastaveni-produkce.php'),
    ]);
} elseif ($vetev === 'beta') {
    nasad([
        'vetev' => $vetev,
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['beta']['ftp'],
        'urlMigrace' => $nastaveni['beta']['urlMigrace'],
        'hesloMigrace' => $nastaveni['beta']['hesloMigrace'],
        'souborNastaveni' => basename(__DIR__ . '/../nastaveni/nastaveni-beta.php'),
    ]);
} elseif ($vetev === 'blackarrow') {
    nasad([
        'vetev' => $vetev,
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['blackarrow']['ftp'],
        'urlMigrace' => $nastaveni['blackarrow']['urlMigrace'],
        'hesloMigrace' => $nastaveni['blackarrow']['hesloMigrace'],
        'log' => $nastaveni['blackarrow']['log'],
        'souborNastaveni' => basename(__DIR__ . '/../nastaveni/nastaveni-blackarrow.php'),
    ]);
} elseif ($vetev === 'jakublounek') {
    nasad([
        'vetev' => $vetev,
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['jakublounek']['ftp'],
        'urlMigrace' => $nastaveni['jakublounek']['urlMigrace'],
        'hesloMigrace' => $nastaveni['jakublounek']['hesloMigrace'],
        'log' => $nastaveni['jakublounek']['log'],
        'souborNastaveni' => basename(__DIR__ . '/../nastaveni/nastaveni-jakublounek.php'),
    ]);
} elseif ($vetev === 'misahojna') {
    nasad([
        'vetev' => $vetev,
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['misahojna']['ftp'],
        'urlMigrace' => $nastaveni['misahojna']['urlMigrace'],
        'hesloMigrace' => $nastaveni['misahojna']['hesloMigrace'],
        'log' => $nastaveni['misahojna']['log'],
        'souborNastaveni' => basename(__DIR__ . '/../nastaveni/nastaveni-misahojna.php'),
    ]);
} elseif ($vetev === 'sciator') {
    nasad([
        'vetev' => $vetev,
        'zdrojovaSlozka' => __DIR__ . '/..',
        'ciloveFtp' => $nastaveni['sciator']['ftp'],
        'urlMigrace' => $nastaveni['sciator']['urlMigrace'],
        'hesloMigrace' => $nastaveni['sciator']['hesloMigrace'],
        'log' => $nastaveni['sciator']['log'],
        'souborNastaveni' => basename(__DIR__ . '/../nastaveni/nastaveni-sciator.php'),
    ]);
} else {
    echo "error: unexpected branch '$vetev'\n";
    exit(1);
}
