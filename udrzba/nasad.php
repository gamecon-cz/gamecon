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

require_once __DIR__ . '/../nastaveni/nastaveni-ftpdeploy.php';

chdir(__DIR__ . '/../');

$deployment = escapeshellarg(realpath(__DIR__ . '/../vendor/dg/ftp-deployment/deployment'));

// testování větve před pushem a čistoty repa, aby se na FTP nedostalo smetí
exec('git rev-parse --abbrev-ref HEAD', $out);
$vetev = $out[0]; // TODO test na master?
if($vetev !== 'master') {
  echo "error: you're not on master branch\n";
  exit(1);
}
exec('git status', $out);
if(end($out) !== 'nothing to commit, working directory clean') {
  echo "error: working directory is not clean\n";
  exit(1);
}

// nahrání souborů - beta
$nastaveniBeta = escapeshellarg(realpath(__DIR__ . '/pomocne/nastaveni-ftpdeploy-beta.php'));
system("php $deployment $nastaveniBeta");

// migrace DB - beta
echo "\nMigrace DB - beta\n";
system(
  'curl --data "cFleeVar=' . BETA_MIGRACE_HESLO . '" --silent ' . // skrýt progressbar
  escapeshellarg('http://admin.beta.gamecon.cz/migrace.php')
);
echo "\n\n";

// nahrání souborů - ostrá
$nastaveniOstra = escapeshellarg(realpath(__DIR__ . '/pomocne/nastaveni-ftpdeploy-ostra.php'));
system("php $deployment $nastaveniOstra");

// migrace DB - ostrá
echo "\nMigrace DB - ostrá\n";
system(
  'curl --data "cFleeVar=' . OSTRA_MIGRACE_HESLO . '" --silent ' . // skrýt progressbar
  escapeshellarg('https://admin.gamecon.cz/migrace.php')
);
echo "\n\n";
