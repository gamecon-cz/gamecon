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
            $systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();
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

    private function vytvorDsn(string $server, string $databaze): string
    {
        return "mysql:host={$server};dbname={$databaze}";
    }

    public function vymazVseZHlavniDatabaze(\mysqli $spojeni)
    {
        $this->vymazVseZDatabaze($this->systemoveNastaveni->databazoveNastaveni()->hlavniDatabaze(), $spojeni);
    }

    public function vymazVseZDatabaze(string $databaze, \mysqli $spojeni)
    {
        if ($databaze === $this->systemoveNastaveni->databazoveNastaveni()->hlavniDatabaze()
            && $this->systemoveNastaveni->jsmeNaOstre()
        ) {
            throw new \LogicException("Nemůžeme promazávat databázi na ostré");
        }
        $this->smazTabulkyAPohledy($databaze, $spojeni);
        $this->smazNaseFunkce($databaze, $spojeni);
    }

    private function smazTabulkyAPohledy(string $databaze, \mysqli $spojeni)
    {
        mysqli_query(
            $spojeni,
            <<<SQL
                SET FOREIGN_KEY_CHECKS = 0
            SQL,
        );
        $showTablesResult = mysqli_query(
            $spojeni,
            <<<SQL
                SHOW TABLES FROM`{$databaze}`
            SQL,
        );
        while ($table = mysqli_fetch_column($showTablesResult)) {
            $showCreateTableResult = mysqli_query(
                $spojeni,
                <<<SQL
                    SHOW CREATE TABLE `$table`
                SQL,
            );
            $showCreateTable       = mysqli_fetch_assoc($showCreateTableResult);
            $type                  = !empty($showCreateTable['View'])
                ? 'VIEW'
                : 'TABLE';
            mysqli_query(
                $spojeni,
                <<<SQL
                    DROP $type `$table`
                SQL,
            );
        }
        mysqli_query(
            $spojeni,
            <<<SQL
                SET FOREIGN_KEY_CHECKS = 1
            SQL,
        );
    }

    private function smazNaseFunkce(string $databaze, \mysqli $spojeni)
    {
        $nazvyNasichFunkci = $this->nazvyNasichFunkci($databaze, $spojeni);
        foreach ($nazvyNasichFunkci as $nazevNasiFunkce) {
            mysqli_query(
                $spojeni,
                <<<SQL
                    DROP FUNCTION `$nazevNasiFunkce`
                SQL,
            );
        }
    }

    private function nazvyNasichFunkci(string $databaze, \mysqli $spojeni): array
    {
        $result                 = mysqli_query(
            $spojeni,
            <<<SQL
                SHOW FUNCTION STATUS
            SQL,
        );
        $functionsStatuses      = mysqli_fetch_all($result, MYSQLI_ASSOC);
        $localFunctionsStatuses = array_filter($functionsStatuses, static function (array $functionStatus) use ($databaze) {
            return $functionStatus['Db'] === $databaze && $functionStatus['Type'] === 'FUNCTION';
        });
        if (!$localFunctionsStatuses) {
            return [];
        }

        return array_map(static fn(array $definition) => $definition['Name'], $localFunctionsStatuses);
    }

    public function obnovDatabaziZeSouboru(string $soubor, \mysqli $spojeni)
    {
        if (!is_readable($soubor)) {
            throw new \RuntimeException("Soubor '$soubor' nelze přečíst");
        }
        if ($this->systemoveNastaveni->jsmeNaOstre()) {
            throw new \LogicException('Je zakázáno obnovovat databázi na ostré');
        }

        $db = $this->systemoveNastaveni->databazoveNastaveni()->hlavniDatabaze();

        dbQuery(q: "USE `$db`", mysqli: $spojeni);
        $this->vymazVseZHlavniDatabaze($spojeni);

        dbQuery(q: "DROP DATABASE `$db`", mysqli: $spojeni);
        dbQuery(q: "CREATE DATABASE `$db`", mysqli: $spojeni);
        dbQuery(q: "USE `$db`", mysqli: $spojeni);
        (new \MySQLImport($spojeni))->load($soubor);

        $this->migruj(false, $spojeni);
    }

    public function migruj(bool $zalohuj, \mysqli $spojeni)
    {
        (new SqlMigrace($this->systemoveNastaveni->databazoveNastaveni()))->migruj($zalohuj, $spojeni);
    }
}
