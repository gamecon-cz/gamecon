<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Ifsnop\Mysqldump\Mysqldump;

class NastrojeDatabaze
{
    public static function vytvorZGlobals() {
        global $systemoveNastaveni;
        if (!$systemoveNastaveni) {
            $systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();
        }
        return new self($systemoveNastaveni);
    }

    public function __construct(
        private SystemoveNastaveni $systemoveNastaveni
    ) {
    }

    public function vytvorMysqldumpProHlavniDatabazi(array $mysqldumpSettings = []): Mysqldump {
        return $this->vytvorMysqldump(
            $this->systemoveNastaveni->databazovyServer(),
            DB_USER,
            DB_PASS,
            $this->systemoveNastaveni->hlavniDatabaze(),
            $mysqldumpSettings,
        );
    }

    public function vytvorMysqldump(
        string $dbServer,
        string $dbUser,
        string $dbPassword,
        string $dbName,
        array $mysqldumpSettings
    ): Mysqldump {
        return new Mysqldump(
            $this->vytvorDsn($dbServer, $dbName),
            $dbUser,
            $dbPassword,
            $mysqldumpSettings
        );
    }

    private function vytvorDsn(string $server, string $databaze): string {
        return "mysql:host={$server};dbname={$databaze}";
    }

    public function vymazVseZHlavniDatabaze() {
        $this->vymazVseZDatabaze($this->systemoveNastaveni->hlavniDatabaze(), dbConnect());
    }

    public function vymazVseZDatabaze(string $databaze, \mysqli $spojeni) {
        if ($databaze === $this->systemoveNastaveni->hlavniDatabaze()
            && $this->systemoveNastaveni->jsmeNaOstre()
        ) {
            throw new \LogicException("Nemůžeme promazávat databázi na ostré");
        }
        $this->smazTabulkyAPohledy($databaze, $spojeni);
        $this->smazNaseFunkce($databaze, $spojeni);
    }

    private function smazTabulkyAPohledy(string $databaze, \mysqli $spojeni) {
        mysqli_query(
            $spojeni,
            <<<SQL
                SET FOREIGN_KEY_CHECKS = 0
            SQL
        );
        $showTablesResult = mysqli_query(
            $spojeni,
            <<<SQL
                SHOW TABLES FROM`{$databaze}`
            SQL
        );
        while ($table = mysqli_fetch_column($showTablesResult)) {
            $showCreateTableResult = mysqli_query(
                $spojeni,
                <<<SQL
                    SHOW CREATE TABLE `$table`
                SQL
            );
            $showCreateTable       = mysqli_fetch_assoc($showCreateTableResult);
            $type                  = !empty($showCreateTable['View'])
                ? 'VIEW'
                : 'TABLE';
            mysqli_query(
                $spojeni,
                <<<SQL
                    DROP $type `$table`
                SQL
            );
        }
        mysqli_query(
            $spojeni,
            <<<SQL
                SET FOREIGN_KEY_CHECKS = 1
            SQL
        );
    }

    private function smazNaseFunkce(string $databaze, \mysqli $spojeni) {
        $nazvyNasichFunkci = $this->nazvyNasichFunkci($databaze, $spojeni);
        foreach ($nazvyNasichFunkci as $nazevNasiFunkce) {
            mysqli_query(
                $spojeni,
                <<<SQL
                    DROP FUNCTION `$nazevNasiFunkce`
                SQL
            );
        }
    }

    private function nazvyNasichFunkci(string $databaze, \mysqli $spojeni): array {
        $result                 = mysqli_query(
            $spojeni,
            <<<SQL
                SHOW FUNCTION STATUS
            SQL
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
}
