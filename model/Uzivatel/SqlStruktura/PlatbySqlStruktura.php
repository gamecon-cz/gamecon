<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\SqlStruktura;

class PlatbySqlStruktura
{
    public const PLATBY_TABULKA = 'platby';

    public const ID                     = 'id';
    public const ID_UZIVATELE           = 'id_uzivatele';
    public const FIO_ID                 = 'fio_id';
    public const VS                     = 'vs';
    public const CASTKA                 = 'castka';
    public const ROK                    = 'rok';
    public const PRIPSANO_NA_UCET_BANKY = 'pripsano_na_ucet_banky';
    public const PROVEDENO              = 'provedeno';
    public const PROVEDL                = 'provedl';
    public const NAZEV_PROTIUCTU        = 'nazev_protiuctu';
    public const CISLO_PROTIUCTU        = 'cislo_protiuctu';
    public const KOD_BANKY_PROTIUCTU    = 'kod_banky_protiuctu';
    public const NAZEV_BANKY_PROTIUCTU  = 'nazev_banky_protiuctu';
    public const POZNAMKA               = 'poznamka';
    public const SKRYTA_POZNAMKA        = 'skryta_poznamka';

}
