<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$report = Report::zSql(<<<SQL
select 'Ir-Timestamp' as kod, 'Timestamp reportu' as nazev, now() as data

union

select concat('Ir-Ucast-', if(r.rid is null, 'Ucastnici', case r.rid
                                                            when 'ORGANIZATOR_ZDARMA' then 'Org0'
                                                            when 'PUL_ORG_UBYTKO' then 'OrgU'
                                                            when 'PUL_ORG_TRICKO' then 'OrgT'
                                                            when 'VYPRAVEC' then 'Vypraveci'
                                                            when 'DOBROVOLNIK_SENIOR' then 'Dobrovolnici'
                                                            when 'PARTNER' then 'Partneri'
                                                            when 'BRIGADNIK' then 'Brigadnici'
                                                           end)) as kod, 'Počet přihlášených účastníků dle kategorií' as nazev, count(u.id) as data
from (select distinct id_uzivatele as id
      from uzivatele_role
      join role_seznam on uzivatele_role.id_role = role_seznam.id_role
      where typ_role = 'ucast'
        and role_seznam.vyznam_role = 'PRIHLASEN'
        and rocnik_role = aktualniRocnik()) u
left join (select ur.id_uzivatele uid, rs.vyznam_role rid
           from uzivatele_role ur
           join role_seznam rs on ur.id_role = rs.id_role
           where rs.rocnik_role in (aktualniRocnik(), -1)
             and rs.vyznam_role in ('ORGANIZATOR_ZDARMA',
                                    'PUL_ORG_UBYTKO',
                                    'PUL_ORG_TRICKO',
                                    'VYPRAVEC',
                                    'DOBROVOLNIK_SENIOR',
                                    'PARTNER',
                                    'BRIGADNIK')) r on r.uid = u.id
group by r.rid

union

select 'Vr-Vstupne' as kod, 'Dobrovolné vstupné (sum CZK)' as nazev, sum(all sn.cena_nakupni) as data
from shop_nakupy sn
join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 5 and sn.rok = aktualniRocnik()

union

select 'Vr-Ubytovani-3L' as kod, 'Prodané noci 3L (počet)' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('3L_st',
                          '3L_ct',
                          '3L_pa',
                          '3L_so',
                          '3L_ne')
  and (not (
      maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
      or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
      or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
      or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
      or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
      or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
      ))

union

select 'Vr-Ubytovani-2L' as kod, 'Prodané noci 2L (počet)' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('2L_st',
                          '2L_ct',
                          '2L_pa',
                          '2L_so',
                          '2L_ne')
  and (not (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
        or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
        or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
        or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

union

select 'Vr-Ubytovani-1L' as kod, 'Prodané noci 1L (počet)' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('1L_st',
                          '1L_ct',
                          '1L_pa',
                          '1L_so',
                          '1L_ne')
  and (not (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
        or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
        or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
        or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

union

select 'Vr-Ubytovani-spac' as kod, 'Prodané noci spacáky (počet)' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('spacak_st',
                          'spacak_ct',
                          'spacak_pa',
                          'spacak_so',
                          'spacak_ne')
  and (not (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
        or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
        or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
        or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Nr-Zdarma-', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Cena za účast orgů zdarma na programu (s právem "Plná sleva na aktivity" na akci, která není "bez slev") (sum CZK)' as nazev, (ase.cena) as data
from akce_seznam ase
         join akce_prihlaseni ap on ase.id_akce = ap.id_akce
         join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and ase.bez_slevy = 0
  and (maPravo(ap.id_uzivatele, 1023)) -- právo Plná sleva na aktivity
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Vr-Storna-', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Storna za' as nazev, ((ase.cena) / 2) as data
from akce_seznam ase
         join akce_prihlaseni_spec ap on ase.id_akce = ap.id_akce
         join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and (ase.bez_slevy = 1 or (not maPravo(ap.id_uzivatele, 1023))) -- není zdarma
  and ap.id_stavu_prihlaseni = 4 -- storno
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Ir-Std', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Počet aktivit přepočtený na standardní aktivitu' as nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_seznam ase
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.kapacita * a.dajns) / sum(dajns)) as data from (
select concat('Ir-Kapacita', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Průměrná kapacita aktivity, vážený průměr podle přepočtu na standardní aktivitu' as nazev, ase.kapacita as kapacita, delkaAktivityJakoNasobekStandardni(ase.id_akce) as dajns
from akce_seznam ase
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.vypraveci * a.dajns) / sum(dajns)) as data from (
select concat('Ir-PrumPocVyp-', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Prům. počet vypravěčů 1 aktivity, vážený průměr podle přepočtu na standardní aktivitu' as nazev, (select count(*) from akce_organizatori ao where ao.id_akce = ase.id_akce) as vypraveci, delkaAktivityJakoNasobekStandardni(ase.id_akce) as dajns
from akce_seznam ase
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Ir-StdVypraveci-', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Vypravěčobloky (přepočtené standardní aktivity * počet lidí) vedené Vypravěči nebo Half-orgy' as nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_organizatori ao
join akce_seznam ase on ase.id_akce = ao.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and not exists(select 1 from uzivatele_role ur where ur.id_uzivatele = ao.id_uzivatele and ur.id_role = 2) -- není full-org
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Ir-StdVypOrgove-', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Vypravěčobloky (přepočtené standardní aktivity * počet lidí) vedené Orgy' as nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_organizatori ao
join akce_seznam ase on ase.id_akce = ao.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and exists(select 1 from uzivatele_role ur where ur.id_uzivatele = ao.id_uzivatele and ur.id_role = 2) -- není full-org
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Nr-Bonusy', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Suma bonusů za vedení aktivit u lidí bez práva "bez bonusu za vedení aktivit"' as nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce) * systemoveNastaveni('BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU')) as data
from akce_organizatori ao
join akce_seznam ase on ase.id_akce = ao.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and not maPravo(ao.id_uzivatele, 1028) -- Bez bonusu za vedení aktivit
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Ir-Ucast', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Počet herních bloků zabraný hráči přepočtený na standardní aktivitu (bez ohledu na kategorii hráče)' as nazev, (delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_prihlaseni ap
join akce_seznam ase on ap.id_akce = ase.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and at.kod_typu is not null
) a
group by a.kod

union

select a.kod, a.nazev, (sum(a.data)) as data from (
select concat('Vr-Vynosy', if(at.id_typu = 6, -- Wargaming
                                   if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12445 /*Malování*/), 'WGmal', 'WGhry'),
                                   if(at.id_typu = 7, -- Bonus
                                      if(exists(select 1 from akce_sjednocene_tagy ast where ast.id_akce = ase.id_akce and ast.id_tagu = 12444 /*Únikovka*/), 'AHEsc', 'AHry'),
                                      at.kod_typu))) as kod,
        'Příjmy z aktivit, bez storn a bez lidí co mají účast zdarma' as nazev, (ase.cena) as data
from akce_prihlaseni ap
join akce_seznam ase on ap.id_akce = ase.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and not maPravo(ap.id_uzivatele, 1023) -- plná sleva na aktivity
  and at.kod_typu is not null
) a
group by a.kod

union

select 'Vsechna tricka jsou TODO' as kod, 'Vsechna tricka jsou TODO' as nazev, 'TODO' as data

union

select concat('Vr-Kostky-', sp.kod_predmetu) as kod, 'kostka prodeje - včetně zdarma - kusy' as nazev, count(*) as data
from shop_nakupy sn
  join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%kostk%'
group by sp.id_predmetu

union

select a.kod, a.nazev, count(*) as data
from (
  select 'Ir-Kostky-CelkemZdarma' as kod, 'Kolik z prodaných kostek (všech typů) je zdarma - kusy' as nazev, 1 as data
  from shop_nakupy sn
    join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
  where sn.rok = aktualniRocnik()
    and sp.kod_predmetu like '%kostk%'
    and maPravo(sn.id_uzivatele, 1003) -- kostka zdarma
  group by sn.id_uzivatele
) a

union

select concat('Vr-Placky') as kod, 'placky prodeje - včetně zdarma - kusy' as nazev, count(*) as data
from shop_nakupy sn
  join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%plack%'

union

select a.kod, a.nazev, count(*) as data
from (
  select 'Ir-Placky-Zdarma' as kod, 'Kolik z prodaných placek je zdarma - kusy' as nazev, 1 as data
  from shop_nakupy sn
    join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
  where sn.rok = aktualniRocnik()
    and sp.kod_predmetu like '%plack%'
    and maPravo(sn.id_uzivatele, 1002) -- kostka zdarma
  group by sn.id_uzivatele
) a

union

select concat('Vr-Nicknacky') as kod, 'nicknacky prodeje - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%nicknack%'

union

select concat('Vr-Bloky') as kod, 'bloky prodeje - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%blok%'

union

select concat('Vr-Ponozky') as kod, 'ponožky prodeje - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%ponozk%'

union

select concat('Vr-Tasky') as kod, 'tašky prodeje - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%task%'

union

select concat('Xr-Jidla-Snidane') as kod, 'snídaně placené - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%snidane%'
  and not maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

union

select concat('Xr-Jidla-Hlavni') as kod, 'hl. jídla placené - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and (sp.kod_predmetu like '%obed%' or sp.kod_predmetu like '%vecere%')
  and not maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

union

select concat('Nr-JidlaZdarma-Snidane') as kod, 'snídaně zdarma - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%snidane%'
  and maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

union

select concat('Nr-JidlaZdarma-Hlavni') as kod, 'hl. jídla zdarma - kusy' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and (sp.kod_predmetu like '%obed%' or sp.kod_predmetu like '%vecere%')
  and maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

SQL,
);

$report->tFormat(get('format'));
