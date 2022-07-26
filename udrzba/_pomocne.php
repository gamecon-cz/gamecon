<?php

function nasad(array $nastaveni) {

    $deployment     = __DIR__ . '/ftp-deployment.php';
    $zdrojovaSlozka = realpath($nastaveni['zdrojovaSlozka']);

    // some files are always required by Composer autoloader, even if not needed, so we have to copy them to server, even if they are for dev (tests) only
    $alwaysAutoloadedRelative      = getFilesAlwaysRequiredByAutoloader();
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
      !/nastaveni/verejne-{$nastaveni['souborSkrytehoNastaveni']}
      !/nastaveni/{$nastaveni['souborSkrytehoNastaveni']}
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
      /ui

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

    $souborSeSkrytymNastavenim = $zdrojovaSlozka . '/nastaveni/' . $nastaveni['souborSkrytehoNastaveni'];
    if (!is_file($souborSeSkrytymNastavenim)) {
        // ENV data viz například .github/workflows/deploy-jakublounek.yml
        file_put_contents($souborSeSkrytymNastavenim, <<<PHP
define('DB_USER', '{$_ENV['DB_USER']}');
define('DB_PASS', '{$_ENV['DB_PASS']}');
define('DB_NAME', '{$_ENV['DB_NAME']}');
define('DB_SERV', '{$_ENV['DB_SERV']}');

// uživatel s přístupem k změnám struktury
define('DBM_USER', '{$_ENV['DBM_USER']}');
define('DBM_PASS', '{$_ENV['DB_PASS']}');

define('MIGRACE_HESLO', '{$_ENV['MIGRACE_HESLO']}');
define('SECRET_CRYPTO_KEY', '{$_ENV['SECRET_CRYPTO_KEY']}');

define('CRON_KEY', '{$_ENV['CRON_KEY']}');
define('GOOGLE_API_CREDENTIALS', '{$_ENV['GOOGLE_API_CREDENTIALS']}');
PHP);
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
