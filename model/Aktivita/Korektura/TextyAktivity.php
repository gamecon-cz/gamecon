<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura;

/**
 * The three texts of an activity that go to proofreading.
 */
readonly class TextyAktivity
{
    public const MAX_DELKA_NAZVU = 255;
    public const MAX_DELKA_POPISU_KRATKEHO = 255;

    public function __construct(
        public string $nazev,
        public string $popisKratky,
        public string $popis,
    ) {
    }

    /**
     * Trimmed and with Unix line ends, so that whitespace the AI or a browser adds
     * does not count as a change.
     */
    public static function normalizuj(string $text): string
    {
        return trim(str_replace("\r\n", "\n", $text));
    }

    public function normalizovane(): self
    {
        return new self(
            self::normalizuj($this->nazev),
            self::normalizuj($this->popisKratky),
            self::normalizuj($this->popis),
        );
    }

    public function jeStejnyJako(self $jine): bool
    {
        $tyto = $this->normalizovane();
        $jine = $jine->normalizovane();

        return $tyto->nazev === $jine->nazev
            && $tyto->popisKratky === $jine->popisKratky
            && $tyto->popis === $jine->popis;
    }
}
