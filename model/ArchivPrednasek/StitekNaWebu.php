<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek;

/**
 * A keyword of the archive or a tag of the program; the filter has one option for both of the same name.
 */
final readonly class StitekNaWebu
{
    public const string SKUPINA_KLICOVA_SLOVA = 'Klíčová slova';

    /**
     * @param string $klic    the name without diacritics and case, in the address of the filter (`?stitky=rpg,horor`)
     * @param string $skupina self::SKUPINA_KLICOVA_SLOVA, or the category of the tag of the program
     */
    public function __construct(
        public string $klic,
        public string $nazev,
        public string $skupina,
    ) {
    }

    public static function klic(string $nazev): string
    {
        return slugify($nazev);
    }
}
