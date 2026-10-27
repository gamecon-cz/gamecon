<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\Korektura\SqlStruktura;

class AkceKorekturaSqlStruktura
{
    public const AKCE_KOREKTURA_TABULKA = 'akce_korektura';

    public const ID_KOREKTURY = 'id_korektury';
    public const ID_AKCE = 'id_akce';
    public const BALIK = 'balik';
    public const STAV = 'stav';
    public const PUVODNI_NAZEV = 'puvodni_nazev';
    public const PUVODNI_POPIS_KRATKY = 'puvodni_popis_kratky';
    public const PUVODNI_POPIS = 'puvodni_popis';
    public const NAVRH_NAZEV = 'navrh_nazev';
    public const NAVRH_POPIS_KRATKY = 'navrh_popis_kratky';
    public const NAVRH_POPIS = 'navrh_popis';
    public const ZMENY = 'zmeny';
    public const EXPORTOVAL = 'exportoval';
    public const EXPORTOVANO_KDY = 'exportovano_kdy';
    public const NAVRZENO_KDY = 'navrzeno_kdy';
    public const VYRIDIL = 'vyridil';
    public const VYRIZENO_KDY = 'vyrizeno_kdy';
}
