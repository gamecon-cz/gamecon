<?php
declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Vyjimkovac\Vyjimkovac;

readonly class KopieOstreDatabaze
{
    public static function createFromGlobals(): static
    {
        return new static(
            NastrojeDatabaze::vytvorZGlobals(),
            SystemoveNastaveni::zGlobals(),
            Vyjimkovac::vytvorZGlobals(),
        );
    }

    public function __construct(
        private NastrojeDatabaze   $nastrojeDatabaze,
        private SystemoveNastaveni $systemoveNastaveni,
        private Vyjimkovac         $vyjimkovac,
    ) {
    }

    public function zkopirujZeSouboruZalohy(string $gzipSouborZalohy): void
    {
        set_time_limit(120);

        // Security: verify the file is inside an allowed backup directory and is an export_ file
        $allowedBaseDirs = $this->systemoveNastaveni->jsmeNaLocale()
            ? [realpath($this->systemoveNastaveni->rootAdresarProjektu() . '/backup/db')]
            : [realpath($this->systemoveNastaveni->rootAdresarProjektu() . '/../ostra/backup/db')];
        // Also allow archive year directories: ../{YYYY}/backup/db
        $projectParent = dirname($this->systemoveNastaveni->rootAdresarProjektu());
        foreach (glob($projectParent . '/*/backup/db', GLOB_ONLYDIR) as $dir) {
            $allowedBaseDirs[] = realpath($dir);
        }
        $allowedBaseDirs = array_filter($allowedBaseDirs);

        $realFile     = realpath($gzipSouborZalohy);
        $inAllowedDir = $realFile !== false && (bool)array_filter(
                $allowedBaseDirs,
                fn(
                    $dir,
                ) => str_starts_with($realFile, $dir . '/'),
            );

        if (!$inAllowedDir || !preg_match('~^export_.*\.sql\.gz$~', basename($realFile))) {
            throw new \RuntimeException("Nepovolený soubor zálohy: {$gzipSouborZalohy}");
        }

        $puvodniPriZalogovaniOdeslatMailem = $this->vyjimkovac->priZalogovaniOdeslatMailem();
        $puvodniZobrazeniChyb              = $this->vyjimkovac->zobrazeni();
        $this->vyjimkovac->priZalogovaniOdeslatMailem(false);
        $this->vyjimkovac->zobrazeni($this->vyjimkovac::TRACY);

        try {
            // Decompress .gz to a temp plain SQL file (removeDefiners needs plain text)
            $tempFile = tempnam($this->systemoveNastaveni->cacheDir(), 'kopie_databaze_');
            if ($tempFile === false) {
                throw new \RuntimeException('Nepodařilo se vytvořit dočasný soubor');
            }

            try {
                // Decompress gzip to plain SQL
                $gz = gzopen($realFile, 'rb');
                if ($gz === false) {
                    throw new \RuntimeException("Nepodařilo se otevřít soubor zálohy: {$realFile}");
                }
                $fp = fopen($tempFile, 'wb');
                if ($fp === false) {
                    gzclose($gz);
                    throw new \RuntimeException('Nepodařilo se otevřít dočasný soubor pro zápis');
                }
                while (!gzeof($gz)) {
                    fwrite($fp, gzread($gz, 524288)); // 512 KB chunks
                }
                gzclose($gz);
                fclose($fp);

                // Strip DEFINERs from the plain SQL
                NastrojeDatabaze::removeDefiners($tempFile);

                // Connect to local (beta) DB
                [
                    'DB_SERV'  => $dbServ,
                    'DBM_USER' => $dbmUser,
                    'DBM_PASS' => $dbmPass,
                    'DB_NAME'  => $dbName,
                    'DB_PORT'  => $dbPort,
                ] = $this->systemoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();

                $localConnection = _dbConnect(
                    dbServer: $dbServ,
                    dbUser: $dbmUser,
                    dbPass: $dbmPass,
                    dbPort: $dbPort,
                    dbName: $dbName,
                    persistent: false,
                );

                $this->nastrojeDatabaze->vymazVseZHlavniDatabaze($localConnection);
                (new \MySQLImport($localConnection))->load($tempFile);

                (new SqlMigrace($this->systemoveNastaveni))->migruj();
            } finally {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }
        } finally {
            $this->vyjimkovac->priZalogovaniOdeslatMailem($puvodniPriZalogovaniOdeslatMailem);
            $this->vyjimkovac->zobrazeni($puvodniZobrazeniChyb);
        }
    }

    public function zkopirujDatabazi(string $zdrojovaDbName): void
    {
        set_time_limit(120);

        $zdrojovaDbName = trim($zdrojovaDbName);
        $jeTestovaciDb  = defined('DB_TEST_PREFIX') && str_starts_with($zdrojovaDbName, DB_TEST_PREFIX);
        $jeOstraDb      = $zdrojovaDbName === $this->systemoveNastaveni->prihlasovaciUdajeOstreDatabaze()['DB_NAME'];
        if (!preg_match('~^gamecon(_\d{4})?$~', $zdrojovaDbName) && !$jeTestovaciDb && !$jeOstraDb) {
            throw new \RuntimeException("Nepovolený název databáze: {$zdrojovaDbName}");
        }

        $puvodniPriZalogovaniOdeslatMailem = $this->vyjimkovac->priZalogovaniOdeslatMailem();
        $puvodniZobrazeniChyb              = $this->vyjimkovac->zobrazeni();

        $this->vyjimkovac->priZalogovaniOdeslatMailem(false);
        $this->vyjimkovac->zobrazeni($this->vyjimkovac::TRACY);

        try {
            $nastaveniZdroj            = $this->systemoveNastaveni->prihlasovaciUdajeOstreDatabaze();
            $nastaveniZdroj['DB_NAME'] = $zdrojovaDbName;

            // pro speciální archivy na stejném serveru použijeme lokální DB účet
            if ($zdrojovaDbName === 'gamecon_2024') {
                $lokalni                    = $this->systemoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();
                $nastaveniZdroj['DBM_USER'] = $lokalni['DBM_USER'];
                $nastaveniZdroj['DBM_PASS'] = $lokalni['DBM_PASS'];
                $nastaveniZdroj['DB_SERV']  = $lokalni['DB_SERV'];
                $nastaveniZdroj['DB_PORT']  = $lokalni['DB_PORT'];
            }

            if (
                $nastaveniZdroj['DB_SERV'] === DB_SERV
                && $nastaveniZdroj['DB_NAME'] === DB_NAME
                && !$this->systemoveNastaveni->jsmeNaLocale()
            ) {
                throw new \RuntimeException('Kopírovat sebe sama nemá smysl');
            }

            $tempFile = tempnam($this->systemoveNastaveni->cacheDir(), 'kopie_databaze_');
            if ($tempFile === false) {
                throw new \RuntimeException('Nepodařilo se vytvořit dočasný soubor');
            }

            [
                'DB_SERV'  => $dbServ,
                'DBM_USER' => $dbmUser,
                'DBM_PASS' => $dbmPass,
                'DB_NAME'  => $dbName,
                'DB_PORT'  => $dbPort,
            ] = $this->systemoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();

            $localConnection = _dbConnect(
                dbServer: $dbServ,
                dbUser: $dbmUser,
                dbPass: $dbmPass,
                dbPort: $dbPort,
                dbName: $dbName,
                persistent: false,
            );

            try {
                $mysqldump = $this->nastrojeDatabaze->vytvorMysqldumpDatabaze($nastaveniZdroj);
                $mysqldump->start($tempFile);
                NastrojeDatabaze::removeDefiners($tempFile);

                $this->nastrojeDatabaze->vymazVseZHlavniDatabaze($localConnection);
                (new \MySQLImport($localConnection))->load($tempFile);

                (new SqlMigrace($this->systemoveNastaveni))->migruj();
            } finally {
                if (is_file($tempFile)) {
                    @unlink($tempFile);
                }
            }
        } finally {
            $this->vyjimkovac->priZalogovaniOdeslatMailem($puvodniPriZalogovaniOdeslatMailem);
            $this->vyjimkovac->zobrazeni($puvodniZobrazeniChyb);
        }
    }
}
