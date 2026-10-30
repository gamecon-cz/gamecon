<?php

declare(strict_types=1);

namespace Gamecon\Web\Motiv;

use Gamecon\Web\StavZobrazeniEnum;

readonly class NaplanovanyMotivWebu
{
    public function __construct(
        public int $id,
        public MotivWebuEnum $motiv,
        public \DateTimeImmutable $zobrazitOd,
        public \DateTimeImmutable $zobrazitDo,
        public ?int $zmenil,
        public ?\DateTimeImmutable $zmenenoKdy,
    ) {
    }

    public function getStav(\DateTimeInterface $kdy): StavZobrazeniEnum
    {
        return StavZobrazeniEnum::zObdobi($this->zobrazitOd, $this->zobrazitDo, $kdy);
    }
}
