<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Ifsnop\Mysqldump\Mysqldump;

class KopieOstreDatabaze
{
    public static function createFromGlobals() {
        return new static(NastrojeDatabaze::vytvorZGlobals());
    }

    public function __construct(
        private NastrojeDatabaze $nastrojeDatabaze
    ) {
    }

    public function zkopirujOstrouDatabazi() {
        $souborNastaveniOstra = PROJECT_ROOT_DIR . '/../ostra/nastaveni/nastaveni-produkce.php';
        if (!is_readable($souborNastaveniOstra)) {
            throw new \RuntimeException('Nelze přečíst soubor s nastavením ostré ' . $souborNastaveniOstra);
        }
        $obsahNastaveniOstre = file_get_contents($souborNastaveniOstra);
        $nastaveniOstre      = [
            'DBM_USER' => true,
            'DBM_PASS' => true,
            'DB_NAME'  => true,
            'DB_SERV'  => true,
            'DB_PORT'  => false,
        ];
        foreach ($nastaveniOstre as $klic => $vyzadovana) {
            if (!preg_match("~^\s*@?define\s*\(\s*'$klic'\s*,\s*'(?<hodnota>[^']+)'\s*\)~m", $obsahNastaveniOstre, $matches)) {
                if ($vyzadovana) {
                    throw new \RuntimeException("Nelze z $souborNastaveniOstra přečíst hodnotu $klic");
                }
            }
            $nastaveniOstre[$klic] = $matches['hodnota'] ?? null;
        }
        if ($nastaveniOstre['DB_SERV'] === DB_SERV && $nastaveniOstre['DB_NAME'] === DB_NAME) {
            throw new \RuntimeException('Kopírovat sebe sama nemá smysl');
        }

        $tempFile  = tempnam(sys_get_temp_dir(), 'kopie_ostre_databaze_');
        $mysqldump = $this->nastrojeDatabaze->vytvorMysqldumpProHlavniDatabazi();
        $mysqldump->start($tempFile);

        $localConnection = new \mysqli(
            DBM_SERV,
            DBM_USER,
            DBM_PASS,
            DBM_NAME,
            defined('DBM_PORT') && constant('DBM_PORT')
                ? constant('DBM_PORT')
                : 3306,
        );

        // aby nám nezůstaly viset tabulky, views a functions z novějších SQL migrací, než má zdroj
        $this->nastrojeDatabaze->vymazVseZHlavniDatabaze();

        (new \MySQLImport($localConnection))->load($tempFile);

        unlink($tempFile);

        (new SqlMigrace())->migruj();
    }
}
