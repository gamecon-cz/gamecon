<?php
declare(strict_types=1);

namespace Gamecon\Accounting;

use Gamecon\Cas\DateTimeGamecon;

class Transaction
{
    private TransactionCategory $category;
    private DateTimeGamecon $date;
    private string $description;
    /** @var TransactionSplit[] $splits */
    private array $splits;

    //region getters
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

    public function getSplits(): array
    {
        return $this->splits;
    }
    //endregion

    /**
     * @param TransactionCategory $category
     * @param DateTimeGamecon $date
     * @param string $description
     * @param TransactionSplit[] $splits
     */
    public function __construct(TransactionCategory $category, DateTimeGamecon $date, string $description, array $splits)
    {
        $this->category = $category;
        $this->date = $date;
        $this->description = $description;
        $this->splits = $splits;
    }

}
