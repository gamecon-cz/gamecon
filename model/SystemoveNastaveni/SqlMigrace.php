<?php declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

use Godric\DbMigrations\DbMigrations;
use Godric\DbMigrations\DbMigrationsConfig;
use Symfony\Component\Filesystem\Filesystem;

class SqlMigrace
{
    public function migruj() {
        (new Filesystem())->mkdir(ZALOHA_DB_SLOZKA);

        (new DbMigrations(new DbMigrationsConfig([
            'connection'          => new \mysqli(
                DBM_SERV,
                DBM_USER,
                DBM_PASS,
                DBM_NAME,
                defined('DBM_PORT')
                    ? DBM_PORT
                    : 3306
            ),
            'migrationsDirectory' => SQL_MIGRACE_DIR,
            'backupsDirectory'    => ZALOHA_DB_SLOZKA,
        ])))->run(true);
    }
}
