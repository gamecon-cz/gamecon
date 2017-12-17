<?php

function nasad($nastaveni) {

  $deployment = realpath(__DIR__ . '/../vendor/dg/ftp-deployment/deployment');

  $nastaveniDeploymentu = '
    log     = /dev/null
    remote  = ' . $nastaveni['ciloveFtp'] . '
    local   = ' . realpath($nastaveni['zdrojovaSlozka']) . '
    ignore  = "
      /_*

      /cache/private/*
      !/cache/private/.htaccess
      /cache/public/*
      !/cache/public/.htaccess
      !/cache/public/sestavene

      /dokumentace

      /nastaveni/*
      !/nastaveni/' . $nastaveni['souborNastaveni'] . '
      !/nastaveni/nastaveni.php
      !/nastaveni/zavadec-zaklad.php
      !/nastaveni/zavadec.php

      /node_modules

      /web/soubory/*
      !/web/soubory/styl
      !/web/soubory/*.js
      !/web/soubory/systemove/aktivity/.keep
      !/web/soubory/systemove/avatary/.keep
      !/web/soubory/systemove/fotky/.keep
    "
    preprocess = no
    allowDelete = yes
  ';

  // nahrání souborů
  msg('synchronizuji soubory na vzdáleném ftp');
  $souborNastaveniDeploymentu = '/tmp/' . mt_rand();
  file_put_contents($souborNastaveniDeploymentu, $nastaveniDeploymentu);
  try {
    call_check(['php', $deployment, $souborNastaveniDeploymentu]);
  } finally {
    unlink($souborNastaveniDeploymentu);
  }

  // migrace DB
  msg('spouštím migrace na vzdálené databázi');
  call_check([
    'curl',
    '--data', 'cFleeVar=' . $nastaveni['hesloMigrace'],
    '--silent', // skrýt progressbar
    $nastaveni['urlMigrace']
  ]);

  msg('nasazení dokončeno');
}

function msg($msg) {
    echo date('H:i:s') . ' ' . $msg . "\n";
}

function call_check($params) {
    $command = escapeshellcmd($params[0]);
    $args    = array_map('escapeshellarg', array_slice($params, 1));
    $args    = implode(' ', $args);

    system($command . ' ' . $args, $exitStatus);
    if ($exitStatus !== 0) throw new Exception('Příkaz skončil chybou.');
}
