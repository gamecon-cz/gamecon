<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Symfony\Component\Filesystem\Filesystem;

class SqlMigrace
{
    public static function vytvorZGlobals(): static
    {
        return new static(DatabazoveNastaveni::vytvorZGlobals());
    }

    public function __construct(private readonly DatabazoveNastaveni $databazoveNastaveni)
    {
    }

    public function migruj(bool $zalohuj = true, \mysqli $spojeni = null)
    {
        if ($zalohuj) {
            (new Filesystem())->mkdir(ZALOHA_DB_SLOZKA);
        }

        $this->dbMigrations($zalohuj, $spojeni)->run(true);
    }

    private function dbMigrations(bool $zalohuj, \mysqli $spojeni = null): DbMigrations
    {
        return new DbMigrations(new DbMigrationsConfig([
            'connection'          => $spojeni ?? $this->spojeni(),
            'doBackups'           => $zalohuj,
            'migrationsDirectory' => SQL_MIGRACE_DIR,
            'backupsDirectory'    => ZALOHA_DB_SLOZKA,
        ]));
    }

    private function spojeni(): \mysqli
    {
        [
            'DB_SERV'  => $dbServ,
            'DBM_USER' => $dbmUser,
            'DBM_PASS' => $dbmPass,
            'DB_NAME'  => $dbName,
            'DB_PORT'  => $dbPort,
        ] = $this->databazoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();

        return _dbConnect(
            dbServer: $dbServ,
            dbUser: $dbmUser,
            dbPass: $dbmPass,
            dbPort: $dbPort,
            dbName: $dbName,
            persistent: false,
        );
    }

    public function nejakeMigraceKeSpusteni(): bool
    {
        return $this->dbMigrations(false)->hasUnappliedMigrations();
    }
}
