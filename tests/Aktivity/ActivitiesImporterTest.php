<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ActivitiesImporter;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ActivitiesImportLogger;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\Lokace;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Mutex\Mutex;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
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
                    'nazev'    => 'Velká místnost',
                    'dvere'    => 'Budova A, dveře 101',
                    'poznamka' => '',
                    'poradi'   => 1,
                    'rok'      => 0,
                ]);

                LocationFactory::createOne([
                    'nazev'    => 'Malá místnost',
                    'dvere'    => 'Budova A, dveře 102',
                    'poznamka' => '',
                    'poradi'   => 2,
                    'rok'      => 0,
                ]);

                LocationFactory::createOne([
                    'nazev'    => 'Klubovna',
                    'dvere'    => 'Budova B, dveře 201',
                    'poznamka' => '',
                    'poradi'   => 3,
                    'rok'      => 0,
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
            ['', 'Deskoherna', 'Aktivita 1', 'url-1', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
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
        $rows = dbFetchAll('SELECT id_lokace FROM akce_lokace WHERE id_akce = ? ORDER BY id_lokace', [$aktivita->id()]);
        self::assertCount(1, $rows);

        // The only location is the automatic main location, so nothing is chosen explicitly
        self::assertNull(self::idZvoleneHlavniLokace($aktivita->id()));
    }

    /**
     * @test
     */
    public function testImportActivityWithMultipleLocations()
    {
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita 2', 'url-2', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost; Malá místnost; Klubovna', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
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

        // Verify database
        $rows = dbFetchAll(
            'SELECT id_lokace FROM akce_lokace WHERE id_akce = ? ORDER BY id_lokace',
            [$aktivita->id()]
        );
        self::assertCount(3, $rows);

        // The first location of the cell is the one the automatic rule picks anyway, so it stays automatic
        self::assertNull(self::idZvoleneHlavniLokace($aktivita->id()), 'Automatic main location should stay automatic');
    }

    /**
     * @test
     */
    public function testImportActivityWithNoLocation()
    {
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita 3', 'url-3', 'Popis', '', '', 'Pátek', '10:00', '12:00', '', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
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
            ['', 'Deskoherna', 'Aktivita Reimport 1', 'url-reimport-1', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            ['', 'Deskoherna', 'Aktivita Reimport 2', 'url-reimport-2', 'Popis', '', '', 'Pátek', '13:00', '15:00', 'Velká místnost; Malá místnost; Klubovna', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            ['', 'Deskoherna', 'Aktivita Reimport 3', 'url-reimport-3', 'Popis', '', '', 'Pátek', '16:00', '18:00', '', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
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
            'akce_lokace_count'   => dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce IN (?, ?, ?)', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
            'akce_lokace_rows'    => dbFetchAll('SELECT id_akce, id_lokace FROM akce_lokace WHERE id_akce IN (?, ?, ?) ORDER BY id_akce, id_lokace', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
        ];

        // Second import - with activity IDs this time (update existing)
        // Note: IDs must be strings since Google Sheets returns strings
        $mockDataUpdate = $this->createMockSheetData([
            [(string) $aktivita1->id(), 'Deskoherna', 'Aktivita Reimport 1', 'url-reimport-1', 'Popis', '', '', 'Pátek', '10:00', '12:00', 'Velká místnost', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            [(string) $aktivita2->id(), 'Deskoherna', 'Aktivita Reimport 2', 'url-reimport-2', 'Popis', '', '', 'Pátek', '13:00', '15:00', 'Velká místnost; Malá místnost; Klubovna', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            [(string) $aktivita3->id(), 'Deskoherna', 'Aktivita Reimport 3', 'url-reimport-3', 'Popis', '', '', 'Pátek', '16:00', '18:00', '', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
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
            'akce_lokace_count'   => dbFetchSingle('SELECT COUNT(*) FROM akce_lokace WHERE id_akce IN (?, ?, ?)', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
            'akce_lokace_rows'    => dbFetchAll('SELECT id_akce, id_lokace FROM akce_lokace WHERE id_akce IN (?, ?, ?) ORDER BY id_akce, id_lokace', [$aktivita1->id(), $aktivita2->id(), $aktivita3->id()]),
        ];

        // Verify nothing changed
        self::assertSame($beforeState, $afterState, 'Re-import with same data should not change anything');
    }

    public function testExportAZpetnyImportZachovajiZvolenouIAutomatickouHlavniMistnost(): void
    {
        // Klubovna first is an explicit choice (the automatic one would be Velká místnost),
        // Malá místnost first is the automatic main location of its two rooms
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita Hlavni 1', 'url-hlavni-1', 'Popis', '', '', 'Pátek', '19:00', '20:00', 'Klubovna; Velká místnost', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            ['', 'Deskoherna', 'Aktivita Hlavni 2', 'url-hlavni-2', 'Popis', '', '', 'Pátek', '20:00', '21:00', 'Klubovna; Malá místnost', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            ['', 'Deskoherna', 'Aktivita Hlavni 3', 'url-hlavni-3', 'Popis', '', '', 'Pátek', '21:00', '22:00', 'Malá místnost; Klubovna', '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);
        self::assertSame(3, $this->createImporter($mockData)->importActivities('fake-spreadsheet-id')->getImportedCount());

        $zvolena = $this->fetchActivityByUrl('url-hlavni-1', ROCNIK);
        $zvolenaMimoPoradi = $this->fetchActivityByUrl('url-hlavni-2', ROCNIK);
        $automaticka = $this->fetchActivityByUrl('url-hlavni-3', ROCNIK);
        $idKlubovny = $zvolena->hlavniLokace()->id();
        self::assertSame('Klubovna', $zvolena->hlavniLokace()->nazev());
        self::assertSame($idKlubovny, self::idZvoleneHlavniLokace($zvolena->id()));
        self::assertSame($idKlubovny, self::idZvoleneHlavniLokace($zvolenaMimoPoradi->id()));
        self::assertSame('Malá místnost', $automaticka->hlavniLokace()->nazev());
        self::assertNull(self::idZvoleneHlavniLokace($automaticka->id()), 'Automatická hlavní místnost se má uložit jako automatická');

        // the export writes the rooms as Aktivita::seznamLokaci() orders them, the main location first
        $bunkaExportu = static fn (Aktivita $aktivita): string => implode(
            '; ',
            array_map(static fn (Lokace $lokace): ?string => $lokace->nazev(), $aktivita->seznamLokaci()),
        );
        $mockDataReimport = $this->createMockSheetData([
            [(string) $zvolena->id(), 'Deskoherna', 'Aktivita Hlavni 1', 'url-hlavni-1', 'Popis', '', '', 'Pátek', '19:00', '20:00', $bunkaExportu($zvolena), '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            [(string) $zvolenaMimoPoradi->id(), 'Deskoherna', 'Aktivita Hlavni 2', 'url-hlavni-2', 'Popis', '', '', 'Pátek', '20:00', '21:00', $bunkaExportu($zvolenaMimoPoradi), '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
            [(string) $automaticka->id(), 'Deskoherna', 'Aktivita Hlavni 3', 'url-hlavni-3', 'Popis', '', '', 'Pátek', '21:00', '22:00', $bunkaExportu($automaticka), '', '10', '', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);
        self::assertSame(3, $this->createImporter($mockDataReimport)->importActivities('fake-spreadsheet-id')->getImportedCount());

        self::assertSame($idKlubovny, self::idZvoleneHlavniLokace($zvolena->id()));
        self::assertSame($idKlubovny, self::idZvoleneHlavniLokace($zvolenaMimoPoradi->id()));
        self::assertNull(self::idZvoleneHlavniLokace($automaticka->id()), 'Zpětný import nesmí z automatické hlavní místnosti udělat zvolenou');
    }

    private static function idZvoleneHlavniLokace(int $idAktivity): ?int
    {
        $idHlavniLokace = dbOneCol(<<<'SQL'
            SELECT id_hlavni_lokace FROM akce_seznam WHERE id_akce = $0
            SQL, [
            0 => $idAktivity,
        ]);

        return $idHlavniLokace === null
            ? null
            : (int) $idHlavniLokace;
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
            'Turnaj',
            'Kolo turnaje',
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

        return $id ? Aktivita::zId((int) $id) : null;
    }

    /**
     * Importér odmítá editovat aktivitu, která už začala. Testovací aktivity se plánují
     * na dny GameConu daného ročníku, takže s reálnými hodinami by testy začaly padat
     * v okamžiku, kdy ročník reálně proběhne. Čas proto zmrazíme na půlnoc dne zahájení.
     */
    private function importedAt(): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface(
            DateTimeGamecon::zacatekGameconu(ROCNIK)->setTime(0, 0),
        );
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
            now: $this->importedAt(),
            storytellersPermissionsUrl: '/permissions',
            logovac: $mockLogovac,
            mutexPattern: $mockMutex,
            errorsListUrl: '/errors',
            activitiesImportLogger: $mockImportLogger,
            exportAktivitSloupce: new ExportAktivitSloupce(),
            dateTimeCz: new DateTimeCz(),
            systemoveNastaveni: SystemoveNastaveni::zGlobals(),
        );
    }

    public function testImportActivityWithTooLongShortDescription()
    {
        $tooLong = str_repeat('a', Aktivita::LIMIT_POPIS_KRATKY + 1);
        $mockData = $this->createMockSheetData([
            ['', 'Deskoherna', 'Aktivita Invalid', 'url-invalid', $tooLong, '', '', 'Pátek', '10:00', '12:00', '', '', '10', '', '', '', '', '', '', '0', '', '', '1', ''],
        ]);

        $importer = $this->createImporter($mockData);
        $result = $importer->importActivities('fake-spreadsheet-id');

        $this->assertSame(0, $result->getImportedCount(), 'Aktivita s příliš dlouhým popiskem by neměla být importována');
        $this->assertNotEmpty($result->getErrorMessages(), 'Import by měl vrátit chybu');
    }
}
