<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

class KopieOstreDatabaze
{
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
        $ostraConnection = new \mysqli(
            $nastaveniOstre['DB_SERV'],
            $nastaveniOstre['DBM_USER'],
            $nastaveniOstre['DBM_PASS'],
            $nastaveniOstre['DB_NAME'],
            $nastaveniOstre['DB_PORT'] ?? 3306,
        );

        $handle = fopen('php://memory', 'r+b');

        (new \MySQLDump($ostraConnection))->write($handle);
        mysqli_close($ostraConnection);

        fflush($handle);
        rewind($handle);

        $localConnection = new \mysqli(
            DBM_SERV,
            DBM_USER,
            DBM_PASS,
            DBM_NAME,
            defined('DBM_PORT') && constant('DBM_PORT')
                ? constant('DBM_PORT')
                : 3306,
        );

        (new \MySQLImport($localConnection))->read($handle);

        fclose($handle);

        (new SqlMigrace())->migruj();
    }
}
