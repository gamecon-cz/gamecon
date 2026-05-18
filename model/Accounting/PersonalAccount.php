<?php

declare(strict_types=1);

namespace Gamecon\Accounting;

readonly class PersonalAccount
{
    /**
     * @param array<Transaction> $transactions
     */
    public function __construct(
        private array $transactions,
    ) {
    }

    /**
     * @return array<Transaction>
     */
    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**
     * Returns total monetary sum of all transactions.
     */
    public function getTotal(): int
    {
        return array_sum(array_map(fn (Transaction $transaction) => $transaction->getTotalAmount(), $this->transactions));
    }

    /**
     * @param bool $positivePrices if true, prices are formatted as positive values and only behave as negative in "total sum"
     */
    public function formatForHtml(bool $positivePrices = false): string
    {
        $result = '<table class="objednavky">';

        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::ACTIVITY, 'Aktivity', $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::FOOD, 'Strava', $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::SHOP_ITEMS, 'Předměty', $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::ACCOMMODATION, 'Ubytování', $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::VOLUNTARY_DONATION, 'Dobrovolné vstupné', $positivePrices);

        $result = $result . '<tr><td><b>Celková cena</b></td><td><b>' . -array_reduce(
            array_filter($this->getTransactions(), fn (Transaction $a) => (
                $a->getCategory() === TransactionCategoryEnum::ACTIVITY
                || $a->getCategory() === TransactionCategoryEnum::FOOD
                || $a->getCategory() === TransactionCategoryEnum::SHOP_ITEMS
                || $a->getCategory() === TransactionCategoryEnum::ACCOMMODATION
                || $a->getCategory() === TransactionCategoryEnum::VOLUNTARY_DONATION)),
            fn ($a, $b) => $this->transactionSumReducer($a, $b),
            0) . '</b></td></tr>';

        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::LEFTOVER_FROM_LAST_YEAR, 'Zůstatek z minulých let');
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::MANUAL_MOVEMENTS, 'Připsané platby');

        $result = $result . '<tr><td><b>Stav financí</b></td><td><b>' . array_reduce(
            $this->getTransactions(),
            fn ($a, $b) => $this->transactionSumReducer($a, $b),
            0) . '</b></td></tr>';

        return $result . '</table>';
    }

    private function transactionSumReducer(int $carry, Transaction $item): int
    {
        foreach ($item->getSplits() as $split) {
            $carry += $split->getAmount();
        }

        return $carry;
    }

    private function categoryToHtml(self $account, TransactionCategoryEnum $category, string $categoryName, bool $negatePrice = false): string
    {
        $groupedSplits = $this->groupedSplitsByCategory($account, $category, $categoryName);
        $categoryTotal = $this->totalForCategory($account, $category);

        $result = '<tr><td><b>' . $categoryName . '</b></td><td><b>'
            . ($negatePrice ? -1 : 1) * $categoryTotal
            . '</b></td></tr>';

        foreach ($groupedSplits as $groupedSplit) {
            $description = $groupedSplit['description'];
            if ($groupedSplit['count'] > 1) {
                $description .= ' ' . $groupedSplit['count'] . '×';
            }
            $result = $result . '<tr><td>' . $description . '</td><td>'
                . ($negatePrice ? -1 : 1) * $groupedSplit['amount']
                . '</td></tr>';
        }

        return $result;
    }

    private function totalForCategory(self $account, TransactionCategoryEnum $category): int
    {
        $total = 0;
        foreach ($account->getTransactions() as $transaction) {
            if ($transaction->getCategory() !== $category) {
                continue;
            }
            foreach ($transaction->getSplits() as $split) {
                $total += $split->getAmount();
            }
        }

        return $total;
    }

    /**
     * @return array<int, array{description: string, amount: int, count: int}>
     */
    private function groupedSplitsByCategory(self $account, TransactionCategoryEnum $category, string $categoryName): array
    {
        $groupedSplits = [];

        foreach ($account->getTransactions() as $transaction) {
            if ($transaction->getCategory() !== $category) {
                continue;
            }
            foreach ($transaction->getSplits() as $split) {
                if ($split->getDescription() === $categoryName) {
                    continue;
                }
                $key = $split->getDescription() . "\0" . $split->getAmount();
                if (! isset($groupedSplits[$key])) {
                    $groupedSplits[$key] = [
                        'description' => $split->getDescription(),
                        'amount'      => 0,
                        'count'       => 0,
                    ];
                }
                $groupedSplits[$key]['amount'] += $split->getAmount();
                ++$groupedSplits[$key]['count'];
            }
        }

        return array_values($groupedSplits);
    }
}
