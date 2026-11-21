<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek\Import;

/**
 * One lecture of the spreadsheet after unification.
 */
final readonly class RadekArcitabulky
{
    /**
     * @param list<string> $prednasejici unified names in the order of the spreadsheet, empty when unknown ("x")
     * @param list<string> $klicovaSlova unified keywords without duplicates
     */
    public function __construct(
        public int $cisloRadku,
        public string $puvodniNazev,
        public string $nazev,
        public int $rok,
        public array $prednasejici,
        public array $klicovaSlova,
        public ?string $youtubeId,
    ) {
    }
}
