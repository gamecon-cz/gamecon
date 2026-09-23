<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use Gamecon\SystemoveNastaveni\NastrojeDatabaze;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class ZalohaDatabazeCollationTest extends AbstractTestDb
{
    // mysqldump čte vlastním spojením, takže data musí být commitnutá
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    public function testZalohaZachova4bajtoveZnaky()
    {
        dbQuery("INSERT INTO _vars (name, value) VALUES ('test-zaloha-emoji', $0)", ['🎲 kostka']);
        $soubor = tempnam(sys_get_temp_dir(), 'gamecon-test-zaloha-');
        try {
            (new NastrojeDatabaze(SystemoveNastaveni::zGlobals()))
                ->vytvorMysqldumpHlavniDatabaze([
                    'include-tables' => ['_vars'],
                ])
                ->start($soubor);

            self::assertStringContainsString('🎲 kostka', (string) file_get_contents($soubor));
        } finally {
            dbQuery("DELETE FROM _vars WHERE name = 'test-zaloha-emoji'");
            unlink($soubor);
        }
    }
}
