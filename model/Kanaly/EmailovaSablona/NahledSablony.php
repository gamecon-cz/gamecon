<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

/**
 * What the editor shows as a preview of the text being typed: whatever is filled in is rendered even when the other
 * part is still blank, which is marked instead of hiding the whole preview.
 */
class NahledSablony
{
    public const NEVYPLNENO = '(nevyplněno)';

    public function __construct(
        private readonly EmailovaSablonaVykreslovac $vykreslovac,
    ) {
    }

    /**
     * @param array<string, string|int|float|\Stringable> $promenne exactly those the definition declares
     */
    public function vykresli(
        EmailovaSablonaDefinice $definice,
        string                  $predmet,
        string                  $text,
        array                   $promenne,
    ): VykreslenaSablona {
        $vykresleno = $this->vykreslovac->vykresli($definice, $predmet, $text, $promenne);

        return new VykreslenaSablona(
            predmet: RozkladSymbolu::jePrazdny($predmet)
                ? self::NEVYPLNENO
                : $vykresleno->predmet,
            text: RozkladSymbolu::jePrazdny($text)
                ? self::NEVYPLNENO
                : $vykresleno->text,
            format: $vykresleno->format,
        );
    }
}
