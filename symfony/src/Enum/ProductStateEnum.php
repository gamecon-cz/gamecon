<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Values of shop_predmety.stav. There is no lookup table and no foreign key, so the set
 * is closed — nothing outside the code can add a state.
 *
 * The column comment still names the original Czech states ('0-mimo, 1-veřejný,
 * 2-podpultový, 3-pozastavený'); the cases here are named for what the shop actually
 * does with each value, which has drifted from those labels.
 *
 * Supersedes Gamecon\Shop\StavPredmetu.
 */
enum ProductStateEnum: int
{
    /**
     * Off the catalogue. The shop's query is `stav > RETIRED OR` an existing purchase, so
     * it is gone for everyone except the people who already bought one, whose orders and
     * reports keep working.
     */
    case RETIRED = 0;

    /** The only state that is on sale by default. */
    case PUBLIC = 1;

    /**
     * On sale only to customers holding the right Pravo — blue and red t-shirts. The entry
     * fee reuses the value for a second meaning: the payment is not late yet.
     */
    case RESTRICTED = 2;

    /**
     * Sale is locked, but the product stays listed and stays visible to those who already
     * bought it. A product whose `nabizet_do` has passed is treated as this, and the
     * `jidloBezZamku` / `ubytovaniBezZamku` settings unlock it again.
     */
    case SUSPENDED = 3;
}
