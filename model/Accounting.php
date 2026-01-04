<?php

namespace Gamecon;

use Gamecon\Accounting\PersonalAccount;
use Gamecon\Accounting\Transaction;
use Gamecon\Accounting\TransactionCategory;
use Gamecon\Accounting\TransactionSplit;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Exceptions\NeznamyTypPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Uzivatel\Finance;
use Uzivatel;

class Accounting
{
    public static function getPersonalFinance(Uzivatel $u): PersonalAccount
    {
        $transactions = [];
        foreach ($u->finance()->dejPolozkyProBfgr() as $polozkaProBfgr) {
            $splits = [];
            $splits[] = new TransactionSplit(-($polozkaProBfgr->castka + $polozkaProBfgr->sleva), $polozkaProBfgr->nazev);
            if ($polozkaProBfgr->sleva != 0) {
                $splits[] = new TransactionSplit($polozkaProBfgr->sleva, 'Sleva z ' . $polozkaProBfgr->nazev);
            }
            /** @var TransactionCategory $category */
            $category = null;
            switch ($polozkaProBfgr->typ) {
                case Finance::AKTIVITY:
                    $category = TransactionCategory::ACTIVITY;
                    break;
                case TypPredmetu::PROPLACENI_BONUSU:
                case Finance::PRIPSANE_SLEVY:
                case TypPredmetu::VSTUPNE:
                case Finance::VSTUPNE:
                case Finance::PLATBA:
                case Finance::ORGSLEVA:
                case Finance::BRIGADNICKA_ODMENA:
                    $category = TransactionCategory::MANUAL_MOVEMENTS;
                    break;
                case TypPredmetu::TRICKO:
                case TypPredmetu::PREDMET:
                    $category = TransactionCategory::SHOP_ITEMS;
                    break;
                case TypPredmetu::UBYTOVANI:
                    $category = TransactionCategory::ACCOMMODATION;
                    break;
                case TypPredmetu::JIDLO:
                    $category = TransactionCategory::FOOD;
                    break;
                case TypPredmetu::PARCON:
                    throw new NeznamyTypPredmetu;
                    break;
                case Finance::ZUSTATEK_Z_PREDCHOZICH_LET:
                    $category = TransactionCategory::LEFTOVER_FROM_LAST_YEAR;
                    break;
                case Finance::CELKOVA:
                case Finance::VYSLEDNY:
                case Finance::KATEGORIE_NEPLATICE:
                case Finance::PLATBY_NADPIS:
                    continue 2;
            }
            if ($category == null) {
                continue;
            }
            $transactions[] = new Transaction($category, DateTimeGamecon::zacatekGameconu(), $polozkaProBfgr->nazev, $splits);
        }
        return new PersonalAccount($transactions);
    }
}
