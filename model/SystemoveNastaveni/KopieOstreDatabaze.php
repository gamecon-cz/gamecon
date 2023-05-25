<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Vyjimkovac\Vyjimkovac;

class KopieOstreDatabaze
{
    public static function createFromGlobals()
    {
        return new static(
            NastrojeDatabaze::vytvorZGlobals(),
            SystemoveNastaveni::vytvorZGlobals(),
            Vyjimkovac::vytvorZGlobals(),
        );
    }

    public function __construct(
        private readonly NastrojeDatabaze   $nastrojeDatabaze,
        private readonly SystemoveNastaveni $systemoveNastaveni,
        private readonly Vyjimkovac         $vyjimkovac,
    )
    {
    }

    public function zkopirujOstrouDatabazi()
    {
        $puvodniPriZalogovaniOdeslatMailem = $this->vyjimkovac->priZalogovaniOdeslatMailem();
        // protože migrace mohou padnout, zkopírovaná ostrá databáze nemusí ještě mít nové tabulky a pak odeslání taky padne
        $this->vyjimkovac->priZalogovaniOdeslatMailem(false);
        $puvodniZobrazeniChyb = $this->vyjimkovac->zobrazeni();
        $this->vyjimkovac->zobrazeni($this->vyjimkovac::TRACY);

        $nastaveniOstre = $this->systemoveNastaveni->prihlasovaciUdajeOstreDatabaze();
        if ($nastaveniOstre['DB_SERV'] === DB_SERV && $nastaveniOstre['DB_NAME'] === DB_NAME) {
            throw new \RuntimeException('Kopírovat sebe sama nemá smysl');
        }

        $tempFile  = tempnam(sys_get_temp_dir(), 'kopie_ostre_databaze_');
        $mysqldump = $this->nastrojeDatabaze->vytvorMysqldumpOstreDatabaze();
        $mysqldump->start($tempFile);

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

        // aby nám nezůstaly viset tabulky, views a functions z novějších SQL migrací, než má zdroj
        $this->nastrojeDatabaze->vymazVseZHlavniDatabaze($localConnection);

        (new \MySQLImport($localConnection))->load($tempFile);

        unlink($tempFile);

        (new SqlMigrace($this->systemoveNastaveni->databazoveNastaveni()))->migruj();

        $this->vyjimkovac->priZalogovaniOdeslatMailem($puvodniPriZalogovaniOdeslatMailem);
        $this->vyjimkovac->zobrazeni($puvodniZobrazeniChyb);
    }
}
