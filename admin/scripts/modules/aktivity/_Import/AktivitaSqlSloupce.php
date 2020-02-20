<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

/**
 * SQL table akce_seznam
 */
class AktivitaSqlSloupce
{
  public const ID_AKCE = 'id_akce'; // int auto_increment primary key
  public const PATRI_POD = 'patri_pod'; // int null,
  public const NAZEV_AKCE = 'nazev_akce'; // varchar(255) not null,
  public const URL_AKCE = 'url_akce'; // varchar(64) null,
  public const ZACATEK = 'zacatek'; // datetime null,
  public const KONEC = 'konec'; // datetime null,
  public const LOKACE = 'lokace'; // int not null,
  public const KAPACITA = 'kapacita'; // int not null,
  public const KAPACITA_F = 'kapacita_f'; // int not null,
  public const KAPACITA_M = 'kapacita_m'; // int not null,
  public const CENA = 'cena'; // int not null,
  public const BEZ_SLEVY = 'bez_slevy'; // tinyint(1) not null comment 'na aktivitu se neuplatňují slevy',
  public const NEDAVA_SLEVU = 'nedava_slevu'; // tinyint(1) not null comment 'aktivita negeneruje organizátorovi slevu',
  public const TYP = 'typ'; // int not null,
  public const DITE = 'dite'; // varchar(64) null comment 'potomci oddělení čárkou',
  public const ROK = 'rok'; // int not null,
  public const STAV = 'stav'; // tinyint(1) not null comment '0-v přípravě 1-aktivní 2-proběhnuté 3-systémové(deprec) 4-viditelné,nepřihlašovatelné 5-připravené k aktivaci',
  public const TEAMOVA = 'teamova'; // tinyint(1) not null,
  public const TEAM_MIN = 'team_min'; // int null comment 'minimální velikost teamu',
  public const TEAM_MAX = 'team_max'; // int null comment 'maximální velikost teamu',
  public const TEAM_KAPACITA = 'team_kapacita'; // int null comment 'max. počet týmů, pokud jde o další kolo týmové aktivity',
  public const TEAM_NAZEV = 'team_nazev'; // varchar(255) null,
  public const ZAMCEL = 'zamcel'; // int null comment 'případně kdo zamčel aktivitu pro svůj team',
  public const ZAMCEL_CAS = 'zamcel_cas'; // datetime null comment 'případně kdy zamčel aktivitu',
  public const POPIS = 'popis'; // int not null,
  public const POPIS_KRATKY = 'popis_kratky'; // varchar(255) not null,
  public const VYBAVENI = 'vybaveni'; // text not null,
}
