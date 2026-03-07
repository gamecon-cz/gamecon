<?php

declare(strict_types=1);

namespace Gamecon\Accounting;

class TransactionSplit
{
    /**
     * Positive amount means INCREASE in USER's balance, negative decrease
     */
    private int $amount;
    private string $description;

    public function __construct(int $amount, string $description)
    {
        $this->amount = $amount;
        $this->description = $description;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
