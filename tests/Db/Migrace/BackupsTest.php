<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db\Migrace;

use Godric\DbMigrations\Backups;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;

class BackupsTest extends KernelTestCase
{
    private \mysqli $connection;
    private string $backupDir;

    protected function setUp(): void
    {
        static::bootKernel();

        $this->connection = dbConnectTemporary();
        // Aby dump nebyl prázdný (prázdná DB se přeskakuje jako workaround na bug
        // MySQLDump) — vytvoříme dočasnou tabulku s jedním řádkem.
        $this->connection->query('CREATE TEMPORARY TABLE tmp_backups_test (id INT PRIMARY KEY)');
        $this->connection->query('INSERT INTO tmp_backups_test (id) VALUES (1)');

        $this->backupDir = LOGY . '/backups-test-' . uniqid('', false);
        (new Filesystem())->mkdir($this->backupDir, 0775);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->backupDir);
    }

    public function testNamedBackupCreatesGzipFileWithMode0600(): void
    {
        $backups = new Backups($this->connection, $this->backupDir);

        $backups->backup('pre-migration-endless');

        $file = $this->backupDir . '/pre-migration-endless.sql.gz';
        self::assertFileExists($file, 'Backup file should be created.');
        self::assertSame(
            '0600',
            substr(sprintf('%o', fileperms($file)), -4),
            'Backup file must not be readable by other accounts on the host.',
        );
    }
}
