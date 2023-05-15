<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni\SqlStruktura;

class SystemoveNastaveniSqlStruktura
{
    public const SYSTEMOVE_NASTAVENI_TABULKA = 'systemove_nastaveni';

    public const ID_NASTAVENI     = 'id_nastaveni';
    public const KLIC             = 'klic';
    public const HODNOTA          = 'hodnota';
    public const VLASTNI          = 'vlastni';
    public const DATOVY_TYP       = 'datovy_typ';
    public const NAZEV            = 'nazev';
    public const POPIS            = 'popis';
    public const ZMENA_KDY        = 'zmena_kdy';
    public const SKUPINA          = 'skupina';
    public const PORADI           = 'poradi';
    public const POUZE_PRO_CTENI  = 'pouze_pro_cteni';
    public const ROCNIK_NASTAVENI = 'rocnik_nastaveni';

    public static function sloupce(): array
    {
        return [
            self::ID_NASTAVENI,
            self::KLIC,
            self::HODNOTA,
            self::VLASTNI,
            self::DATOVY_TYP,
            self::NAZEV,
            self::POPIS,
            self::ZMENA_KDY,
            self::SKUPINA,
            self::PORADI,
            self::POUZE_PRO_CTENI,
            self::ROCNIK_NASTAVENI,
        ];
    }

}
