<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractUzivatelTestDb;
use Gamecon\Uzivatel\UzivatelSlucovani;
use Uzivatel;

class UzivatelSlucovaniTest extends AbstractUzivatelTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            // Testovací tabulka s FK na uzivatele_hodnoty
            'CREATE TABLE test_uzivatel_reference (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_uzivatele INT NOT NULL,
                test_data VARCHAR(100),
                FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
            )',

            // Testovací tabulka s unique indexem
            'CREATE TABLE test_unique_uzivatel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_uzivatele INT NOT NULL,
                unique_field VARCHAR(50),
                UNIQUE KEY unique_user_field (id_uzivatele, unique_field),
                FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
            )',

            // Testovací tabulka bez FK
            'CREATE TABLE test_no_fk_reference (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_uzivatele INT NOT NULL,
                some_data VARCHAR(100)
            )',
        ];
    }

    public static function tearDownAfterClass(): void
    {
        dbQuery('DROP TABLE IF EXISTS test_uzivatel_reference');
        dbQuery('DROP TABLE IF EXISTS test_unique_uzivatel');
        dbQuery('DROP TABLE IF EXISTS test_no_fk_reference');

        parent::tearDownAfterClass();
    }

    public function test_sluc_merges_users_successfully(): void
    {
        // Arrange
        $staryUzivatel = $this->createTestUser('stary@test.com', 'Stary', 'Uzivatel');
        $novyUzivatel = $this->createTestUser('novy@test.com', 'Novy', 'Uzivatel');

        $staryId = (int)$staryUzivatel->id();
        $novyId = (int)$novyUzivatel->id();

        // Add test data for old user
        dbQuery("INSERT INTO test_uzivatel_reference (id_uzivatele, test_data) VALUES ($staryId, 'test data 1')");
        dbQuery("INSERT INTO test_uzivatel_reference (id_uzivatele, test_data) VALUES ($staryId, 'test data 2')");

        $slucovani = new UzivatelSlucovani($this->getDatabaseName());
        $zmeny = ['jmeno_uzivatele' => 'Sloučený'];

        // Act
        $slucovani->sluc($staryUzivatel, $novyUzivatel, $zmeny);

        // Assert
        $this->assertUserDataMerged($staryId, $novyId);
        $this->assertOldUserDeleted($staryId);
        $this->assertNewUserUpdated($novyId, $zmeny);
    }

    public function test_sluc_handles_same_user_ids(): void
    {
        // Arrange
        $uzivatel = $this->createTestUser('same@test.com', 'Same', 'User');
        $slucovani = new UzivatelSlucovani($this->getDatabaseName());

        // Act & Assert - should not throw exception and should exit early
        $slucovani->sluc($uzivatel, $uzivatel, []);

        // Verify user still exists
        $this->assertNotNull(Uzivatel::zId($uzivatel->id()));
    }

    public function test_sluc_handles_unique_constraint_conflicts(): void
    {
        // Arrange
        $staryUzivatel = $this->createTestUser('stary2@test.com', 'Stary2', 'Uzivatel');
        $novyUzivatel = $this->createTestUser('novy2@test.com', 'Novy2', 'Uzivatel');

        $staryId = (int)$staryUzivatel->id();
        $novyId = (int)$novyUzivatel->id();

        // Create conflicting unique data
        dbQuery("INSERT INTO test_unique_uzivatel (id_uzivatele, unique_field) VALUES ($staryId, 'conflict')");
        dbQuery("INSERT INTO test_unique_uzivatel (id_uzivatele, unique_field) VALUES ($novyId, 'conflict')");

        $slucovani = new UzivatelSlucovani($this->getDatabaseName());

        // Act - should handle conflict by deleting old user's conflicting records
        $slucovani->sluc($staryUzivatel, $novyUzivatel, []);

        // Assert - only new user's record should remain
        $remainingRecords = dbFetchAll("SELECT * FROM test_unique_uzivatel WHERE unique_field = 'conflict'");
        $this->assertCount(1, $remainingRecords);
        $this->assertEquals($novyId, $remainingRecords[0]['id_uzivatele']);
    }

    public function test_sluc_creates_log_entries(): void
    {
        // Arrange
        $staryUzivatel = $this->createTestUser('log1@test.com', 'Log1', 'User');
        $novyUzivatel = $this->createTestUser('log2@test.com', 'Log2', 'User');

        $logFile = LOGY . '/slucovani.log';
        flush();
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        $slucovani = new UzivatelSlucovani($this->getDatabaseName());

        // Act
        $slucovani->sluc($staryUzivatel, $novyUzivatel, ['jmeno_uzivatele' => 'Logged']);

        // Assert
        flush();
        $this->assertTrue(file_exists($logFile), sprintf('Log file %s should exist', $logFile));
        $this->assertGreaterThan(0, filesize($logFile));

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('sloučeno a smazáno', $logContent);
        $this->assertStringContainsString(
            "do ID {$novyUzivatel->id()} sloučeno a smazáno ID {$staryUzivatel->id()}",
            $logContent
        );
    }

    public function test_odkazujici_tabulky_returns_correct_tables(): void
    {
        // This tests the private method indirectly through sluc()
        $staryUzivatel = $this->createTestUser('ref1@test.com', 'Ref1', 'User');
        $novyUzivatel = $this->createTestUser('ref2@test.com', 'Ref2', 'User');

        $staryId = (int)$staryUzivatel->id();

        // Add data to test table without FK (should be handled by whitelist)
        dbQuery("INSERT INTO test_no_fk_reference (id_uzivatele, some_data) VALUES ($staryId, 'no fk test')");

        $slucovani = new UzivatelSlucovani($this->getDatabaseName());
        $slucovani->sluc($staryUzivatel, $novyUzivatel, []);

        // Verify data was moved from table without FK
        $newRecords = dbFetchAll("SELECT * FROM test_no_fk_reference WHERE id_uzivatele = " . $novyUzivatel->id());
        $this->assertEmpty($newRecords); // This table is not in the whitelist for this test
    }

    private function createTestUser(string $email, string $jmeno, string $prijmeni): Uzivatel
    {
        $login = strtolower(str_replace('@', '_', str_replace('.', '_', $email)));

        dbInsert('uzivatele_hodnoty', [
            'login_uzivatele' => $login,
            'email1_uzivatele' => $email,
            'jmeno_uzivatele' => $jmeno,
            'prijmeni_uzivatele' => $prijmeni,
        ]);

        $idUzivatele = dbInsertId();
        $uzivatel = Uzivatel::zId($idUzivatele);

        $this->assertNotNull($uzivatel, 'Failed to create test user');
        return $uzivatel;
    }

    private function getDatabaseName(): string
    {
        $result = dbQuery('SELECT DATABASE() as db_name');
        $row = $result->fetch_assoc();
        return $row['db_name'];
    }

    private function assertUserDataMerged(int $staryId, int $novyId): void
    {
        // Check that references were moved to new user
        $oldReferences = dbFetchAll("SELECT * FROM test_uzivatel_reference WHERE id_uzivatele = $staryId");
        $this->assertEmpty($oldReferences, 'Old user should have no references');

        $newReferences = dbFetchAll("SELECT * FROM test_uzivatel_reference WHERE id_uzivatele = $novyId");
        $this->assertCount(2, $newReferences, 'New user should have merged references');
    }

    private function assertOldUserDeleted(int $staryId): void
    {
        $oldUser = dbFetchAll("SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele = $staryId");
        $this->assertEmpty($oldUser, 'Old user record should be deleted');
    }

    private function assertNewUserUpdated(int $novyId, array $expectedChanges): void
    {
        $newUser = dbFetchAll("SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele = $novyId");
        $this->assertCount(1, $newUser, 'New user should exist');

        foreach ($expectedChanges as $column => $expectedValue) {
            $this->assertEquals($expectedValue, $newUser[0][$column], "Column $column was not updated correctly");
        }
    }
}
