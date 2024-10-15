<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

class FiltrAktivity
{
    public const ROK = 'rok';
    public const NAZEV_AKCE = 'nazev_akce';
    public const TYP = 'typ'; // id typu aktivity
    public const ORGANIZATOR = 'organizator'; // id organizatora
    public const JEN_VIDITELNE = 'jenViditelne';
    public const JEN_ZAMCENE = 'jenZamcene';
    public const JEN_NEUZAVRENE = 'jenNeuzavrene';
    public const JEN_NEVYPLNENE = 'jenNevyplnene';
    public const OD = 'od'; // datum (a čas) v SQL formátu YYYY-MM-DD HH:mm:ss
    public const DO = 'do'; // datum (a čas) v SQL formátu YYYY-MM-DD HH:mm:ss
    public const STAV = 'stav'; // id stavu, array nebo int
    public const BEZ_DALSICH_KOL = 'bezDalsichKol';
    public const PRIHLASENI = 'prihlaseni'; // id uživatelů, array nebo int
    public const JEN_VOLNE = 'jenVolne';
}
