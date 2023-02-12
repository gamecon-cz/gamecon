<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

class AkcePrihlaseniLogSqlStruktura
{
    public const AKCE_PRIHLASENI_LOG_TABULKA = 'akce_prihlaseni_log';

    public const ID_LOG       = 'id_log';
    public const ID_AKCE      = 'id_akce';
    public const ID_UZIVATELE = 'id_uzivatele';
    public const KDY          = 'kdy';
    public const TYP          = 'typ';
    public const ID_ZMENIL    = 'id_zmenil';
}
