<?php

declare(strict_types=1);

namespace Gamecon;

use Gamecon\Accounting\PersonalAccount;
use Gamecon\Accounting\Transaction;
use Gamecon\Accounting\TransactionCategoryEnum;
use Gamecon\Accounting\TransactionSplit;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Exceptions\NeznamyTypPredmetu;
use App\Enum\ProductTagCode;
use Gamecon\Uzivatel\Finance;

class Accounting
{
    public static function getPersonalFinance(\Uzivatel $u, bool $showDiscounts): PersonalAccount
    {
        $transactions = [];
        foreach ($u->finance()->dejPolozkyProBfgr() as $polozkaProBfgr) {
            $splits = [];
            if ($showDiscounts) {
                $splits[] = new TransactionSplit(-($polozkaProBfgr->castka + $polozkaProBfgr->sleva), $polozkaProBfgr->nazev);
                if ($polozkaProBfgr->sleva !== 0.0) {
                    $splits[] = new TransactionSplit($polozkaProBfgr->sleva, 'Sleva z ' . $polozkaProBfgr->nazev);
                }
            } else {
                $splits[] = new TransactionSplit(-$polozkaProBfgr->castka, $polozkaProBfgr->nazev);
            }
            /** @var TransactionCategoryEnum $category */
            $category = null;
            if ($polozkaProBfgr->kategorie !== null) {
                $category = match ($polozkaProBfgr->kategorie) {
                    ProductTagCode::PROPLACENI_BONUSU => TransactionCategoryEnum::MANUAL_MOVEMENTS,
                    ProductTagCode::VSTUPNE           => TransactionCategoryEnum::VOLUNTARY_DONATION,
                    ProductTagCode::TRICKO,
                    ProductTagCode::PREDMET           => TransactionCategoryEnum::SHOP_ITEMS,
                    ProductTagCode::UBYTOVANI         => TransactionCategoryEnum::ACCOMMODATION,
                    ProductTagCode::JIDLO             => TransactionCategoryEnum::FOOD,
                    default                           => throw new NeznamyTypPredmetu(sprintf('Unknown item category %s', $polozkaProBfgr->kategorie->value)),
                };
            } else {
                switch ($polozkaProBfgr->typ) {
                    case Finance::AKTIVITY:
                        $category = TransactionCategoryEnum::ACTIVITY;
                        break;
                    case Finance::PRIPSANE_SLEVY:
                    case Finance::PLATBA:
                    case Finance::ORGSLEVA:
                    case Finance::BRIGADNICKA_ODMENA:
                        $category = TransactionCategoryEnum::MANUAL_MOVEMENTS;
                        break;
                    case Finance::VSTUPNE:
                        $category = TransactionCategoryEnum::VOLUNTARY_DONATION;
                        break;
                    case Finance::ZUSTATEK_Z_PREDCHOZICH_LET:
                        $category = TransactionCategoryEnum::LEFTOVER_FROM_LAST_YEAR;
                        break;
                    case Finance::CELKOVA:
                    case Finance::VYSLEDNY:
                    case Finance::KATEGORIE_NEPLATICE:
                    case Finance::PLATBY_NADPIS:
                        continue 2;
                }
            }
            if ($category === null) {
                continue;
            }
            $transactions[] = new Transaction(
                category: $category,
                date: DateTimeGamecon::zacatekGameconu(),
                description: $polozkaProBfgr->nazev,
                splits: $splits,
                id: '#U[' . $u->id() . ']#V[' . $polozkaProBfgr->idVarianty . ']');
        }

        return new PersonalAccount($transactions);
    }

    public static function cancelTransaction(string $transactionId): bool
    {
        if (preg_match('/#U\[(\d+)]#V\[(\d+)]/', $transactionId, $matches) !== 1) {
            return false;
        }

        return \Uzivatel::zId(intval($matches[1]))->shop()->zrusNakupVarianty(intval($matches[2]), 1) > 0;
    }
}
