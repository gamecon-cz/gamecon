<?php

function nasad(array $nastaveni) {

    $deployment = __DIR__ . '/ftp-deployment.php';
    $zdrojovaSlozka = realpath($nastaveni['zdrojovaSlozka']);

    // some files are always required by Composer autoloader, even if not needed, so we have to copy them to server, even if they are for dev (tests) only
    $alwaysAutoloadedRelative = getFilesAlwaysRequiredByAutoloader();
    $nutneKvuliComposerAutoRequire = implode(
        "      \n",
        array_map(static function (string $file) {
            return "!$file";
        }, $alwaysAutoloadedRelative)
    );

    $logFile = $nastaveni['log'] ?? 'nasad.log';

    $nastaveniDeploymentu = "
    log     = {$logFile}
    remote  = {$nastaveni['ciloveFtp']}
    local   = {$zdrojovaSlozka}
    ignore  = '
      /_*
      /.git
      /.github
      /.idea
      /.vscode
      /.phpunit.result.cache

      /backup/*
      /cache/private/*
      /admin/stamps/*
      !/cache/private/.htaccess
      /cache/public/*
      !/cache/public/.htaccess
      !/cache/public/sestavene

      /dokumentace

      /nastaveni/*
      !/nastaveni/verejne-{$nastaveni['souborNastaveni']}
      !/nastaveni/{$nastaveni['souborNastaveni']}
      !/nastaveni/db-migrace.php
      !/nastaveni/initial-fatal-error-handler.php
      !/nastaveni/nastaveni.php
      !/nastaveni/nastaveni-vychozi.php
      !/nastaveni/zavadec.php
      !/nastaveni/zavadec-zaklad.php
      !/nastaveni/google_api_client_secret_produkce.json
      !/nastaveni/google_api_client_secret_beta.json

      /tests
      /udrzba
      /node_modules

      /web/soubory/*
      !/web/soubory/blackarrow
      !/web/soubory/styl
      !/web/soubory/*.js
      !/web/soubory/*.html
      !/web/soubory/systemove/*/.gitkeep

      /vendor/phpunit
      /vendor/sebastian
      /vendor/phpdocumentor
      /vendor/webmozart
      /vendor/myclabs/deep-copy
      /vendor/nikic/php-parser
      /vendor/phpspec/prophecy
      /vendor/phar-io/manifest
      /vendor/phar-io/version
      /vendor/composer/tmp-*
      {$nutneKvuliComposerAutoRequire}

      /nasad.log
    '
    preprocess = no
    allowDelete = yes
  ";

    if (!empty($nastaveni['vetev'])) {
        nadpis("NASAZUJI '{$nastaveni['vetev']}'");
    }

    // kontroly
    if (!is_file($zdrojovaSlozka . '/nastaveni/' . $nastaveni['souborNastaveni'])) {
        throw new Exception('Nenalezen soubor s nastaveními pro vzdálený server.');
    }

    // nahrání souborů
    msg('synchronizuji soubory na vzdáleném ftp');
    $souborNastaveniDeploymentu = tempnam(sys_get_temp_dir(), 'gamecon-ftpdeploy-');
    file_put_contents($souborNastaveniDeploymentu, $nastaveniDeploymentu);
    try {
        call_check(['php', $deployment, $souborNastaveniDeploymentu]);
    } finally {
        unlink($souborNastaveniDeploymentu);
    }

    // migrace DB
    runMigrationsOnRemote($nastaveni['urlMigrace'], $nastaveni['hesloMigrace']);

    msg('nasazení dokončeno');
}

function runMigrationsOnRemote(string $urlMigrace, string $hesloMigrace) {
    msg("spouštím migrace na vzdálené databázi");
    call_check([
        'curl',
        '--data', http_build_query(['migraceHeslo' => $hesloMigrace]),
        '--silent', // skrýt progressbar
        $urlMigrace,
    ]);
}

function getFilesAlwaysRequiredByAutoloader(): array {
    if (!file_exists(__DIR__ . '/../vendor/composer/autoload_files.php')) {
        return [];
    }
    $alwaysAutoloadedAbsolute = require __DIR__ . '/../vendor/composer/autoload_files.php';

    return array_map(
        static function (string $absolutePath) {
            // create path relative to project root
            $vendorPosition = strpos($absolutePath, '/vendor/');
            return substr($absolutePath, $vendorPosition);
        },
        $alwaysAutoloadedAbsolute);
}

function msg($msg) {
    echo date('H:i:s') . ' ' . $msg . "\n";
}

function nadpis(string $msg) {
    $length = mb_strlen($msg);
    $okraj = str_repeat('=', $length);
    $eol = PHP_EOL;
    echo "  $okraj  $eol";
    echo "‖ $msg ‖$eol";
    echo "  $okraj  $eol";
}

function call_check($params) {
    $command = escapeshellcmd($params[0]);
    $args = array_map('escapeshellarg', array_slice($params, 1));
    $args = implode(' ', $args);
    $commandWithArgs = $command . ' ' . $args;

    passthru($commandWithArgs, $exitStatus);
    if ($exitStatus !== 0) {
        throw new Exception("Chyba příkazu '$commandWithArgs'", $exitStatus);
    }
}
