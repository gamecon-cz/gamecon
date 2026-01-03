<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Vyjimkovac\Vyjimkovac;

class KopieOstreDatabaze
{
    public static function createFromGlobals()
    {
        return new static(
            NastrojeDatabaze::vytvorZGlobals(),
            SystemoveNastaveni::zGlobals(),
            Vyjimkovac::vytvorZGlobals(),
        );
    }

    public function __construct(
        private readonly NastrojeDatabaze $nastrojeDatabaze,
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly Vyjimkovac $vyjimkovac,
    ) {
    }

    public function zkopirujDatabazi(string $zdrojovaDbName, bool $migrovat = true): void
    {
        set_time_limit(120);

        $zdrojovaDbName = trim($zdrojovaDbName);
        $jeTestovaciDb = defined('DB_TEST_PREFIX') && str_starts_with($zdrojovaDbName, DB_TEST_PREFIX);
        $jeOstraDb     = $zdrojovaDbName === $this->systemoveNastaveni->prihlasovaciUdajeOstreDatabaze()['DB_NAME'];
        if (!preg_match('~^gamecon(_\d{4})?$~', $zdrojovaDbName) && !$jeTestovaciDb && !$jeOstraDb) {
            throw new \RuntimeException("Nepovolený název databáze: {$zdrojovaDbName}");
        }

        $puvodniPriZalogovaniOdeslatMailem = $this->vyjimkovac->priZalogovaniOdeslatMailem();
        $puvodniZobrazeniChyb = $this->vyjimkovac->zobrazeni();

        $this->vyjimkovac->priZalogovaniOdeslatMailem(false);
        $this->vyjimkovac->zobrazeni($this->vyjimkovac::TRACY);

        try {
            $nastaveniZdroj           = $this->systemoveNastaveni->prihlasovaciUdajeOstreDatabaze();
            $nastaveniZdroj['DB_NAME'] = $zdrojovaDbName;

            // pro speciální archivy na stejném serveru použijeme lokální DB účet
            if ($zdrojovaDbName === 'gamecon_2024') {
                $lokalni = $this->systemoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();
                $nastaveniZdroj['DBM_USER'] = $lokalni['DBM_USER'];
                $nastaveniZdroj['DBM_PASS'] = $lokalni['DBM_PASS'];
                $nastaveniZdroj['DB_SERV']  = $lokalni['DB_SERV'];
                $nastaveniZdroj['DB_PORT']  = $lokalni['DB_PORT'];
            }

            if ($nastaveniZdroj['DB_SERV'] === DB_SERV && $nastaveniZdroj['DB_NAME'] === DB_NAME) {
                throw new \RuntimeException('Kopírovat sebe sama nemá smysl');
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'kopie_databaze_');
            if ($tempFile === false) {
                throw new \RuntimeException('Nepodařilo se vytvořit dočasný soubor');
            }

            [
                'DB_SERV' => $dbServ,
                'DBM_USER' => $dbmUser,
                'DBM_PASS' => $dbmPass,
                'DB_NAME' => $dbName,
                'DB_PORT' => $dbPort,
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

                if ($migrovat) {
                    (new SqlMigrace($this->systemoveNastaveni))->migruj();
                }
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
