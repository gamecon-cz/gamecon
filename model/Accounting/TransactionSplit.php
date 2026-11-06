<?php

declare(strict_types=1);

namespace Gamecon\Accounting;

use Gamecon\Uzivatel\Finance;

class TransactionSplit
{
    /**
     * Positive amount means INCREASE in USER's balance, negative decrease
     */
    private float $amount;
    private string $description;

    public function __construct(float $amount, string $description)
    {
        $this->amount = Finance::zaokouhli($amount);
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
