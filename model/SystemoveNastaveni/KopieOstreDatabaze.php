<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

class KopieOstreDatabaze
{
    public function zkopirovatOstrouDatabazi() {
        $souborNastaveniOstra = PROJECT_ROOT_DIR . '/../ostra/nastaveni/nastaveni-produkce.php';
        if (!is_readable($souborNastaveniOstra)) {
            throw new \RuntimeException('Nelze přečíst soubor s nastavením ostré ' . $souborNastaveniOstra);
        }
        $obsahNastaveniOstre = file_get_contents($souborNastaveniOstra);
        $nastaveniOstre = [
            'DBM_USER' => true,
            'DBM_PASS' => true,
            'DBM_NAME' => true,
            'DBM_SERV' => true,
            'DBM_PORT' => false,
        ];
        foreach ($nastaveniOstre as $klic => $vyzadovana) {
            if (!preg_match("~^\s*@?define\s*\(\s*'$klic'\s*,\s*'(?<hodnota>[^']+)'\s*\)~m", $obsahNastaveniOstre, $matches)) {
                if ($vyzadovana) {
                    throw new \RuntimeException("Nelze z $souborNastaveniOstra přečíst hodnotu $klic");
                }
            }
            $nastaveniOstre[$klic] = $matches['hodnota'] ?? null;
        }
        if ($nastaveniOstre['DBM_SERV'] === DBM_SERV && $nastaveniOstre['DBM_NAME'] === DBM_NAME) {
            throw new \RuntimeException('Kopírovat sebe sama nemá smysl');
        }
        $ostraConnection = new \mysqli(
            $nastaveniOstre['DBM_SERV'],
            $nastaveniOstre['DBM_USER'],
            $nastaveniOstre['DBM_PASS'],
            $nastaveniOstre['DBM_NAME'],
            $nastaveniOstre['DBM_PORT'] ?? 3306,
        );
        $dump = new \MySQLDump($ostraConnection);
        $handle = fopen('php://memory', 'r+b');
        $dump->write($handle);
        mysqli_close($ostraConnection);
        fflush($handle);
        rewind($handle);

        $localConnection = new \mysqli(
            DBM_SERV,
            DBM_USER,
            DBM_PASS,
            DBM_NAME,
            defined('DBM_PORT') && DBM_PORT
                ? DBM_PORT
                : 3306,
        );
        $result = $this->executeQuery(
            <<<SQL
SHOW TABLES
SQL,
            $localConnection,
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
        $command = '';
        while (($row = fgets($handle)) !== false) {
            $command .= $row;
            if (substr(trim($row), -1, 1) === ';') {
                $this->executeQuery($command, $localConnection);
                $command = '';
            }
        }
    }

    /**
     * @param string $query
     * @param \mysqli $connection
     * @return \mysqli_result|bool
     */
    private function executeQuery(string $query, $connection) {
        $result = mysqli_query($connection, $query);
        if ($result === false) {
            throwDbException($connection);
        }
        return $result;
    }
}
