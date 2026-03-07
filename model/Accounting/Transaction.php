<?php

declare(strict_types=1);

namespace Gamecon\Accounting;

use Gamecon\Cas\DateTimeGamecon;

readonly class Transaction
{
    /**
     * @param TransactionSplit[] $splits
     */
    public function __construct(
        private TransactionCategory $category,
        private DateTimeGamecon $date,
        private string $description,
        private array $splits,
        private string $id,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCategory(): TransactionCategory
    {
        return $this->category;
    }

    public function getDate(): DateTimeGamecon
    {
        return $this->date;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return TransactionSplit[]
     */
    public function getSplits(): array
    {
        return $this->splits;
    }

    public function getTotalAmount(): int
    {
        return array_sum(array_map(fn (TransactionSplit $split) => $split->getAmount(), $this->splits));
    }
}
