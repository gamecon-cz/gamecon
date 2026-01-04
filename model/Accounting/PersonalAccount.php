<?php
declare(strict_types=1);

namespace Gamecon\Accounting;

class PersonalAccount
{
    /** @var array<Transaction> $transactions */
    private array $transactions;

    /**
     * @param array<Transaction> $transactions
     */
    public function __construct(array $transactions)
    {
        $this->transactions = $transactions;
    }

    public function getTransactions(): array
    {
        return $this->transactions;
    }

    /**
     * @param bool $positivePrices if true, prices are formatted as positive values and only behave as negative in "total sum"
     */
    public function formatForHtml(bool $positivePrices = false): string
    {
        function transactionSumReducer(int $carry, Transaction $item): int
        {
            foreach ($item->getSplits() as $split) {
                $carry += $split->getAmount();
            }
            return $carry;
        }

        function categoryToHtml(PersonalAccount $account, TransactionCategory $category, string $categoryName, bool $negatePrice = false): string
        {
            $result = '<tr><td><b>' . $categoryName . '</b></td><td><b>' . ($negatePrice ? -1 : 1) * array_reduce(
                    array_filter($account->getTransactions(), fn($a) => $a->getCategory() == $category),
                    fn($a, $b) => transactionSumReducer($a, $b),
                    0) . '</b></td></tr>';
            foreach ($account->getTransactions() as $transaction) {
                if ($transaction->getCategory() == $category) {
                    foreach ($transaction->getSplits() as $split) {
                        $result = $result . '<tr><td>' . $split->getDescription() . '</td><td>' . ($negatePrice ? -1 : 1) * $split->getAmount() . '</td></tr>';
                    }
                }
            }

            return $result;
        }

        $result = '<table class="objednavky">';

        $result = $result . categoryToHtml($this, TransactionCategory::ACTIVITY, "Aktivity", $positivePrices);
        $result = $result . categoryToHtml($this, TransactionCategory::FOOD, "Strava", $positivePrices);
        $result = $result . categoryToHtml($this, TransactionCategory::SHOP_ITEMS, "Předměty", $positivePrices);
        $result = $result . categoryToHtml($this, TransactionCategory::ACCOMMODATION, "Ubytování", $positivePrices);

        $result = $result . '<tr><td><b>Celková cena</b></td><td><b>' . -array_reduce(
                array_filter($this->getTransactions(), fn($a) => (
                    $a->getCategory() == TransactionCategory::ACTIVITY ||
                    $a->getCategory() == TransactionCategory::FOOD ||
                    $a->getCategory() == TransactionCategory::SHOP_ITEMS ||
                    $a->getCategory() == TransactionCategory::ACCOMMODATION)),
                fn($a, $b) => transactionSumReducer($a, $b),
                0) . '</b></td></tr>';

        $result = $result . categoryToHtml($this, TransactionCategory::LEFTOVER_FROM_LAST_YEAR, "Zůstatek z minulých let");
        $result = $result . categoryToHtml($this, TransactionCategory::MANUAL_MOVEMENTS, "Připsané platby");

        $result = $result . '<tr><td><b>Stav financí</b></td><td><b>' . array_reduce(
                $this->getTransactions(),
                fn($a, $b) => transactionSumReducer($a, $b),
                0) . '</b></td></tr>';

        $result = $result . '</table>';

        return $result;
    }
}
