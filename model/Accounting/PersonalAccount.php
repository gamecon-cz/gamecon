<?php

declare(strict_types=1);

namespace Gamecon\Accounting;

use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Uzivatel\Finance;

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
    public function getTotal(): float
    {
        return Finance::zaokouhli(array_sum(array_map(fn (Transaction $transaction) => $transaction->getTotalAmount(), $this->transactions)));
    }

    /**
     * Přehled jako HTML tabulka. Názvy skupin a částky jsou v jazyce požadavku (v adminu česky), popisy položek
     * jsou HTML z financí (@see Finance::dejPolozkyProBfgr()).
     *
     * @param bool $positivePrices if true, prices are formatted as positive values and only behave as negative in "total sum"
     */
    public function formatForHtml(bool $positivePrices = false): string
    {
        $result = '<table class="objednavky">';

        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::ACTIVITY, t('finance.prehled.aktivity'), $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::FOOD, t('finance.prehled.strava'), $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::SHOP_ITEMS, t('finance.prehled.predmety'), $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::ACCOMMODATION, t('finance.prehled.ubytovani'), $positivePrices);
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::VOLUNTARY_DONATION, t('finance.prehled.dobrovolne_vstupne'), $positivePrices);

        $result = $result . '<tr><td><b>' . htmlspecialchars(t('finance.prehled.celkova_cena')) . '</b></td><td><b>' . $this->formatujCastku(-array_reduce(
            array_filter($this->getTransactions(), fn (Transaction $a) => (
                $a->getCategory() === TransactionCategoryEnum::ACTIVITY
                || $a->getCategory() === TransactionCategoryEnum::FOOD
                || $a->getCategory() === TransactionCategoryEnum::SHOP_ITEMS
                || $a->getCategory() === TransactionCategoryEnum::ACCOMMODATION
                || $a->getCategory() === TransactionCategoryEnum::VOLUNTARY_DONATION)),
            fn ($a, $b) => $this->transactionSumReducer($a, $b),
            0.0)) . '</b></td></tr>';

        // položka zůstatku má stejný název jako skupina (stejný klíč), a tak se pod ní nevypíše znovu
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::LEFTOVER_FROM_LAST_YEAR, t('finance.prehled.zustatek_z_minulych_let'));
        $result = $result . $this->categoryToHtml($this, TransactionCategoryEnum::MANUAL_MOVEMENTS, t('finance.prehled.pripsane_platby'));

        $result = $result . '<tr><td><b>' . htmlspecialchars(t('finance.prehled.stav_financi')) . '</b></td><td><b>' . $this->formatujCastku(array_reduce(
            $this->getTransactions(),
            fn ($a, $b) => $this->transactionSumReducer($a, $b),
            0.0)) . '</b></td></tr>';

        return $result . '</table>';
    }

    private function formatujCastku(float $castka): string
    {
        return Finance::formatujCastku($castka, AktualniJazyk::jazyk());
    }

    private function transactionSumReducer(float $carry, Transaction $item): float
    {
        foreach ($item->getSplits() as $split) {
            $carry += $split->getAmount();
        }

        return Finance::zaokouhli($carry);
    }

    /**
     * @param string $categoryName prostý text
     */
    private function categoryToHtml(self $account, TransactionCategoryEnum $category, string $categoryName, bool $negatePrice = false): string
    {
        $groupedSplits = $this->groupedSplitsByCategory($account, $category, $categoryName);
        $categoryTotal = $this->totalForCategory($account, $category);

        $result = '<tr><td><b>' . htmlspecialchars($categoryName) . '</b></td><td><b>'
            . $this->formatujCastku(($negatePrice ? -1 : 1) * $categoryTotal)
            . '</b></td></tr>';

        foreach ($groupedSplits as $groupedSplit) {
            $description = $groupedSplit['description'];
            if ($groupedSplit['count'] > 1) {
                $description .= ' ' . $groupedSplit['count'] . '×';
            }
            $result = $result . '<tr class="objednavky--polozka"><td>' . $description . '</td><td>'
                . $this->formatujCastku(($negatePrice ? -1 : 1) * $groupedSplit['amount'])
                . '</td></tr>';
        }

        return $result;
    }

    private function totalForCategory(self $account, TransactionCategoryEnum $category): float
    {
        $total = 0.0;
        foreach ($account->getTransactions() as $transaction) {
            if ($transaction->getCategory() !== $category) {
                continue;
            }
            foreach ($transaction->getSplits() as $split) {
                $total += $split->getAmount();
            }
        }

        return Finance::zaokouhli($total);
    }

    /**
     * @return array<int, array{description: string, amount: float, count: int}>
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
                        'amount'      => 0.0,
                        'count'       => 0,
                    ];
                }
                $groupedSplits[$key]['amount'] = Finance::zaokouhli($groupedSplits[$key]['amount'] + $split->getAmount());
                ++$groupedSplits[$key]['count'];
            }
        }

        return array_values($groupedSplits);
    }
}
