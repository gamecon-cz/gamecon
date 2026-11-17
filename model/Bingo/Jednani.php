<?php

declare(strict_types=1);

namespace Gamecon\Bingo;

/**
 * Termín jednání rady, jedna soutěž v Bingu.
 */
final readonly class Jednani
{
    /**
     * @param \DateTimeImmutable|null $spusteno kdy soutěž někdo spustil ručně, jinak začne sama v 19:00
     */
    public function __construct(
        public int $id,
        public \DateTimeImmutable $datum,
        public ?\DateTimeImmutable $spusteno = null,
    ) {
    }
}
