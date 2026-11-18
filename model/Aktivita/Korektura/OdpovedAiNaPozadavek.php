<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * The AI's answer to one request of a batch: its text, or why there is none.
 */
readonly class OdpovedAiNaPozadavek
{
    private function __construct(
        public ?string $text,
        public ?string $chyba,
    ) {
    }

    public static function text(string $text): self
    {
        return new self($text, null);
    }

    public static function chyba(string $chyba): self
    {
        return new self(null, $chyba);
    }
}
