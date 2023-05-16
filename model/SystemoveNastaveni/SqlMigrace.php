<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Symfony\Component\Filesystem\Filesystem;

class SqlMigrace
{
    public static function vytvorZGlobals(): static
    {
        return new static(SystemoveNastaveni::vytvorZGlobals());
    }

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function migruj(bool $zalohuj = true)
    {
        if ($zalohuj) {
            (new Filesystem())->mkdir(ZALOHA_DB_SLOZKA);
        }

        [
            'DB_SERV'  => $dbServ,
            'DBM_USER' => $dbmUser,
            'DBM_PASS' => $dbmPass,
            'DB_NAME'  => $dbName,
            'DB_PORT'  => $dbPort,
        ] = $this->systemoveNastaveni->prihlasovaciUdajeSoucasneDatabaze();

        (new DbMigrations(new DbMigrationsConfig([
            'connection'          => _dbConnect(
                dbServer: $dbServ,
                dbUser: $dbmUser,
                dbPass: $dbmPass,
                dbPort: $dbPort,
                dbName: $dbName,
                persistent: false,
            ),
            'doBackups'           => $zalohuj,
            'migrationsDirectory' => SQL_MIGRACE_DIR,
            'backupsDirectory'    => ZALOHA_DB_SLOZKA,
        ])))->run(true);
    }
}
