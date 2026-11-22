<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Ai;

use Gamecon\Ai\DavkyAi;
use Gamecon\Ai\Exceptions\ChybaApiAi;
use Gamecon\Ai\OdpovedAiNaPozadavek;
use Gamecon\Ai\PoskytovatelAi;

/**
 * Batches that stay in memory: a test sees what went out and decides when and what the AI answers.
 */
class FalesneDavkyAi implements DavkyAi
{
    /**
     * @var array<string, array{pokyny: string, zpravy: array<string, string>}> by batch ID
     */
    public array $odeslane = [];

    /**
     * @var array<string> IDs of the cancelled batches
     */
    public array $zrusene = [];

    /**
     * @var array<string> IDs of the batches asked for their answers, in order
     */
    public array $dotazy = [];

    /**
     * @var array<string, array<string, OdpovedAiNaPozadavek>> by batch ID, a missing batch is running
     */
    private array $odpovedi = [];

    /**
     * @var array<string, ChybaApiAi> by batch ID, what a question about the batch ends with
     */
    private array $chyby = [];

    public function __construct(
        private readonly PoskytovatelAi $poskytovatel = PoskytovatelAi::ANTHROPIC,
    ) {
    }

    public function poskytovatel(): PoskytovatelAi
    {
        return $this->poskytovatel;
    }

    public function overSpojeni(): void
    {
    }

    public function odesli(string $pokyny, array $zpravy): string
    {
        $idDavky = "{$this->poskytovatel->value}_batch_test_" . (count($this->odeslane) + 1);
        $this->odeslane[$idDavky] = [
            'pokyny' => $pokyny,
            'zpravy' => $zpravy,
        ];

        return $idDavky;
    }

    public function dejOdpovedi(string $idDavky): ?array
    {
        $this->dotazy[] = $idDavky;
        if (isset($this->chyby[$idDavky])) {
            throw $this->chyby[$idDavky];
        }

        return $this->odpovedi[$idDavky] ?? null;
    }

    public function zrus(string $idDavky): void
    {
        $this->zrusene[] = $idDavky;
    }

    /**
     * Ends the batch with an answer to each of its requests.
     *
     * @param callable(int $idAktivity): OdpovedAiNaPozadavek $odpovedNaAktivitu
     */
    public function dokonci(string $idDavky, callable $odpovedNaAktivitu): void
    {
        $this->odpovedi[$idDavky] = [];
        foreach ($this->odeslane[$idDavky]['zpravy'] as $idPozadavku => $zprava) {
            preg_match('~<gc-aktivita id="(\d+)">~', $zprava, $shoda);
            $this->odpovedi[$idDavky][$idPozadavku] = $odpovedNaAktivitu((int) $shoda[1]);
        }
    }

    /**
     * Every question about the batch ends with the error, until null is given.
     */
    public function selze(string $idDavky, ?ChybaApiAi $chyba): void
    {
        if ($chyba === null) {
            unset($this->chyby[$idDavky]);

            return;
        }
        $this->chyby[$idDavky] = $chyba;
    }

    public function posledniDavka(): string
    {
        return (string) array_key_last($this->odeslane);
    }

    /**
     * @return array<int, string> the message of each activity by its ID
     */
    public function zpravyPodleAktivit(string $idDavky): array
    {
        $zpravy = [];
        foreach ($this->odeslane[$idDavky]['zpravy'] as $zprava) {
            preg_match('~<gc-aktivita id="(\d+)">~', $zprava, $shoda);
            $zpravy[(int) $shoda[1]] = $zprava;
        }

        return $zpravy;
    }
}
