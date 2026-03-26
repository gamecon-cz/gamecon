<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Ifsnop\Mysqldump\Mysqldump;

class NastrojeDatabaze
{
    public static function vytvorZGlobals()
    {
        global $systemoveNastaveni;
        if (!$systemoveNastaveni) {
            $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        }
        return new self($systemoveNastaveni);
    }

    public function __construct(
        private SystemoveNastaveni $systemoveNastaveni,
    )
    {
    }

    public function vytvorMysqldumpOstreDatabaze(array $mysqldumpSettings = ['skip-definer' => true]): Mysqldump
    {
        $nastaveniOstre = $this->systemoveNastaveni->prihlasovaciUdajeOstreDatabaze();

        return $this->vytvorMysqldump(
            $nastaveniOstre['DB_SERV'],
            $nastaveniOstre['DBM_USER'], // běžný uživatel nemá právo SHOW VIEW
            $nastaveniOstre['DBM_PASS'],
            $nastaveniOstre['DB_NAME'],
            $mysqldumpSettings,
        );
    }

    /**
     * Obecný mysqldump pro libovolnou databázi dle předaných přihlašovacích údajů.
     * Očekává klíče: DB_SERV, DBM_USER, DBM_PASS, DB_NAME
     */
    public function vytvorMysqldumpDatabaze(
        array $nastaveniDb,
        array $mysqldumpSettings = ['skip-definer' => true],
    ): Mysqldump
    {
        return $this->vytvorMysqldump(
            $nastaveniDb['DB_SERV'],
            $nastaveniDb['DBM_USER'], // dbm účet kvůli SHOW VIEW
            $nastaveniDb['DBM_PASS'],
            $nastaveniDb['DB_NAME'],
            $mysqldumpSettings,
        );
    }

    public function vytvorMysqldumpHlavniDatabaze(array $mysqldumpSettings = ['skip-definer' => true]): Mysqldump
    {
        return $this->vytvorMysqldump(
            $this->systemoveNastaveni->databazoveNastaveni()->serverHlavniDatabaze(),
            DBM_USER, // běžný uživatel nemá právo SHOW VIEW
            DBM_PASS,
            $this->systemoveNastaveni->databazoveNastaveni()->hlavniDatabaze(),
            $mysqldumpSettings,
        );
    }

    public function vytvorMysqldumpAnonymniDatabaze(array $mysqldumpSettings = ['skip-definer' => true]): Mysqldump
    {
        return $this->vytvorMysqldump(
            $this->systemoveNastaveni->databazoveNastaveni()->serverAnonymizovaneDatabase(),
            DB_ANONYM_USER,
            DB_ANONYM_PASS,
            $this->systemoveNastaveni->databazoveNastaveni()->anonymizovanaDatabaze(),
            $mysqldumpSettings,
        );
    }

    public function vytvorMysqldump(
        string $dbServer,
        string $dbUser,
        string $dbPassword,
        string $dbName,
        array  $mysqldumpSettings,
    ): Mysqldump
    {
        return new Mysqldump(
            $this->vytvorDsn($dbServer, $dbName),
            $dbUser,
            $dbPassword,
            $mysqldumpSettings
        );
    }

    // MariaDb občas vyblije syntaxi nezachycenou regexi MySQLDumpu a tím se do výsledného dumpu dostanou DEFINER klauze.
    // Zavoláním této funkce nad souborem s SQLdumpem se tyto DEFINER klauze odstraní nahrubo
    public static function removeDefiners(string $filename): void
    {
        $file = file_get_contents($filename);
        $file = preg_replace('#DEFINER=`(?:[^`]|``)*`@`(?:[^`]|``)*`#', '', $file);
        file_put_contents($filename, $file);
    }

    private function vytvorDsn(string $server, string $databaze): string
    {
        return "mysql:host={$server};dbname={$databaze}";
    }

    public function vymazVseZHlavniDatabaze(\PDO $spojeni)
    {
        $this->vymazVseZDatabaze($this->systemoveNastaveni->databazoveNastaveni()->hlavniDatabaze(), $spojeni);
    }

    public function vymazVseZDatabaze(string $databaze, \PDO $spojeni)
    {
        if ($databaze === $this->systemoveNastaveni->databazoveNastaveni()->hlavniDatabaze()
            && $this->systemoveNastaveni->jsmeNaOstre()
        ) {
            throw new \LogicException("Nemůžeme promazávat databázi na ostré");
        }
        $this->smazTabulkyAPohledy($databaze, $spojeni);
        $this->smazNaseFunkce($databaze, $spojeni);
    }

    private function smazTabulkyAPohledy(string $databaze, \PDO $spojeni)
    {
        $spojeni->query(<<<SQL
                SET FOREIGN_KEY_CHECKS = 0
            SQL,
        );
        $showTablesResult = $spojeni->query(<<<SQL
                SHOW TABLES FROM`{$databaze}`
            SQL,
        );
        while ($table = $showTablesResult->fetchColumn()) {
            $showCreateTableResult = $spojeni->query(<<<SQL
                    SHOW CREATE TABLE `$table`
                SQL,
            );
            $showCreateTable       = $showCreateTableResult->fetch(\PDO::FETCH_ASSOC);
            $type                  = !empty($showCreateTable['View'])
                ? 'VIEW'
                : 'TABLE';
            $spojeni->query(<<<SQL
                    DROP $type `$table`
                SQL,
            );
        }
        $spojeni->query(<<<SQL
                SET FOREIGN_KEY_CHECKS = 1
            SQL,
        );
    }

    private function smazNaseFunkce(string $databaze, \PDO $spojeni)
    {
        $nazvyNasichFunkci = $this->nazvyNasichFunkci($databaze, $spojeni);
        foreach ($nazvyNasichFunkci as $nazevNasiFunkce) {
            $spojeni->query(<<<SQL
                    DROP FUNCTION `$nazevNasiFunkce`
                SQL,
            );
        }
    }

    private function nazvyNasichFunkci(string $databaze, \PDO $spojeni): array
    {
        $result                 = $spojeni->query(<<<SQL
                SHOW FUNCTION STATUS
            SQL,
        );
        $functionsStatuses      = $result->fetchAll(\PDO::FETCH_ASSOC);
        $localFunctionsStatuses = array_filter($functionsStatuses, static function (array $functionStatus) use ($databaze) {
            return $functionStatus['Db'] === $databaze && $functionStatus['Type'] === 'FUNCTION';
        });
        if (!$localFunctionsStatuses) {
            return [];
        }

        return array_map(static fn(array $definition) => $definition['Name'], $localFunctionsStatuses);
    }
}
