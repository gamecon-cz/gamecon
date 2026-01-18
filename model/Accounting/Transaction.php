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
    private string $id;

    //region getters
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
        return array_sum(array_map(fn(TransactionSplit $split) => $split->getAmount(), $this->splits));

    }
    //endregion

    /**
     * @param TransactionCategory $category
     * @param DateTimeGamecon $date
     * @param string $description
     * @param TransactionSplit[] $splits
     */
    public function __construct(TransactionCategory $category, DateTimeGamecon $date, string $description, array $splits, string $id)
    {
        $this->category = $category;
        $this->date = $date;
        $this->description = $description;
        $this->splits = $splits;
        $this->id = $id;
    }

}
