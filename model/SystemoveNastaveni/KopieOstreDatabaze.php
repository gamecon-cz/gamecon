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
        $dump            = new \MySQLDump($ostraConnection);
        $tempFile        = tempnam(sys_get_temp_dir(), 'gc_kopie_ostre_databaze');
        $dump->save($tempFile);
        mysqli_close($ostraConnection);

        $localConnection = new \mysqli(
            DB_SERV,
            DBM_USER,
            DBM_PASS,
            DB_NAME,
            defined('DBM_PORT') && constant('DB_PORT')
                ? constant('DB_PORT')
                : 3306,
        );
        $result          = $this->executeQuery(
            <<<SQL
SHOW TABLES
SQL,
            $localConnection,
        );
        $this->executeQuery(
            <<<SQL
SET FOREIGN_KEY_CHECKS = 0
SQL,
            $localConnection
        );
        $localTables = mysqli_fetch_all($result);
        foreach ($localTables as $localTableWrapped) {
            $localTable = reset($localTableWrapped);
            $this->executeQuery(
                <<<SQL
DROP TABLE IF EXISTS `$localTable`
SQL,
                $localConnection
            );
        }
        $this->executeQuery(
            <<<SQL
SET FOREIGN_KEY_CHECKS = 1
SQL,
            $localConnection
        );
        $this->executeQuery(file_get_contents($tempFile), $localConnection);
        unlink($tempFile);
        (new SqlMigrace())->migruj();
    }

    /**
     * @param string $query
     * @param \mysqli $connection
     * @return \mysqli_result|bool
     * @throws \DbDuplicateEntryException
     * @throws \DbException
     */
    private function executeQuery(string $query, \mysqli $connection): bool|\mysqli_result {
        return dbMysqliQuery($query, $connection);
    }
}
