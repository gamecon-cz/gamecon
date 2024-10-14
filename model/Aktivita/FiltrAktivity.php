<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

class FiltrAktivity
{
    public const string ROK = 'rok';
    public const string NAZEV_AKCE = 'nazev_akce';
    public const string TYP = 'typ'; // id typu aktivity
    public const string ORGANIZATOR = 'organizator'; // id organizatora
    public const string JEN_VIDITELNE = 'jenViditelne';
    public const string JEN_ZAMCENE = 'jenZamcene';
    public const string JEN_NEUZAVRENE = 'jenNeuzavrene';
    public const string JEN_NEVYPLNENE = 'jenNevyplnene';
    public const string OD = 'od'; // datum (a čas) v SQL formátu YYYY-MM-DD HH:mm:ss
    public const string DO = 'do'; // datum (a čas) v SQL formátu YYYY-MM-DD HH:mm:ss
    public const string STAV = 'stav'; // id stavu, array nebo int
    public const string BEZ_DALSICH_KOL = 'bezDalsichKol';
    public const string PRIHLASENI = 'prihlaseni'; // id uživatelů, array nebo int
    public const string JEN_VOLNE = 'jenVolne';
}
