<?php

declare(strict_types=1);

namespace Gamecon\Tests\Cache;

use App\Kernel;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\SqlStruktura\TypAktivitySqlStruktura as TypSql;
use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Symfony\Component\Filesystem\Filesystem;

class ProgramStaticFileGeneratorTest extends AbstractTestDb
{
    private const ROK = ROCNIK;

    protected static bool $disableStrictTransTables = true;

    private string $publicCacheDir;
    private string $privateCacheDir;
    private Filesystem $filesystem;

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            fn () => dbInsertUpdate(
                TypSql::TYP_AKTIVITY_TABULKA,
                [
                    TypSql::ID_TYPU   => TypAktivity::DESKOHERNA,
                    TypSql::STRANKA_O => dbFetchSingle('SELECT id_stranky FROM stranky LIMIT 1'),
                ],
            ),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem();
        $this->publicCacheDir = sys_get_temp_dir() . '/gamecon-test-public-cache-' . getmypid() . '-' . mt_rand();
        $this->privateCacheDir = sys_get_temp_dir() . '/gamecon-test-private-cache-' . getmypid() . '-' . mt_rand();
        $this->filesystem->mkdir($this->publicCacheDir);
        $this->filesystem->mkdir($this->privateCacheDir);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->publicCacheDir);
        $this->filesystem->remove($this->privateCacheDir);

        parent::tearDown();
    }

    private function createSystemoveNastaveni(): SystemoveNastaveni
    {
        return new SystemoveNastaveni(
            self::ROK,
            new DateTimeImmutableStrict(),
            false,
            false,
            DatabazoveNastaveni::vytvorZGlobals(),
            '',
            $this->privateCacheDir,
            new Kernel('test', false),
            $this->publicCacheDir,
        );
    }

    private function createGenerator(?SystemoveNastaveni $systemoveNastaveni = null): ProgramStaticFileGenerator
    {
        return new ProgramStaticFileGenerator($systemoveNastaveni ?? $this->createSystemoveNastaveni());
    }

    private function insertAktivita(array $data): int
    {
        $defaults = [
            Sql::NAZEV_AKCE   => 'Test aktivita',
            Sql::POPIS_KRATKY => 'Krátký popis',
            Sql::POPIS        => 1,
            Sql::ROK          => self::ROK,
            Sql::STAV         => StavAktivity::AKTIVOVANA,
            Sql::TYP          => TypAktivity::DESKOHERNA,
            Sql::ZACATEK      => date('Y-m-d 10:00:00'),
            Sql::KONEC        => date('Y-m-d 13:00:00'),
            Sql::KAPACITA     => 5,
            Sql::KAPACITA_F   => 0,
            Sql::KAPACITA_M   => 0,
            Sql::CENA         => 100,
            Sql::TEAMOVA      => 0,
        ];

        $merged = array_merge($defaults, $data);
        dbInsertUpdate(Sql::AKCE_SEZNAM_TABULKA, $merged);

        return (int) dbInsertId();
    }

    /**
     * @test
     */
    public function generateAktivityCreatesJsonFile(): void
    {
        $idAktivity = $this->insertAktivita([
            Sql::NAZEV_AKCE   => 'RPG Dračí Doupě',
            Sql::POPIS_KRATKY => 'Krátký RPG popis',
            Sql::POPIS        => 'Popis testovací aktivity',
            Sql::CENA         => 150,
            Sql::STAV         => StavAktivity::AKTIVOVANA,
            Sql::ZACATEK      => date('Y-m-d 10:15:00'),
            Sql::KONEC        => date('Y-m-d 13:45:00'),
        ]);

        $generator = $this->createGenerator();
        $filename = $generator->generateActivities(self::ROK);

        $filepath = $this->publicCacheDir . '/program/' . $filename;
        self::assertFileExists($filepath);
        self::assertStringEndsWith('.json', $filename);
        self::assertStringStartsWith('aktivity-' . self::ROK . '-', $filename);

        $data = json_decode(file_get_contents($filepath), true);
        self::assertIsArray($data);
        self::assertNotEmpty($data);

        $found = null;
        foreach ($data as $item) {
            if ($item['id'] === $idAktivity) {
                $found = $item;
                break;
            }
        }

        self::assertNotNull($found, "Aktivita {$idAktivity} not found in JSON output");
        self::assertSame('RPG Dračí Doupě', $found['nazev']);
        self::assertSame('Krátký RPG popis', $found['kratkyPopis']);
        self::assertSame(150, $found['cenaZaklad']);
        self::assertSame('10:15&ndash;13:45', $found['casText']);
        self::assertArrayHasKey('cas', $found);
        self::assertArrayHasKey('od', $found['cas']);
        self::assertArrayHasKey('do', $found['cas']);
        self::assertNotEmpty($found['linie']);
    }

    /**
     * @test
     * Regression: array_filter previously stripped every falsy field from
     * aktivity.json — a cenaZaklad of 0, an empty vypraveci array, or an
     * empty stitkyId array would be dropped, and a null/0 popisId would
     * prevent the frontend from joining the long annotation from popisy.json.
     */
    public function generateAktivityKeepsAllFieldsEvenWhenFalsy(): void
    {
        $idAktivity = $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Zdarma aktivita bez vypravěče',
            Sql::POPIS      => 42,
            Sql::CENA       => 0,
        ]);

        $generator = $this->createGenerator();
        $filename = $generator->generateActivities(self::ROK);

        $data = json_decode(file_get_contents($this->publicCacheDir . '/program/' . $filename), true);

        $found = null;
        foreach ($data as $item) {
            if ($item['id'] === $idAktivity) {
                $found = $item;
                break;
            }
        }

        self::assertNotNull($found, "Aktivita {$idAktivity} not found in JSON output");
        self::assertArrayHasKey('popisId', $found, 'popisId must be present — frontend joins popisy.json through this key');
        self::assertNotEmpty($found['popisId'], 'popisId must be non-empty for activity with real description');
        self::assertArrayHasKey('cenaZaklad', $found, 'cenaZaklad must stay in JSON even when 0');
        self::assertSame(0, $found['cenaZaklad']);
        self::assertArrayHasKey('vypraveci', $found, 'vypraveci must stay in JSON even when empty');
        self::assertSame([], $found['vypraveci']);
        self::assertArrayHasKey('stitkyId', $found, 'stitkyId must stay in JSON even when empty');
        self::assertSame([], $found['stitkyId']);
    }

    /**
     * @test
     */
    public function generatePopisyCreatesJsonFile(): void
    {
        $popisText = 'Popis testovací aktivity pro JSON';

        $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Aktivita s popisem',
            Sql::POPIS      => $popisText,
        ]);

        $generator = $this->createGenerator();
        $filename = $generator->generatePopisy(self::ROK);

        $filepath = $this->publicCacheDir . '/program/' . $filename;
        self::assertFileExists($filepath);
        self::assertStringStartsWith('popisy-' . self::ROK . '-', $filename);

        $data = json_decode(file_get_contents($filepath), true);
        self::assertIsArray($data);
        self::assertNotEmpty($data);

        $popisTexts = array_column($data, 'popis');
        $containsExpectedText = false;
        foreach ($popisTexts as $popis) {
            if (str_contains($popis, $popisText)) {
                $containsExpectedText = true;
                break;
            }
        }
        self::assertTrue($containsExpectedText, "Expected popis text '{$popisText}' not found in generated JSON");
    }

    /**
     * @test
     */
    public function generateObsazenostiCreatesJsonFile(): void
    {
        $idAktivity = $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Aktivita pro obsazenosti',
            Sql::KAPACITA   => 10,
        ]);

        $generator = $this->createGenerator();
        $filename = $generator->generateObsazenosti(self::ROK);

        $filepath = $this->publicCacheDir . '/program/' . $filename;
        self::assertFileExists($filepath);
        self::assertStringStartsWith('obsazenosti-' . self::ROK . '-', $filename);

        $data = json_decode(file_get_contents($filepath), true);
        self::assertIsArray($data);

        $found = null;
        foreach ($data as $item) {
            if ($item['idAktivity'] === $idAktivity) {
                $found = $item;
                break;
            }
        }

        self::assertNotNull($found, "Obsazenost for activity {$idAktivity} not found");
        self::assertArrayHasKey('obsazenost', $found);
    }

    /**
     * @test
     */
    public function regenerateAllCreatesManifest(): void
    {
        $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Aktivita pro manifest',
        ]);

        $generator = $this->createGenerator();
        $generator->regenerateAll(self::ROK);

        $manifestPath = $this->publicCacheDir . '/program/manifest-' . self::ROK . '.json';
        self::assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        self::assertIsArray($manifest);
        self::assertArrayHasKey('aktivity', $manifest);
        self::assertArrayHasKey('popisy', $manifest);
        self::assertArrayHasKey('obsazenosti', $manifest);

        foreach ($manifest as $type => $filename) {
            $file = $this->publicCacheDir . '/program/' . $filename;
            self::assertFileExists($file, "Manifest references non-existing file: {$filename}");
        }
    }

    /**
     * @test
     */
    public function dirtyFlagsAreCreatedAndDeleted(): void
    {
        $systemoveNastaveni = $this->createSystemoveNastaveni();
        $generator = new ProgramStaticFileGenerator($systemoveNastaveni);

        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));

        $generator->touchDirtyFlag(ProgramStaticFileType::AKTIVITY);
        self::assertTrue($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::POPISY));

        $generator->deleteDirtyFlag(ProgramStaticFileType::AKTIVITY);
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));
    }

    /**
     * @test
     */
    public function touchDirtyFlagWithoutStartingWorker(): void
    {
        $generator = $this->createGenerator();

        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));

        // tryStartWorker: false should still create the flag
        $generator->touchDirtyFlag(ProgramStaticFileType::AKTIVITY, tryStartWorker: false);
        self::assertTrue($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));

        // Second call with tryStartWorker: true (default) should also work
        $generator->touchDirtyFlag(ProgramStaticFileType::POPISY);
        self::assertTrue($generator->hasDirtyFlag(ProgramStaticFileType::POPISY));

        $generator->deleteDirtyFlag(ProgramStaticFileType::AKTIVITY);
        $generator->deleteDirtyFlag(ProgramStaticFileType::POPISY);
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::POPISY));
    }

    /**
     * @test
     */
    public function activityChangeTriggersJsonRegeneration(): void
    {
        $idAktivity = $this->insertAktivita([
            Sql::NAZEV_AKCE   => 'Měnící se aktivita',
            Sql::POPIS_KRATKY => 'Krátký popis originálu',
            Sql::POPIS        => 'Původní popis',
            Sql::CENA         => 200,
        ]);

        $generator = $this->createGenerator();

        $filenameV1 = $generator->generateActivities(self::ROK);
        $filepathV1 = $this->publicCacheDir . '/program/' . $filenameV1;
        $dataV1 = json_decode(file_get_contents($filepathV1), true);

        $foundV1 = null;
        foreach ($dataV1 as $item) {
            if ($item['id'] === $idAktivity) {
                $foundV1 = $item;
                break;
            }
        }
        self::assertNotNull($foundV1);
        self::assertSame('Měnící se aktivita', $foundV1['nazev']);
        self::assertSame(200, $foundV1['cenaZaklad']);

        // Now change the activity name and price in DB
        dbUpdate(
            Sql::AKCE_SEZNAM_TABULKA,
            [
                Sql::NAZEV_AKCE => 'Změněná aktivita',
                Sql::CENA       => 300,
            ],
            [
                Sql::ID_AKCE => $idAktivity,
            ],
        );

        // Create a fresh generator to avoid query cache
        $generator = $this->createGenerator();

        $filenameV2 = $generator->generateActivities(self::ROK);
        self::assertNotSame($filenameV1, $filenameV2, 'Changed data should produce different filename (hash)');

        $filepathV2 = $this->publicCacheDir . '/program/' . $filenameV2;
        $dataV2 = json_decode(file_get_contents($filepathV2), true);

        $foundV2 = null;
        foreach ($dataV2 as $item) {
            if ($item['id'] === $idAktivity) {
                $foundV2 = $item;
                break;
            }
        }
        self::assertNotNull($foundV2);
        self::assertSame('Změněná aktivita', $foundV2['nazev']);
        self::assertSame(300, $foundV2['cenaZaklad']);
    }

    /**
     * @test
     */
    public function invisibleActivityIsNotInGeneratedJson(): void
    {
        $idVisible = $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Viditelná aktivita',
            Sql::STAV       => StavAktivity::AKTIVOVANA,
        ]);

        $idInvisible = $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Neviditelná aktivita',
            Sql::STAV       => StavAktivity::NOVA,
        ]);

        $generator = $this->createGenerator();
        $filename = $generator->generateActivities(self::ROK);
        $data = json_decode(file_get_contents($this->publicCacheDir . '/program/' . $filename), true);

        $ids = array_column($data, 'id');
        self::assertContains($idVisible, $ids);
        self::assertNotContains($idInvisible, $ids);
    }

    /**
     * @test
     * Simulates the worker loop: dirty flags are deleted before generation starts,
     * so a concurrent activity change during generation creates new dirty flags
     * that trigger another regeneration iteration with fresh data.
     */
    public function dirtyFlagDuringGenerationTriggersAnotherIteration(): void
    {
        $systemoveNastaveni = $this->createSystemoveNastaveni();
        $generator = new ProgramStaticFileGenerator($systemoveNastaveni);

        $idOriginal = $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Původní aktivita',
            Sql::CENA       => 100,
        ]);

        // Simulate: activity change marks cache as dirty
        $generator->touchDirtyFlag(ProgramStaticFileType::AKTIVITY);
        $generator->touchDirtyFlag(ProgramStaticFileType::POPISY);

        // === Worker iteration 1 ===
        // Worker checks dirty flags
        self::assertTrue($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));
        self::assertTrue($generator->hasDirtyFlag(ProgramStaticFileType::POPISY));
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::OBSAZENOSTI));

        // Worker deletes flags BEFORE regeneration (as the real worker does)
        $generator->deleteDirtyFlag(ProgramStaticFileType::AKTIVITY);
        $generator->deleteDirtyFlag(ProgramStaticFileType::POPISY);

        // Generation is now in progress...
        $filenameV1 = $generator->generateActivities(self::ROK);
        $generator->generatePopisy(self::ROK);
        $generator->updateManifest(self::ROK);

        // Verify V1 content
        $dataV1 = json_decode(file_get_contents($this->publicCacheDir . '/program/' . $filenameV1), true);
        $foundV1 = null;
        foreach ($dataV1 as $item) {
            if ($item['id'] === $idOriginal) {
                $foundV1 = $item;
                break;
            }
        }
        self::assertNotNull($foundV1);
        self::assertSame('Původní aktivita', $foundV1['nazev']);

        // Meanwhile, DURING generation, another activity change occurs
        // This simulates a concurrent request modifying data + touching dirty flags
        dbUpdate(
            Sql::AKCE_SEZNAM_TABULKA,
            [
                Sql::NAZEV_AKCE => 'Změněná aktivita',
                Sql::CENA       => 999,
            ],
            [
                Sql::ID_AKCE => $idOriginal,
            ],
        );
        $generator->touchDirtyFlag(ProgramStaticFileType::AKTIVITY);

        // === Worker iteration 2 (loop continues) ===
        // Worker checks again — the new dirty flag from concurrent change is detected
        self::assertTrue(
            $generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY),
            'Dirty flag set during generation must be visible for the next worker iteration',
        );
        self::assertFalse(
            $generator->hasDirtyFlag(ProgramStaticFileType::POPISY),
            'Popisy flag should not be dirty — only aktivity was changed concurrently',
        );

        // Worker deletes the new flag and regenerates
        $generator->deleteDirtyFlag(ProgramStaticFileType::AKTIVITY);

        // Clear prefetched table versions so the next SQL fetch picks up DB changes
        // even when the worker stays alive in the same process.
        $systemoveNastaveni->db()->clearPrefetchedDataVersions();
        $generator->reset();

        $filenameV2 = $generator->generateActivities(self::ROK);
        $generator->updateManifest(self::ROK);

        self::assertNotSame($filenameV1, $filenameV2, 'Second generation must produce a different file with updated data');

        // Verify V2 has the updated data
        $dataV2 = json_decode(file_get_contents($this->publicCacheDir . '/program/' . $filenameV2), true);
        $foundV2 = null;
        foreach ($dataV2 as $item) {
            if ($item['id'] === $idOriginal) {
                $foundV2 = $item;
                break;
            }
        }
        self::assertNotNull($foundV2);
        self::assertSame('Změněná aktivita', $foundV2['nazev']);
        self::assertSame(999, $foundV2['cenaZaklad']);

        // === Worker iteration 3 (loop should stop) ===
        // No more dirty flags — worker exits
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::AKTIVITY));
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::POPISY));
        self::assertFalse($generator->hasDirtyFlag(ProgramStaticFileType::OBSAZENOSTI));
    }

    /**
     * @test
     */
    public function readManifestReturnsNullWhenNoManifestExists(): void
    {
        $generator = $this->createGenerator();

        self::assertNull($generator->readManifest());
    }

    /**
     * @test
     */
    public function lazyInitRegeneratesWhenManifestIsMissing(): void
    {
        $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Lazy init aktivita',
            Sql::CENA       => 42,
        ]);

        $generator = $this->createGenerator();

        // Simulate the lazy init logic from program.php
        self::assertNull($generator->readManifest());
        $generator->regenerateAll(self::ROK);
        $manifest = $generator->readManifest();

        self::assertNotNull($manifest, 'Manifest must exist after regenerateAll');
        self::assertArrayHasKey('aktivity', $manifest);
        self::assertArrayHasKey('popisy', $manifest);
        self::assertArrayHasKey('obsazenosti', $manifest);

        // Verify files have real content (not empty arrays)
        $aktivityFile = $this->publicCacheDir . '/program/' . $manifest['aktivity'];
        $data = json_decode(file_get_contents($aktivityFile), true);
        self::assertNotEmpty($data, 'Lazy init must produce non-empty activity data');
    }

    /**
     * @test
     */
    public function secondCallToReadManifestSkipsRegeneration(): void
    {
        $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Aktivita pro double check',
        ]);

        $generator = $this->createGenerator();
        $generator->regenerateAll(self::ROK);

        $manifest1 = $generator->readManifest();
        self::assertNotNull($manifest1);

        // Second call should return the same manifest without regeneration
        $manifest2 = $generator->readManifest();
        self::assertSame($manifest1, $manifest2);
    }

    /**
     * @test
     */
    public function cleanupRemovesOldFiles(): void
    {
        $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Aktivita pro cleanup',
        ]);

        $generator = $this->createGenerator();
        $generator->regenerateAll(self::ROK);

        $programDir = $this->publicCacheDir . '/program';

        // Create an old stale file (not in manifest)
        $staleFile = $programDir . '/aktivity-' . self::ROK . '-oldhash123.json';
        file_put_contents($staleFile, '[]');
        touch($staleFile, time() - 7200); // 2 hours ago

        self::assertFileExists($staleFile);

        $generator->cleanup(self::ROK);

        self::assertFileDoesNotExist($staleFile);

        // Manifest-referenced files should still exist
        $manifest = json_decode(file_get_contents($programDir . '/manifest-' . self::ROK . '.json'), true);
        foreach ($manifest as $filename) {
            self::assertFileExists($programDir . '/' . $filename);
        }
    }

    /**
     * @test
     * Sdílený helper aktivitaDoPole musí produkovat přesně tu samou strukturu
     * jakou generateActivities zapisuje do aktivity-{rok}-{hash}.json — jinak
     * by se aktivitySkryte (z user-API) slučené na frontendu mohlo rozejít.
     */
    public function aktivitaDoPoleMaStejnouStrukturuJakoGenerateActivities(): void
    {
        $idAktivity = $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Test shared helper',
        ]);

        $generator = $this->createGenerator();
        $filename = $generator->generateActivities(self::ROK);
        $zJsonu = null;
        $data = json_decode(
            file_get_contents($this->publicCacheDir . '/program/' . $filename),
            true,
        );
        foreach ($data as $item) {
            if ($item['id'] === $idAktivity) {
                $zJsonu = $item;
                break;
            }
        }
        self::assertNotNull($zJsonu);

        $aktivita = \Gamecon\Aktivita\Aktivita::zId(id: $idAktivity);
        $collector = new \Gamecon\Cache\DataSourcesCollector();
        \Gamecon\Aktivita\Aktivita::organizatoriDSC($collector);
        $zHelperu = ProgramStaticFileGenerator::aktivitaDoPole($aktivita, $collector);

        self::assertSame(
            array_keys($zJsonu),
            array_keys($zHelperu),
            'aktivitaDoPole musí produkovat stejné klíče jako generateActivities.',
        );
        self::assertSame(
            $zJsonu,
            $zHelperu,
            'aktivitaDoPole musí produkovat stejná data jako generateActivities pro tutéž aktivitu.',
        );
    }

    /**
     * @test
     * Deploy gate: regenerateAll() se dřívě ukončí, pokud už manifest existuje
     * (ochrana před souběžnými requesty). Na deployi je to ale problém —
     * starý manifest z předchozího releasu způsobí, že nový kód nevygeneruje
     * žádné nové JSONy.
     *
     * Ověřujeme, že když se před regenerateAll() smaže manifest, metoda
     * skutečně projde a vytvoří ho znovu.
     */
    public function deploySmazaniManifestuVynutiRegeneraci(): void
    {
        $this->insertAktivita([
            Sql::NAZEV_AKCE => 'Aktivita pro deploy regen',
        ]);

        $generator = $this->createGenerator();
        $programDir = $this->publicCacheDir . '/program';
        $manifestPath = $programDir . '/manifest-' . self::ROK . '.json';

        // Inicializace: manifest a hashované soubory existují.
        $generator->regenerateAll(self::ROK);
        self::assertFileExists($manifestPath);
        $puvodniMtime = filemtime($manifestPath);

        // Sanity check: opakované volání regenerateAll() je no-op,
        // manifest není přepsán (early-return v ProgramStaticFileGenerator::regenerateAll).
        clearstatcache();
        $generator->regenerateAll(self::ROK);
        self::assertSame(
            $puvodniMtime,
            filemtime($manifestPath),
            'regenerateAll() nesmí přepsat existující manifest (early-return).',
        );

        // Deploy logika: smazání manifestu před regenerateAll() VYNUTÍ plnou
        // regeneraci. Toto je přesně to, co dělá admin/deploy/regeneruj-program-cache.php.
        unlink($manifestPath);
        self::assertFileDoesNotExist($manifestPath);

        $generatorPoDeployi = $this->createGenerator();
        $generatorPoDeployi->regenerateAll(self::ROK);

        self::assertFileExists(
            $manifestPath,
            'Po smazání manifestu a volání regenerateAll() musí vzniknout nový manifest.',
        );
    }
}
