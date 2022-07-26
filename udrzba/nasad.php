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

$nastaveni = [];
if (file_exists(__DIR__ . '/../nastaveni/nastaveni-nasazovani.php')) {
    $nastaveni = require __DIR__ . '/../nastaveni/nastaveni-nasazovani.php';
}

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
        'vetev'                   => $vetev,
        'zdrojovaSlozka'          => __DIR__ . '/..',
        'ciloveFtp'               => $nastaveni['ostra']['ftp'] ?? ($_ENV['FTP_BASE_URL'] . '/' . $_ENV['FTP_DIR']),
        'urlMigrace'              => $nastaveni['ostra']['urlMigrace'] ?? (URL_ADMIN . '/' . basename(__DIR__ . '/../admin/migrace.php')),
        'hesloMigrace'            => $nastaveni['ostra']['hesloMigrace'] ?? $_ENV['MIGRACE_HESLO'],
        'souborSkrytehoNastaveni' => souborSkrytehoNastaveniPodleVerejneho(__DIR__ . '/../nastaveni/verejne-nastaveni-produkce.php'),
    ]);
} elseif ($vetev === 'beta') {
    nasad([
        'vetev'                   => $vetev,
        'zdrojovaSlozka'          => __DIR__ . '/..',
        'ciloveFtp'               => $nastaveni['beta']['ftp'] ?? ($_ENV['FTP_BASE_URL'] . '/' . $_ENV['FTP_DIR']),
        'urlMigrace'              => $nastaveni['beta']['urlMigrace'] ?? (URL_ADMIN . '/' . basename(__DIR__ . '/../admin/migrace.php')),
        'hesloMigrace'            => $nastaveni['beta'] ['hesloMigrace'] ?? $_ENV['MIGRACE_HESLO'],
        'souborSkrytehoNastaveni' => souborSkrytehoNastaveniPodleVerejneho(__DIR__ . '/../nastaveni/verejne-nastaveni-beta.php'),
    ]);
} elseif ($vetev === 'blackarrow') {
    nasad([
        'vetev'                   => $vetev,
        'zdrojovaSlozka'          => __DIR__ . '/..',
        'ciloveFtp'               => $nastaveni['blackarrow']['ftp'] ?? ($_ENV['FTP_BASE_URL'] . '/' . $_ENV['FTP_DIR']),
        'urlMigrace'              => $nastaveni['blackarrow']['urlMigrace'] ?? (URL_ADMIN . '/' . basename(__DIR__ . '/../admin/migrace.php')),
        'hesloMigrace'            => $nastaveni['blackarrow'] ['hesloMigrace'] ?? $_ENV['MIGRACE_HESLO'],
        'log'                     => $nastaveni['blackarrow']['log'],
        'souborSkrytehoNastaveni' => souborSkrytehoNastaveniPodleVerejneho(__DIR__ . '/../nastaveni/verejne-nastaveni-blackarrow.php'),
    ]);
} elseif ($vetev === 'jakublounek') {
    nasad([
        'vetev'                   => $vetev,
        'zdrojovaSlozka'          => __DIR__ . '/..',
        'ciloveFtp'               => $nastaveni['jakublounek']['ftp'] ?? ($_ENV['FTP_BASE_URL'] . '/' . $_ENV['FTP_DIR']),
        'urlMigrace'              => $nastaveni['jakublounek']['urlMigrace'] ?? (URL_ADMIN . '/' . basename(__DIR__ . '/../admin/migrace.php')),
        'hesloMigrace'            => $nastaveni['jakublounek'] ['hesloMigrace'] ?? $_ENV['MIGRACE_HESLO'],
        'log'                     => $nastaveni['jakublounek']['log'],
        'souborSkrytehoNastaveni' => souborSkrytehoNastaveniPodleVerejneho(__DIR__ . '/../nastaveni/verejne-nastaveni-jakublounek.php'),
    ]);
} elseif ($vetev === 'misahojna') {
    nasad([
        'vetev'                   => $vetev,
        'zdrojovaSlozka'          => __DIR__ . '/..',
        'ciloveFtp'               => $nastaveni['misahojna']['ftp'] ?? ($_ENV['FTP_BASE_URL'] . '/' . $_ENV['FTP_DIR']),
        'urlMigrace'              => $nastaveni['misahojna']['urlMigrace'] ?? (URL_ADMIN . '/' . basename(__DIR__ . '/../admin/migrace.php')),
        'hesloMigrace'            => $nastaveni['misahojna'] ['hesloMigrace'] ?? $_ENV['MIGRACE_HESLO'],
        'log'                     => $nastaveni['misahojna']['log'],
        'souborSkrytehoNastaveni' => souborSkrytehoNastaveniPodleVerejneho(__DIR__ . '/../nastaveni/verejne-nastaveni-misahojna.php'),
    ]);
} elseif ($vetev === 'sciator') {
    nasad([
        'vetev'                   => $vetev,
        'zdrojovaSlozka'          => __DIR__ . '/..',
        'ciloveFtp'               => $nastaveni['sciator']['ftp'] ?? ($_ENV['FTP_BASE_URL'] . '/' . $_ENV['FTP_DIR']),
        'urlMigrace'              => $nastaveni['sciator']['urlMigrace'] ?? (URL_ADMIN . '/' . basename(__DIR__ . '/../admin/migrace.php')),
        'hesloMigrace'            => $nastaveni['sciator'] ['hesloMigrace'] ?? $_ENV['MIGRACE_HESLO'],
        'log'                     => $nastaveni['sciator']['log'],
        'souborSkrytehoNastaveni' => souborSkrytehoNastaveniPodleVerejneho(__DIR__ . '/../nastaveni/verejne-nastaveni-sciator.php'),
    ]);
} else {
    echo "error: unexpected branch '$vetev'\n";
    exit(1);
}
