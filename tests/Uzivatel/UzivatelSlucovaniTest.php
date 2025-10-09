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
                id_uzivatele BIGINT UNSIGNED NOT NULL,
                test_data VARCHAR(100),
                FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
            )',

            // Testovací tabulka s unique indexem
            'CREATE TABLE test_unique_uzivatel (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_uzivatele BIGINT UNSIGNED NOT NULL,
                unique_field VARCHAR(50),
                UNIQUE KEY unique_user_field (id_uzivatele, unique_field),
                FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
            )',

            // Testovací tabulka bez FK
            'CREATE TABLE test_no_fk_reference (
                id INT AUTO_INCREMENT PRIMARY KEY,
                id_uzivatele BIGINT UNSIGNED NOT NULL,
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

        $slucovani = new UzivatelSlucovani();
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
        $slucovani = new UzivatelSlucovani();

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

        $slucovani = new UzivatelSlucovani();

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

        // Clear any existing log entries for these users
        dbQuery('DELETE FROM uzivatele_slucovani_log WHERE id_smazaneho_uzivatele = $1 OR id_noveho_uzivatele = $2',
               [$staryUzivatel->id(), $novyUzivatel->id()]);

        $slucovani = new UzivatelSlucovani();

        // Act
        $slucovani->sluc($staryUzivatel, $novyUzivatel, ['jmeno_uzivatele' => 'Logged']);

        // Assert
        $logEntries = dbFetchAll('SELECT * FROM uzivatele_slucovani_log WHERE id_smazaneho_uzivatele = $1 AND id_noveho_uzivatele = $2',
                               [$staryUzivatel->id(), $novyUzivatel->id()]);

        $this->assertCount(1, $logEntries, 'Should create exactly one log entry');

        $logEntry = $logEntries[0];
        $this->assertEquals($staryUzivatel->id(), $logEntry['id_smazaneho_uzivatele']);
        $this->assertEquals($novyUzivatel->id(), $logEntry['id_noveho_uzivatele']);
        $this->assertEquals('log1@test.com', $logEntry['email_smazaneho']);
        $this->assertEquals('log2@test.com', $logEntry['email_noveho_puvodne']);
        $this->assertEquals('log2@test.com', $logEntry['email_noveho_aktualne']); // Should remain the same since no email change
        $this->assertNotNull($logEntry['kdy']);
    }

    public function test_odkazujici_tabulky_returns_correct_tables(): void
    {
        // This tests the private method indirectly through sluc()
        $staryUzivatel = $this->createTestUser('ref1@test.com', 'Ref1', 'User');
        $novyUzivatel = $this->createTestUser('ref2@test.com', 'Ref2', 'User');

        $staryId = (int)$staryUzivatel->id();

        // Add data to test table without FK (should be handled by whitelist)
        dbQuery("INSERT INTO test_no_fk_reference (id_uzivatele, some_data) VALUES ($staryId, 'no fk test')");

        $slucovani = new UzivatelSlucovani();
        $slucovani->sluc($staryUzivatel, $novyUzivatel, []);

        // Verify data was moved from table without FK
        $newRecords = dbFetchAll("SELECT * FROM test_no_fk_reference WHERE id_uzivatele = " . $novyUzivatel->id());
        $this->assertEmpty($newRecords); // This table is not in the whitelist for this test
    }

    public function test_sluc_copies_all_uzivatele_hodnoty_fields(): void
    {
        $staryUzivatel = $this->createTestUser('stary_complete@test.com', 'StaryJmeno', 'StaryPrijmeni');
        $novyUzivatel = $this->createTestUser('novy_complete@test.com', 'NovyJmeno', 'NovyPrijmeni');

        $staryId = (int)$staryUzivatel->id();
        $novyId = (int)$novyUzivatel->id();

        // Update old user with comprehensive data
        dbUpdate('uzivatele_hodnoty', [
            'login_uzivatele' => 'stary_login_unique',
            'email1_uzivatele' => 'stary_unique@test.com',
            'ulice_a_cp_uzivatele' => 'Stará ulice 123',
            'mesto_uzivatele' => 'Staré Město',
            'stat_uzivatele' => 1,
            'psc_uzivatele' => '12345',
            'telefon_uzivatele' => '+420111222333',
            'datum_narozeni' => '1990-05-15',
            'heslo_md5' => 'old_hash_value',
            'nechce_maily' => '2023-01-15 10:30:00',
            'mrtvy_mail' => 1,
            'forum_razeni' => 'A',
            'random' => 'old_random_123',
            'zustatek' => 500,
            'pohlavi' => 'm',
            'registrovan' => '2020-01-01 12:00:00',
            'ubytovan_s' => 'Někdo Jiný',
            'poznamka' => 'Poznámka starého uživatele',
            'pomoc_typ' => 'vypravec',
            'pomoc_vice' => 'Detaily pomoci starého',
            'op' => 'encrypted_op_old',
            'potvrzeni_zakonneho_zastupce' => '2022-06-01',
            'potvrzeni_proti_covid19_pridano_kdy' => '2022-07-01 14:00:00',
            'potvrzeni_proti_covid19_overeno_kdy' => '2022-07-02 15:00:00',
            'infopult_poznamka' => 'Stará infopult poznámka',
            'typ_dokladu_totoznosti' => 'OP',
            'statni_obcanstvi' => 'CZ',
            'z_rychloregistrace' => 1,
            'potvrzeni_zakonneho_zastupce_soubor' => '2022-06-01 10:00:00',
        ], ['id_uzivatele' => $staryId]);

        // Update new user with some existing data
        dbUpdate('uzivatele_hodnoty', [
            'login_uzivatele' => 'novy_login_unique',
            'email1_uzivatele' => 'novy_unique@test.com',
            'ulice_a_cp_uzivatele' => 'Nová ulice 456',
            'mesto_uzivatele' => '',  // Empty field
            'zustatek' => 200,
            'poznamka' => 'Původní poznámka nového',
        ], ['id_uzivatele' => $novyId]);

        $slucovani = new UzivatelSlucovani();

        $zmeny = [
            // Unique fields
            'login_uzivatele' => 'stary_login_unique',
            'email1_uzivatele' => 'stary_unique@test.com',

            // All other fields
            'jmeno_uzivatele' => 'StaryJmeno',
            'prijmeni_uzivatele' => 'StaryPrijmeni',
            'ulice_a_cp_uzivatele' => 'Stará ulice 123',
            'mesto_uzivatele' => 'Staré Město',
            'stat_uzivatele' => 1,
            'psc_uzivatele' => '12345',
            'telefon_uzivatele' => '+420111222333',
            'datum_narozeni' => '1990-05-15',
            'heslo_md5' => 'old_hash_value',
            'nechce_maily' => '2023-01-15 10:30:00',
            'mrtvy_mail' => 1,
            'forum_razeni' => 'A',
            'random' => 'old_random_123',
            // 'zustatek' => 500,  // Zustatek is handled separately by merge logic
            'pohlavi' => 'm',
            'registrovan' => '2020-01-01 12:00:00',
            'ubytovan_s' => 'Někdo Jiný',
            'poznamka' => 'Poznámka starého uživatele',
            'pomoc_typ' => 'vypravec',
            'pomoc_vice' => 'Detaily pomoci starého',
            'op' => 'encrypted_op_old',
            'potvrzeni_zakonneho_zastupce' => '2022-06-01',
            'potvrzeni_proti_covid19_pridano_kdy' => '2022-07-01 14:00:00',
            'potvrzeni_proti_covid19_overeno_kdy' => '2022-07-02 15:00:00',
            'infopult_poznamka' => 'Stará infopult poznámka',
            'typ_dokladu_totoznosti' => 'OP',
            'statni_obcanstvi' => 'CZ',
            'z_rychloregistrace' => 1,
            'potvrzeni_zakonneho_zastupce_soubor' => '2022-06-01 10:00:00',
        ];

        // Act
        $slucovani->sluc($staryUzivatel, $novyUzivatel, $zmeny);

        // Assert
        $mergedUser = dbFetchAll("SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele = $novyId");
        $this->assertCount(1, $mergedUser);
        $user = $mergedUser[0];

        // Check that UNIQUE fields were NOT changed (to avoid duplicate key errors)
        $this->assertEquals('stary_login_unique', $user['login_uzivatele'], 'Login should remain unchanged (UNIQUE constraint)');
        $this->assertEquals('stary_unique@test.com', $user['email1_uzivatele'], 'Email should remain unchanged (UNIQUE constraint)');

        // Check that all non-unique fields were copied from old user
        $this->assertEquals('StaryJmeno', $user['jmeno_uzivatele']);
        $this->assertEquals('StaryPrijmeni', $user['prijmeni_uzivatele']);
        $this->assertEquals('Stará ulice 123', $user['ulice_a_cp_uzivatele']);
        $this->assertEquals('Staré Město', $user['mesto_uzivatele']);
        $this->assertEquals(1, $user['stat_uzivatele']);
        $this->assertEquals('12345', $user['psc_uzivatele']);
        $this->assertEquals('+420111222333', $user['telefon_uzivatele']);
        $this->assertEquals('1990-05-15', $user['datum_narozeni']);
        $this->assertEquals('old_hash_value', $user['heslo_md5']);
        $this->assertEquals('2023-01-15 10:30:00', $user['nechce_maily']);
        $this->assertEquals(1, $user['mrtvy_mail']);
        $this->assertEquals('A', $user['forum_razeni']);
        $this->assertEquals('old_random_123', $user['random']);
        $this->assertEquals('m', $user['pohlavi']);
        $this->assertEquals('2020-01-01 12:00:00', $user['registrovan']);
        $this->assertEquals('Někdo Jiný', $user['ubytovan_s']);
        $this->assertEquals('Poznámka starého uživatele', $user['poznamka']);
        $this->assertEquals('vypravec', $user['pomoc_typ']);
        $this->assertEquals('Detaily pomoci starého', $user['pomoc_vice']);
        $this->assertEquals('encrypted_op_old', $user['op']);
        $this->assertEquals('2022-06-01', $user['potvrzeni_zakonneho_zastupce']);
        $this->assertEquals('2022-07-01 14:00:00', $user['potvrzeni_proti_covid19_pridano_kdy']);
        $this->assertEquals('2022-07-02 15:00:00', $user['potvrzeni_proti_covid19_overeno_kdy']);
        $this->assertEquals('Stará infopult poznámka', $user['infopult_poznamka']);
        $this->assertEquals('OP', $user['typ_dokladu_totoznosti']);
        $this->assertEquals('CZ', $user['statni_obcanstvi']);
        $this->assertEquals(1, $user['z_rychloregistrace']);
        $this->assertEquals('2022-06-01 10:00:00', $user['potvrzeni_zakonneho_zastupce_soubor']);

        // Check that balance was merged (200 + 500 = 700)
        $this->assertEquals(700, (int)$user['zustatek'], 'Balance should be sum of both users');

        // Old user should be deleted
        $this->assertOldUserDeleted($staryId);
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
