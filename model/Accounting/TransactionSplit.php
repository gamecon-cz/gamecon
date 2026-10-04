<?php

declare(strict_types=1);

namespace Gamecon\Accounting;

class TransactionSplit
{
    /**
     * Positive amount means INCREASE in USER's balance, negative decrease.
     * Float, because payments arrive from the bank in hellers.
     */
    private float $amount;
    private string $description;

    public function __construct(float $amount, string $description)
    {
        $this->amount = $amount;
        $this->description = $description;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
