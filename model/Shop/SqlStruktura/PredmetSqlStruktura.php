<?php

declare(strict_types=1);

namespace Gamecon\Shop\SqlStruktura;

class PredmetSqlStruktura
{
    public const SHOP_PREDMETY_TABULKA = 'shop_predmety';

    public const ID_PREDMETU        = 'id_predmetu';
    public const NAZEV              = 'nazev';
    public const KOD_PREDMETU       = 'kod_predmetu';
    public const CENA_AKTUALNI      = 'cena_aktualni';
    public const STAV               = 'stav';
    public const NABIZET_DO         = 'nabizet_do';
    public const KUSU_VYROBENO      = 'kusu_vyrobeno';
    public const UBYTOVANI_DEN      = 'ubytovani_den';
    public const POPIS              = 'popis';
    public const VEDLEJSI           = 'vedlejsi';
    public const ARCHIVED_AT        = 'archived_at';
    public const RESERVED_FOR_ORGANIZERS = 'reserved_for_organizers';

    // Virtual columns from shop_predmety_s_typem view (not on the base table, but used by legacy code)
    public const MODEL_ROK         = 'model_rok';
    public const TYP               = 'typ';
    public const JE_LETOSNI_HLAVNI = 'je_letosni_hlavni';

    public const SHOP_PREDMETY_S_TYPEM_TABULKA = 'shop_predmety_s_typem';
}
