<?php

// require nastavení je potřeba i tady, protože ftpdeployment volá tento soubor samostatně
require_once __DIR__ . '/../../nastaveni/nastaveni-ftpdeploy.php';

return [
  'Gamecon beta' =>  [
    'remote'    =>  'ftp://' . OSTRA_UZIVATEL . ':' . OSTRA_HESLO . '@' . OSTRA_ADRESA,
    'local'     =>  realpath(__DIR__ . '/../../'),
    'ignore'    =>  '
      /_*
      /cache/private/*
      !/cache/private/.htaccess
      /cache/public/*
      !/cache/public/.htaccess
      /dokumentace
      /nastaveni/*
      !/nastaveni/nastaveni-produkce.php
      !/nastaveni/nastaveni.php
      !/nastaveni/zavadec.php
      /web/soubory/*
      !/web/soubory/styl
      !/web/soubory/*.js
      !/web/soubory/systemove/aktivity/.keep
      !/web/soubory/systemove/avatary/.keep
      !/web/soubory/systemove/fotky/.keep
    ',
    'allowdelete' =>  true,
    'preprocess'  =>  false,
  ],
  'log'   =>  '/dev/null',
];
