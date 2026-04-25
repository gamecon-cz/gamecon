<?php

declare(strict_types=1);

namespace Gamecon\Cache;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\EditorTagu;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\BackgroundProcess\BackgroundProcessService;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Service\ResetInterface;

class ProgramStaticFileGenerator implements ResetInterface
{
    private readonly string $outputDir;
    private readonly string $dirtyFlagsDir;

    /**
     * @var array<int, Aktivita[]>
     */
    private array $activitiesCache = [];

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
        $this->outputDir = $systemoveNastaveni->publicCacheDir() . '/program';
        $this->dirtyFlagsDir = $systemoveNastaveni->privateCacheDir() . '/program';
    }

    public function generateActivities(int $rok): string
    {
        $activities = $this->loadActivities($rok);

        $dataSourcesCollector = new DataSourcesCollector();
        Aktivita::organizatoriDSC($dataSourcesCollector);

        $aktivityNeprihlasen = [];
        foreach ($activities as $activity) {
            if (! $activity->zacatek() || ! $activity->konec() || ! $activity->viditelnaPro(null)) {
                continue;
            }
            $aktivityNeprihlasen[] = self::aktivitaDoPole($activity, $dataSourcesCollector);
        }

        return $this->writeJsonFile('aktivity', $rok, $aktivityNeprihlasen);
    }

    /**
     * Převede aktivitu na asociativní pole ve stejné struktuře, jakou
     * frontend očekává v {@see \ApiAktivitaNepřihlášen} (TypeScript typu
     * `ApiAktivitaNepřihlášen` v ui/src/api/program/index.ts).
     *
     * Tuto metodu MUSÍ používat každý endpoint, který skládá objekt aktivity
     * pro veřejný program (statické JSONy i aktivitySkryte v uživatelském API),
     * aby se struktura nemohla rozejít mezi různými cestami.
     *
     * Předpokládá, že $activity má naplněný zacatek() a konec().
     *
     * @return array<string, mixed>
     */
    public static function aktivitaDoPole(
        Aktivita $activity,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        $zacatekAktivity = $activity->zacatek();
        $konecAktivity = $activity->konec();

        $vypraveci = array_map(
            fn (\Uzivatel $organizator) => $organizator->jmenoNaWebu(),
            $activity->organizatori(dataSourcesCollector: $dataSourcesCollector),
        );

        $aktivitaRes = [
            'id'          => $activity->id(),
            'nazev'       => $activity->nazev(),
            'kratkyPopis' => $activity->kratkyPopis(),
            'popisId'     => $activity->popisId(),
            'obrazek'     => (string) $activity->obrazek(),
            'vypraveci'   => $vypraveci,
            'stitkyId'    => $activity->tagyId(),
            'cenaZaklad'  => intval($activity->cenaZaklad()),
            'casText'     => $zacatekAktivity->format('G') . ':00&ndash;' . $konecAktivity->format('G') . ':00',
            'cas'         => [
                'od' => $zacatekAktivity->getTimestamp() * 1000,
                'do' => $konecAktivity->getTimestamp() * 1000,
            ],
            'linie'           => $activity->typ()->nazev(),
            'vBudoucnu'       => $activity->vBudoucnu(),
            'vdalsiVlne'      => $activity->vDalsiVlne(),
            'probehnuta'      => $activity->probehnuta(),
            'jeBrigadnicka'   => $activity->jeBrigadnicka(),
            'prihlasovatelna' => $activity->prihlasovatelna(),
            'tymova'          => $activity->tymova(),
            'dite'            => $activity->detiIds(),
        ];

        return $aktivitaRes;
    }

    public function generatePopisy(int $rok): string
    {
        $aktivity = $this->loadActivities($rok);

        $popisy = [];
        foreach ($aktivity as $aktivita) {
            $popisy[] = [
                'id'    => $aktivita->popisId(),
                'popis' => $aktivita->popis(),
            ];
        }

        return $this->writeJsonFile('popisy', $rok, $popisy);
    }

    public function generateStitky(int $rok): string
    {
        $editorTagu = new EditorTagu($this->systemoveNastaveni->db());
        $tagy = $editorTagu->getTagy();

        $tagyProJson = array_map(
            static function (
                $tag,
            ) {
                return [
                    'id'             => (int) $tag['id'],
                    'nazev'          => $tag['nazev'],
                    'nazevKategorie' => $tag['nazev_kategorie'],
                ];
            },
            $tagy,
        );

        return $this->writeJsonFile('tagy', $rok, $tagyProJson);
    }

    public function generateObsazenosti(int $rok): string
    {
        $aktivity = $this->loadActivities($rok);

        $dataSourcesCollector = new DataSourcesCollector();
        Aktivita::obsazenostObjDSC($dataSourcesCollector);

        $aktivityObsazenost = [];
        foreach ($aktivity as $aktivita) {
            $aktivityObsazenost[] = [
                'idAktivity' => $aktivita->id(),
                'obsazenost' => $aktivita->obsazenostObj($dataSourcesCollector),
            ];
        }

        return $this->writeJsonFile('obsazenosti', $rok, $aktivityObsazenost);
    }

    public function updateManifest(int $rok): void
    {
        $outputDir = $this->outputDir;
        $manifestPath = $this->getManifestPath($rok);

        // Read existing manifest to preserve unchanged filenames
        $manifest = [];
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true)
                ?: [];
        }

        // Find current files for each type
        foreach (ProgramStaticFileType::cases() as $type) {
            $pattern = "{$outputDir}/{$type->value}-{$rok}-*.json";
            $files = glob($pattern);
            if ($files) {
                // Use the most recently modified file
                usort($files, fn (
                    $a,
                    $b,
                ) => filemtime($b) - filemtime($a));
                $manifest[$type->value] = basename($files[0]);
            }
        }

        $tmpPath = "{$outputDir}/.tmp-manifest-{$rok}.json";
        file_put_contents($tmpPath, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        rename($tmpPath, $manifestPath);
    }

    public function regenerateAll(int $rok): void
    {
        $fileLock = new FileLock($this->systemoveNastaveni);
        $fileLock->lock("program-static-{$rok}");
        try {
            // Double-check: another process may have generated while we waited
            if ($this->readManifest($rok) !== null) {
                return;
            }
            $this->generateActivities($rok);
            $this->generatePopisy($rok);
            $this->generateObsazenosti($rok);
            $this->generateStitky($rok);
            $this->updateManifest($rok);
        } finally {
            $fileLock->unlock("program-static-{$rok}");
        }
    }

    public function cleanup(int $rok): void
    {
        $outputDir = $this->outputDir;
        $manifestPath = $this->getManifestPath($rok);

        if (! file_exists($manifestPath)) {
            return;
        }

        $manifest = json_decode(file_get_contents($manifestPath), true)
            ?: [];
        $referencedFiles = array_values($manifest);
        $referencedFiles[] = basename($manifestPath);

        $oneHourAgo = time() - 3600;

        foreach (glob("{$outputDir}/*-{$rok}*.json") as $file) {
            $filename = basename($file);
            if (! in_array($filename, $referencedFiles, true) && filemtime($file) < $oneHourAgo) {
                unlink($file);
            }
        }
    }

    private function getManifestPath(int $rok): string
    {
        return $this->outputDir . "/manifest-{$rok}.json";
    }

    /**
     * @return array{string: string}|null Current manifest or null if not found
     */
    public function readManifest(?int $rok = null): ?array
    {
        $rok = $rok ?? $this->systemoveNastaveni->rocnik();
        $manifestPath = $this->getManifestPath($rok);
        if (! file_exists($manifestPath)) {
            return null;
        }

        return json_decode(file_get_contents($manifestPath), true)
            ?: null;
    }

    public function touchDirtyFlag(
        ProgramStaticFileType $type,
        bool $tryStartWorker = true,
    ): void {
        (new Filesystem())->mkdir($this->dirtyFlagsDir);

        $rocnik = $this->systemoveNastaveni->rocnik();

        touch("{$this->dirtyFlagsDir}/dirty-{$type->value}-{$rocnik}");

        if ($tryStartWorker) {
            $this->tryStartWorker();
        }
    }

    public function hasDirtyFlag(
        ProgramStaticFileType $type,
    ): bool {
        $rocnik = $this->systemoveNastaveni->rocnik();

        return file_exists($this->dirtyFlagsDir . "/dirty-{$type->value}-{$rocnik}");
    }

    public function deleteDirtyFlag(
        ProgramStaticFileType $type,
    ): void {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $path = $this->dirtyFlagsDir . "/dirty-{$type->value}-{$rocnik}";
        if (file_exists($path)) {
            (new Filesystem())->remove($path);
        }
    }

    /**
     * Try to start the background worker if it's not already running.
     * Safe to call from any context — silently ignores failures.
     */
    public function tryStartWorker(): void
    {
        try {
            $backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
            if (! $backgroundProcessService->isProcessRunning(BackgroundProcessService::COMMAND_PROGRAM_STATIC_FILES)) {
                $workerPath = __DIR__ . '/../../admin/scripts/zvlastni/program/_program-static-files-worker.php';
                $rocnik = $this->systemoveNastaveni->rocnik();
                $backgroundProcessService->startBackgroundProcess(
                    BackgroundProcessService::COMMAND_PROGRAM_STATIC_FILES,
                    $workerPath,
                    [
                        'rok' => (string) $rocnik,
                    ],
                );
            }
        } catch (\Throwable $e) {
            // Worker start failure is not critical
        }
    }

    /**
     * @return Aktivita[]
     */
    private function loadActivities(int $rok): array
    {
        if (! array_key_exists($rok, $this->activitiesCache)) {
            $this->activitiesCache[$rok] = Aktivita::zFiltru(
                systemoveNastaveni: $this->systemoveNastaveni,
                filtr: [
                    FiltrAktivity::ROK => $rok,
                ],
                prednacitat: true,
            );
        }

        return $this->activitiesCache[$rok];
    }

    public function reset(): void
    {
        $this->activitiesCache = [];
    }

    private function writeJsonFile(
        string $type,
        int $rok,
        array $data,
    ): string {
        $outputDir = $this->outputDir;
        (new Filesystem())->mkdir($outputDir, 0775);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $hash = md5($json);
        $filename = "{$type}-{$rok}-{$hash}.json";
        $filepath = "{$outputDir}/{$filename}";

        // Skip write if file with same hash already exists
        if (file_exists($filepath)) {
            return $filename;
        }

        $tmpPath = "{$outputDir}/.tmp-{$filename}";
        file_put_contents($tmpPath, $json);
        rename($tmpPath, $filepath);

        return $filename;
    }
}
