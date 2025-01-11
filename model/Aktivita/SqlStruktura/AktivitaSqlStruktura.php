<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\SqlStruktura;

class AktivitaSqlStruktura
{
    public const AKCE_SEZNAM_TABULKA = 'akce_seznam';

    public const ID_AKCE       = 'id_akce';
    public const PATRI_POD     = 'patri_pod';
    public const NAZEV_AKCE    = 'nazev_akce';
    public const URL_AKCE      = 'url_akce';
    public const ZACATEK       = 'zacatek';
    public const KONEC         = 'konec';
    public const LOKACE        = 'lokace';
    public const KAPACITA      = 'kapacita';
    public const KAPACITA_F    = 'kapacita_f';
    public const KAPACITA_M    = 'kapacita_m';
    public const CENA          = 'cena';
    public const BEZ_SLEVY    = 'bez_slevy';
    public const NEDAVA_BONUS = 'nedava_bonus';
    public const TYP          = 'typ';
    public const DITE          = 'dite';
    public const ROK           = 'rok';
    public const STAV          = 'stav';
    public const TEAMOVA       = 'teamova';
    public const TEAM_MIN      = 'team_min';
    public const TEAM_MAX      = 'team_max';
    public const TEAM_KAPACITA = 'team_kapacita';
    public const TEAM_NAZEV    = 'team_nazev';
    public const TEAM_LIMIT    = 'team_limit';
    public const ZAMCEL        = 'zamcel';
    public const ZAMCEL_CAS    = 'zamcel_cas';
    public const POPIS         = 'popis';
    public const POPIS_KRATKY  = 'popis_kratky';
    public const VYBAVENI      = 'vybaveni';
    public const PO_KOREKCI    = 'probehla_korekce';
}
