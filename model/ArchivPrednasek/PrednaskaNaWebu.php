<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

/**
 * A lecture as the public archive shows it. Annotation and tags come from the paired activity, a lecture without one
 * has none.
 */
final readonly class PrednaskaNaWebu
{
    /**
     * @param list<PrednasejiciNaWebu> $prednasejici  in their order
     * @param list<StitekNaWebu>       $stitky        keywords first, then tags of the program, without duplicates by name
     * @param string|null              $kratkaAnotace HTML as the program stores it (the line page prints it as it is)
     * @param string|null              $dlouhaAnotace HTML from Markdown, as on the line page
     * @param string|null              $urlRoku       the year on the line page of lectures, only for a year its switcher offers
     * @param string|null              $urlProgramu   the card on the page of its line in that year
     */
    public function __construct(
        public int $id,
        public string $nazev,
        public int $rok,
        public ?string $youtubeId,
        public array $prednasejici,
        public array $stitky,
        public ?string $kratkaAnotace,
        public ?string $dlouhaAnotace,
        public ?string $urlRoku,
        public ?string $urlProgramu,
    ) {
    }

    public function getHtmlId(): string
    {
        return 'prednaska-' . $this->id;
    }
}
