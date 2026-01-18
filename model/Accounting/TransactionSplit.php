<?php

namespace Gamecon\Accounting;

class TransactionSplit
{
    /**
     * Positive amount means INCREASE in USER's balance, negative decrease
     */
    private int $amount;
    private string $description;

    /**
     * @param int $amount
     * @param string $description
     */
    public function __construct(int $amount, string $description)
    {
        $this->amount = $amount;
        $this->description = $description;
    }

    //region Getters
    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
    //endregion
}
