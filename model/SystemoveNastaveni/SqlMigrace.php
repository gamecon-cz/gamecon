<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Symfony\Component\Filesystem\Filesystem;

class SqlMigrace
{
    public static function vytvorZGlobals(): static
    {
        return new static(SystemoveNastaveni::zGlobals());
    }

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function migruj(bool $zalohuj = true)
    {
        if ($zalohuj) {
            (new Filesystem())->mkdir(ZALOHA_DB_SLOZKA);
        }

        $this->dbMigrations($zalohuj)->run(true);
    }

    private function dbMigrations(bool $zalohuj): DbMigrations
    {
        [
            'DB_SERV'  => $dbServ,
            'DBM_USER' => $dbmUser,
            'DBM_PASS' => $dbmPass,
            'DB_NAME'  => $dbName,
            'DB_PORT'  => $dbPort,
        ] = $this->systemoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();

        return new DbMigrations(new DbMigrationsConfig(
            connection: _dbConnect(
                dbServer: $dbServ,
                dbUser: $dbmUser,
                dbPass: $dbmPass,
                dbPort: $dbPort,
                dbName: $dbName,
                persistent: false,
            ),
            migrationsDirectory: SQL_MIGRACE_DIR,
            doBackups: $zalohuj,
            backupsDirectory: ZALOHA_DB_SLOZKA,
        ));
    }

    public function nejakeMigraceKeSpusteni(): bool
    {
        return $this->dbMigrations(false)->hasUnappliedMigrations();
    }
}
