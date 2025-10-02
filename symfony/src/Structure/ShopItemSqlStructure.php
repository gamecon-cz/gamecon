<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\ShopItem
 * SQL table `shop_predmety`
 */
class ShopItemSqlStructure
{
    public const ID = 'id_predmetu';
    public const NAZEV = 'nazev';
    public const KOD_PREDMETU = 'kod_predmetu';
    public const MODEL_ROK = 'model_rok';
    public const CENA_AKTUALNI = 'cena_aktualni';
    public const STAV = 'stav';
    public const NABIZET_DO = 'nabizet_do';
    public const KUSU_VYROBENO = 'kusu_vyrobeno';
    public const TYP = 'typ';
    public const UBYTOVANI_DEN = 'ubytovani_den';
    public const POPIS = 'popis';
    public const JE_LETOSNI_HLAVNI = 'je_letosni_hlavni';
}
