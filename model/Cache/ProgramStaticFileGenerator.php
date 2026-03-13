<?php

declare(strict_types=1);

namespace Gamecon\Cache;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\BackgroundProcess\BackgroundProcessService;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Symfony\Component\Filesystem\Filesystem;

class ProgramStaticFileGenerator
{
    private readonly string $outputDir;
    private readonly string $dirtyFlagsDir;

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
        $this->outputDir = $systemoveNastaveni->publicCacheDir() . '/program';
        $this->dirtyFlagsDir = $systemoveNastaveni->privateCacheDir() . '/program';
    }

    public function generateAktivity(int $rok): string
    {
        $aktivity = $this->loadAktivity($rok);

        $dataSourcesCollector = new DataSourcesCollector();
        Aktivita::organizatoriDSC($dataSourcesCollector);

        $aktivityNeprihlasen = [];
        foreach ($aktivity as $aktivita) {
            $zacatekAktivity = $aktivita->zacatek();
            $konecAktivity = $aktivita->konec();

            if (! $zacatekAktivity || ! $konecAktivity || ! $aktivita->viditelnaPro(null)) {
                continue;
            }

            $vypraveci = array_map(
                fn (
                    \Uzivatel $organizator,
                ) => $organizator->jmenoNick(),
                $aktivita->organizatori(dataSourcesCollector: $dataSourcesCollector),
            );

            $stitkyId = $aktivita->tagyId();

            $aktivitaRes = [
                'id'          => $aktivita->id(),
                'nazev'       => $aktivita->nazev(),
                'kratkyPopis' => $aktivita->kratkyPopis(),
                'popisId'     => $aktivita->popisId(),
                'obrazek'     => (string) $aktivita->obrazek(),
                'vypraveci'   => $vypraveci,
                'stitkyId'    => $stitkyId,
                'cenaZaklad'  => intval($aktivita->cenaZaklad()),
                'casText'     => $zacatekAktivity
                    ? $zacatekAktivity->format('G') . ':00&ndash;' . $konecAktivity->format('G') . ':00'
                    : '',
                'cas' => [
                    'od' => $zacatekAktivity->getTimestamp() * 1000,
                    'do' => $konecAktivity->getTimestamp() * 1000,
                ],
                'linie'         => $aktivita->typ()->nazev(),
                'vBudoucnu'     => $aktivita->vBudoucnu(),
                'vdalsiVlne'    => $aktivita->vDalsiVlne(),
                'probehnuta'    => $aktivita->probehnuta(),
                'jeBrigadnicka' => $aktivita->jeBrigadnicka(),
            ];

            $aktivitaRes['prihlasovatelna'] = $aktivita->prihlasovatelna();
            $aktivitaRes['tymova'] = $aktivita->tymova();

            $dite = $aktivita->detiIds();
            if ($dite && count($dite)) {
                $aktivitaRes['dite'] = $dite;
            }

            $aktivitaRes = array_filter($aktivitaRes);
            $aktivityNeprihlasen[] = $aktivitaRes;
        }

        return $this->writeJsonFile('aktivity', $rok, $aktivityNeprihlasen);
    }

    public function generatePopisy(int $rok): string
    {
        $aktivity = $this->loadAktivity($rok);

        $popisy = [];
        foreach ($aktivity as $aktivita) {
            $popisy[] = [
                'id'    => $aktivita->popisId(),
                'popis' => $aktivita->popis(),
            ];
        }

        return $this->writeJsonFile('popisy', $rok, $popisy);
    }

    public function generateObsazenosti(int $rok): string
    {
        $aktivity = $this->loadAktivity($rok);

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
        $manifestPath = "{$outputDir}/manifest-{$rok}.json";

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
        $this->generateAktivity($rok);
        $this->generatePopisy($rok);
        $this->generateObsazenosti($rok);
        $this->updateManifest($rok);
    }

    public function cleanup(int $rok): void
    {
        $outputDir = $this->outputDir;
        $manifestPath = "{$outputDir}/manifest-{$rok}.json";

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

    /**
     * @return array{string: string}|null Current manifest or null if not found
     */
    public function readManifest(): ?array
    {
        $manifestPath = $this->outputDir . "/manifest-{$this->systemoveNastaveni->rocnik()}.json";
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
    private function loadAktivity(int $rok): array
    {
        return Aktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: [
                FiltrAktivity::ROK => $rok,
            ],
            prednacitat: true,
        );
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
