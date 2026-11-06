<?php

/**
 * Nahraje aktuální stav na ostrou (větev main) nebo na betu (větev beta) přes rsync po SSH.
 *
 * Spouští ho GitHub Actions (.github/workflows/deploy-*.yml). Potřebuje DEPLOY_RSYNC_TARGET, DEPLOY_SSH_KEY,
 * DEPLOY_SSH_KNOWN_HOSTS a další proměnné prostředí, viz dokumentace/nasazeni.md.
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
    // kontrola čistoty repa, aby se na server nedostalo smetí
    exec('git status', $out);
    if (!preg_match('/^nothing to commit, working (tree|directory) clean$/', end($out))) {
        echo "error: working directory is not clean\n";
        exit(1);
    }
}

exec('git rev-parse --abbrev-ref HEAD', $branchOutput);
$vetev = $branchOutput[0];

// větev a cíl se ověří dřív než testy, které trvají minuty; ostrá a beta mají každá vlastní SSH klíč omezený na svou složku
$prostredi = [
    'main' => ['klic' => 'ostra', 'verejneNastaveni' => 'produkce'],
    'beta' => ['klic' => 'beta', 'verejneNastaveni' => 'beta'],
];
if (!isset($prostredi[$vetev])) {
    echo "error: unexpected branch '$vetev'\n";
    exit(1);
}
$ciloveRsync = (string)getenv('DEPLOY_RSYNC_TARGET');
if ($ciloveRsync === '') {
    echo "error: DEPLOY_RSYNC_TARGET is not set (GitHub secret BETA_DEPLOY_RSYNC_TARGET or OSTRA_DEPLOY_RSYNC_TARGET), the deploy goes only over rsync\n";
    exit(1);
}

// spuštění testů
$skipTests = getopt('', ['skip-tests']);
if (!$skipTests) {
    call_check(['php', __DIR__ . '/testuj.php']);
}

$dryRun = (bool)getopt('', ['dry-run']);

nasad([
    'vetev'                    => $vetev,
    'zdrojovaSlozka'           => __DIR__ . '/..',
    'ciloveRsync'              => $ciloveRsync,
    'dryRun'                   => $dryRun,
    'hesloMigrace'             => $nastaveni[$prostredi[$vetev]['klic']]['hesloMigrace'] ?? getenv('MIGRACE_HESLO'),
    'souborVerejnehoNastaveni' => __DIR__ . '/../nastaveni/verejne-nastaveni-' . $prostredi[$vetev]['verejneNastaveni'] . '.php',
]);
