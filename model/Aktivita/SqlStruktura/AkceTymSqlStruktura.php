<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\SqlStruktura;

class AkceTymSqlStruktura
{
    public const AKCE_TYM_TABULKA            = 'akce_tym';
    public const AKCE_TYM_PRIHLASENI_TABULKA = 'akce_tym_prihlaseni';

    // akce_tym columns
    public const ID         = 'id';
    public const ID_AKCE    = 'id_akce';
    public const KOD        = 'kod';
    public const NAZEV      = 'nazev';
    public const LIMIT      = 'limit';
    public const ID_KAPITAN = 'id_kapitan';
    public const ZALOZEN    = 'zalozen';
    public const VEREJNY    = 'verejny';

    // akce_tym_prihlaseni columns
    public const PRIHLASENI_ID_UZIVATELE = 'id_uzivatele';
    public const PRIHLASENI_ID_TYMU      = 'id_tymu';
}
