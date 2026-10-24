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

require_once __DIR__ . '/../nastaveni/zavadec-autoloader.php';
require_once __DIR__ . '/_pomocne.php';

$nastaveni = [];
if (file_exists(__DIR__ . '/../nastaveni/nastaveni-nasazovani.php')) {
    $nastaveni = require __DIR__ . '/../nastaveni/nastaveni-nasazovani.php';
}

chdir(__DIR__ . '/../');

$skipNoChangesCheck = getopt('', ['skip-no-changes-check']);
if (!$skipNoChangesCheck) {
    // testování větve před pushem a čistoty repa, aby se na FTP nedostalo smetí
    exec('git status', $out);
    if (!preg_match('/^nothing to commit, working (tree|directory) clean$/', end($out))) {
        echo "error: working directory is not clean\n";
        exit(1);
    }
}

// spuštění testů
$skipTests = getopt('', ['skip-tests']);
if (!$skipTests) {
    call_check(['php', __DIR__ . '/testuj.php']);
}

exec('git rev-parse --abbrev-ref HEAD', $branchOutput);
$vetev = $branchOutput[0];

$dryRun = (bool)getopt('', ['dry-run']);
if ($dryRun && !in_array($vetev, ['main', 'beta'], true)) {
    echo "error: --dry-run works only on the main and beta branches, on '$vetev' it would deploy for real\n";
    exit(1);
}

// nasazení
if ($vetev === 'main') {
    nasad([
        'vetev'                    => $vetev,
        'zdrojovaSlozka'           => __DIR__ . '/..',
        'ciloveFtp'                => $nastaveni['ostra']['ftp'] ?? (getenv('FTP_BASE_URL') . '/' . getenv('FTP_DIR')),
        // DEPLOY_RSYNC_TARGET switches from FTP to rsync; ostra and beta each have their own SSH key, limited to its directory
        'ciloveRsync'              => getenv('DEPLOY_RSYNC_TARGET')
            ?: '',
        'dryRun'                   => $dryRun,
        'serverPassphrase'         => getenv('FTP_SERVER_PASSPHRASE')
            ?: '',
        'hesloMigrace'             => $nastaveni['ostra']['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
        'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-produkce.php',
    ]);
} elseif ($vetev === 'vpsfree') {
    nasad([
        'vetev'                    => $vetev,
        'zdrojovaSlozka'           => __DIR__ . '/..',
        'ciloveFtp'                => $nastaveni['ostra']['ftp'] ?? (getenv('FTP_BASE_URL') . '/' . getenv('FTP_DIR')),
        'serverPassphrase'         => getenv('FTP_SERVER_PASSPHRASE')
            ?: '',
        'hesloMigrace'             => $nastaveni['ostra']['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
        'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-vpsfree.php',
    ]);
} elseif ($vetev === 'beta') {
    nasad([
        'vetev'                    => $vetev,
        'zdrojovaSlozka'           => __DIR__ . '/..',
        'ciloveFtp'                => $nastaveni['beta']['ftp'] ?? (getenv('FTP_BASE_URL') . '/' . getenv('FTP_DIR')),
        'ciloveRsync'              => getenv('DEPLOY_RSYNC_TARGET')
            ?: '',
        'dryRun'                   => $dryRun,
        'hesloMigrace'             => $nastaveni['beta'] ['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
        'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-beta.php',
    ]);
} elseif ($vetev === 'blackarrow') {
    nasad([
        'vetev'                    => $vetev,
        'zdrojovaSlozka'           => __DIR__ . '/..',
        'ciloveFtp'                => $nastaveni['blackarrow']['ftp'] ?? (getenv('FTP_BASE_URL') . '/' . getenv('FTP_DIR')),
        'hesloMigrace'             => $nastaveni['blackarrow'] ['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
        'log'                      => $nastaveni['blackarrow']['log'],
        'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-blackarrow.php',
    ]);
} elseif ($vetev === 'jakublounek') {
    nasad([
        'vetev'                    => $vetev,
        'zdrojovaSlozka'           => __DIR__ . '/..',
        'ciloveFtp'                => $nastaveni['jakublounek']['ftp'] ?? (getenv('FTP_BASE_URL') . '/' . getenv('FTP_DIR')),
        'hesloMigrace'             => $nastaveni['jakublounek'] ['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
        'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-jakublounek.php',
    ]);
} elseif ($vetev === 'misahojna') {
    nasad([
        'vetev'                    => $vetev,
        'zdrojovaSlozka'           => __DIR__ . '/..',
        'ciloveFtp'                => $nastaveni['misahojna']['ftp'] ?? (getenv('FTP_BASE_URL') . '/' . getenv('FTP_DIR')),
        'hesloMigrace'             => $nastaveni['misahojna'] ['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
        'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-misahojna.php',
    ]);
} elseif ($vetev === 'sciator') {
    nasad([
        'vetev'                    => $vetev,
        'zdrojovaSlozka'           => __DIR__ . '/..',
        'ciloveFtp'                => $nastaveni['sciator']['ftp'] ?? (getenv('FTP_BASE_URL') . '/' . getenv('FTP_DIR')),
        'hesloMigrace'             => $nastaveni['sciator'] ['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
        'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-sciator.php',
    ]);
} else {
    echo "error: unexpected branch '$vetev'\n";
    exit(1);
}
