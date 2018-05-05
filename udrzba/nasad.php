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
if($vetev !== 'preview') {
  echo "notice: you're not on automatically deployed branch, deplyoment skipped\n";
  exit(0);
}
exec('git status', $out);
if(!in_array('nothing to commit, working tree clean', $out)) {
  echo "error: working directory is not clean\n";
  exit(1);
}

// sestavení souborů
call_check(['php', __DIR__ . '/sestav.php']);

// nasazení
if($vetev == 'preview') {
  nasad([
    'zdrojovaSlozka'  =>  __DIR__ . '/..',
    'ciloveFtp'       =>  $nastaveni['ostra']['ftp'],
    'urlMigrace'      =>  $nastaveni['ostra']['urlMigrace'],
    'hesloMigrace'    =>  $nastaveni['ostra']['hesloMigrace'],
    'souborNastaveni' =>  'nastaveni-produkce.php',
  ]);
  nasad([
    'zdrojovaSlozka'  =>  __DIR__ . '/..',
    'ciloveFtp'       =>  $nastaveni['beta']['ftp'],
    'urlMigrace'      =>  $nastaveni['beta']['urlMigrace'],
    'hesloMigrace'    =>  $nastaveni['beta']['hesloMigrace'],
    'souborNastaveni' =>  'nastaveni-beta.php',
  ]);
} else {
  nasad([
    'zdrojovaSlozka'  =>  __DIR__ . '/..',
    'ciloveFtp'       =>  $nastaveni['redesign']['ftp'],
    'urlMigrace'      =>  $nastaveni['redesign']['urlMigrace'],
    'hesloMigrace'    =>  $nastaveni['redesign']['hesloMigrace'],
    'souborNastaveni' =>  'nastaveni-redesign.php',
  ]);
}
