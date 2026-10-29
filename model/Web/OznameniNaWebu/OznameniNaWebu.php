<?php

declare(strict_types=1);

namespace Gamecon\Web\OznameniNaWebu;

use Gamecon\Web\StavZobrazeniEnum;

/**
 * An announcement on every page of the public web. Without a start it shows at once, without an end until somebody ends it.
 */
readonly class OznameniNaWebu
{
    public function __construct(
        public int $id,
        public string $text,
        public TypOznameniNaWebuEnum $typ,
        public ?\DateTimeImmutable $zobrazitOd,
        public ?\DateTimeImmutable $zobrazitDo,
        public ?int $zmenil,
        public ?\DateTimeImmutable $zmenenoKdy,
    ) {
    }

    public function getStav(\DateTimeInterface $kdy): StavZobrazeniEnum
    {
        return StavZobrazeniEnum::zObdobi($this->zobrazitOd, $this->zobrazitDo, $kdy);
    }

    /**
     * What a visitor who closed the announcement remembers. It changes with the text or the type,
     * so a visitor who closed the announcement sees it again once it says something else.
     */
    public function getKlicSkryti(): string
    {
        return $this->id . '-' . md5($this->typ->value . "\n" . $this->text);
    }
}
