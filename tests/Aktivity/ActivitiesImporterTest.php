<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ActivitiesImporter;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ActivitiesImportLogger;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Mutex\Mutex;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Tests\Factory\LocationFactory;
use Gamecon\Vyjimkovac\Logovac;

/**
 * Testy pro import aktivit s více místnostmi (multiple locations per activity)
 */
class ActivitiesImporterTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function () {
                // Create 3 test locations using LocationFactory
                LocationFactory::createOne([
                    'nazev' => 'Velká místnost',
                    'dvere' => 'Budova A, dveře 101',
                    'poznamka' => '',
                    'poradi' => 1,
                    'rok' => 0,
                ]);

                LocationFactory::createOne([
                    'nazev' => 'Malá místnost',
                    'dvere' => 'Budova A, dveře 102',
                    'poznamka' => '',
                    'poradi' => 2,
                    'rok' => 0,
                ]);

                LocationFactory::createOne([
                    'nazev' => 'Klubovna',
                    'dvere' => 'Budova B, dveře 201',
                    'poznamka' => '',
                    'poradi' => 3,
                    'rok' => 0,
                ]);

                // Program line (Deskoherna) already exists in base schema
            },
        ];
    }

    /**
     * @test
     */
    public function testImportActivityWithSingleLocation()
    {
        // Mock Google Sheets with single location
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita 1', 'url-1', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);

        // Create importer with mocked service
        $importer = $this->createImporter($mockData);

        // Run import
        $result = $importer->importActivities('fake-spreadsheet-id');

        // Debug: Check for errors
        if ($result->getImportedCount() === 0) {
            $errors = $result->getErrorMessages();
            $this->fail('Import failed with errors: ' . print_r($errors, true));
        }

        // Verify success
        self::assertSame(1, $result->getImportedCount());

        // Find imported activity by URL
        $aktivita = $this->fetchActivityByUrl('url-1', ROCNIK);

        // Verify locations
        self::assertCount(1, $aktivita->seznamLokaci());
        self::assertNotNull($aktivita->hlavniLokace());
        self::assertSame('Velká místnost', $aktivita->hlavniLokace()->nazev());

        $locationIds = $aktivita->seznamLokaciIdcka();
        self::assertCount(1, $locationIds);

        // Verify database
        $rows = dbFetchAll('SELECT id_lokace, je_hlavni FROM akce_lokace WHERE id_akce = ? ORDER BY id_lokace', [$aktivita->id()]);
        self::assertCount(1, $rows);
        self::assertSame('1', $rows[0]['je_hlavni']);
    }

    /**
     * @test
     */
    public function testImportActivityWithMultipleLocations()
    {
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita 2', 'url-2', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost; Malá místnost; Klubovna', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);

        $importer = $this->createImporter($mockData);
        $result = $importer->importActivities('fake-spreadsheet-id');

        self::assertSame(1, $result->getImportedCount());

        $aktivita = $this->fetchActivityByUrl('url-2', ROCNIK);

        // Verify 3 locations
        self::assertCount(3, $aktivita->seznamLokaci());
        self::assertSame('Velká místnost', $aktivita->hlavniLokace()->nazev()); // First is main

        $locationIds = $aktivita->seznamLokaciIdcka();
        self::assertCount(3, $locationIds);

        // Verify database - main location flagged
        $rows = dbFetchAll(
            'SELECT id_lokace, je_hlavni FROM akce_lokace WHERE id_akce = ? ORDER BY id_lokace',
            [$aktivita->id()]
        );
        self::assertCount(3, $rows);

        // First location should be main
        $mainCount = 0;
        foreach ($rows as $row) {
            if ($row['je_hlavni'] === '1') {
                $mainCount++;
            }
        }
        self::assertSame(1, $mainCount, 'Exactly one location should be marked as main');
    }

    /**
     * @test
     */
    public function testImportActivityWithNoLocation()
    {
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita 3', 'url-3', 'Popis', '', '', 'Pátek', '10:00', '12:00', '', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);

        $importer = $this->createImporter($mockData);
        $result = $importer->importActivities('fake-spreadsheet-id');

        self::assertSame(1, $result->getImportedCount());

        $aktivita = $this->fetchActivityByUrl('url-3', ROCNIK);

        // Verify no locations
        self::assertSame([], $aktivita->seznamLokaci());
        self::assertNull($aktivita->hlavniLokace());
        self::assertSame([], $aktivita->seznamLokaciIdcka());

        // Verify database
        $count = dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce = ?', [$aktivita->id()]);
        self::assertSame('0', $count);
    }

    /**
     * @test
     */
    public function testReimportSameDataDoesNotChangeAnything()
    {
        // First import - create 3 activities with different location configurations
        // Use unique URLs to avoid conflicts with other tests
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita Reimport 1', 'url-reimport-1', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
            ['', 'Deskoherna', 'Aktivita Reimport 2', 'url-reimport-2', 'Popis', '', '', 'Pátek', '13:00', '15:00', 'Velká místnost; Malá místnost; Klubovna', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
            ['', 'Deskoherna', 'Aktivita Reimport 3', 'url-reimport-3', 'Popis', '', '', 'Pátek', '16:00', '18:00', '', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);

        $importer = $this->createImporter($mockData);
        $result1 = $importer->importActivities('fake-spreadsheet-id');
        self::assertSame(3, $result1->getImportedCount());

        // Get activity IDs
        $aktivita1 = $this->fetchActivityByUrl('url-reimport-1', ROCNIK);
        $aktivita2 = $this->fetchActivityByUrl('url-reimport-2', ROCNIK);
        $aktivita3 = $this->fetchActivityByUrl('url-reimport-3', ROCNIK);

        // Capture initial state
        $beforeState = [
            'aktivita1_locations' => $aktivita1->seznamLokaciIdcka(),
            'aktivita2_locations' => $aktivita2->seznamLokaciIdcka(),
            'aktivita3_locations' => $aktivita3->seznamLokaciIdcka(),
            'akce_lokace_count' => dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce IN (?, ?, ?)', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
            'akce_lokace_rows' => dbFetchAll('SELECT id_akce, id_lokace, je_hlavni FROM akce_lokace WHERE id_akce IN (?, ?, ?) ORDER BY id_akce, id_lokace', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
        ];

        // Second import - with activity IDs this time (update existing)
        // Note: IDs must be strings since Google Sheets returns strings
        $mockDataUpdate = $this->createMockSheetData([
            [(string)$aktivita1->id(), 'Deskoherna', 'Aktivita Reimport 1', 'url-reimport-1', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
            [(string)$aktivita2->id(), 'Deskoherna', 'Aktivita Reimport 2', 'url-reimport-2', 'Popis', '', '', 'Pátek', '13:00', '15:00', 'Velká místnost; Malá místnost; Klubovna', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
            [(string)$aktivita3->id(), 'Deskoherna', 'Aktivita Reimport 3', 'url-reimport-3', 'Popis', '', '', 'Pátek', '16:00', '18:00', '', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);

        $importer2 = $this->createImporter($mockDataUpdate);
        $result2 = $importer2->importActivities('fake-spreadsheet-id');
        self::assertSame(3, $result2->getImportedCount());

        // Reload activities
        $aktivita1 = Aktivita::zId($aktivita1->id());
        $aktivita2 = Aktivita::zId($aktivita2->id());
        $aktivita3 = Aktivita::zId($aktivita3->id());

        // Capture final state
        $afterState = [
            'aktivita1_locations' => $aktivita1->seznamLokaciIdcka(),
            'aktivita2_locations' => $aktivita2->seznamLokaciIdcka(),
            'aktivita3_locations' => $aktivita3->seznamLokaciIdcka(),
            'akce_lokace_count' => dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce IN (?, ?, ?)', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
            'akce_lokace_rows' => dbFetchAll('SELECT id_akce, id_lokace, je_hlavni FROM akce_lokace WHERE id_akce IN (?, ?, ?) ORDER BY id_akce, id_lokace', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
        ];

        // Verify nothing changed
        self::assertSame($beforeState, $afterState, 'Re-import with same data should not change anything');
    }

    /**
     * Helper method to create mock Google Sheets data
     */
    private function createMockSheetData(array $dataRows): array
    {
        $headers = [
            'ID aktivity',
            'Programová linie',
            'Název',
            'URL',
            'Krátká anotace',
            'Tagy',
            'Dlouhá anotace',
            'Den',
            'Začátek',
            'Konec',
            'Místnost',
            'Vypravěči',
            'Kapacita unisex',
            'Kapacita muži',
            'Kapacita ženy',
            'Je týmová',
            'Minimální kapacita týmu',
            'Maximální kapacita týmu',
            'Následující (semi)finále',
            'Cena',
            'Bez slev',
            'Příprava místnosti',
            'Stav',
            'Obrázek',
        ];

        return array_merge([$headers], $dataRows);
    }

    /**
     * Helper method to fetch activity by URL
     */
    private function fetchActivityByUrl(string $url, int $rok): ?Aktivita
    {
        $id = dbFetchSingle('SELECT id_akce FROM akce_seznam WHERE url_akce = ? AND rok = ?', [$url, $rok]);
        return $id ? Aktivita::zId((int)$id) : null;
    }

    /**
     * Helper method to create ActivitiesImporter with mocked dependencies
     */
    private function createImporter(array $mockSheetData): ActivitiesImporter
    {
        // Create mocks
        $mockGoogleSheets = $this->createMock(GoogleSheetsService::class);
        $mockGoogleSheets->method('getSpreadsheetValues')
            ->willReturn($mockSheetData);

        $mockGoogleDrive = $this->createMock(GoogleDriveService::class);
        $mockGoogleDrive->method('getFileName')
            ->willReturn('Test Import File');

        $mockLogovac = $this->createMock(Logovac::class);

        $mockMutex = $this->createMock(Mutex::class);
        $mockMutex->method('cekejAZamkni')->willReturn(true);
        $mockMutex->method('dejProPodAkci')->willReturnSelf();

        $mockImportLogger = $this->createMock(ActivitiesImportLogger::class);

        // Create importer with real dependencies
        return new ActivitiesImporter(
            userId: 1,
            googleDriveService: $mockGoogleDrive,
            googleSheetsService: $mockGoogleSheets,
            editActivityUrlSkeleton: '/admin/aktivity/upravit?id=%d',
            now: new \DateTimeImmutable(),
            storytellersPermissionsUrl: '/permissions',
            logovac: $mockLogovac,
            mutexPattern: $mockMutex,
            errorsListUrl: '/errors',
            activitiesImportLogger: $mockImportLogger,
            exportAktivitSloupce: new ExportAktivitSloupce(),
            dateTimeCz: new DateTimeCz()
        );
    }
}
