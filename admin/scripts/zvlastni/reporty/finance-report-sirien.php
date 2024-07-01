<?php
require __DIR__ . '/sdilene-hlavicky.php';

/** @var $systemoveNastaveni */

$report = Report::zSql(<<<SQL
select 'Ir-Timestamp' as kod, 'Timestamp reportu' as nazev, now() as data

union

select concat('Ir-Ucast-', if(r.rid is null, 'Ucastnici (obyč)', r.rid)) as kod, 'Přihlášení dle kategorií' as nazev, count(u.id) as data
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

select 'Vr-Vstupne' as kod, 'Dobrovolné vstupné' as nazev, sum(all sn.cena_nakupni) as data
from shop_nakupy sn
join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 5 and sn.rok = aktualniRocnik()

union

select 'Vr-Ubytovani-3L' as kod, 'Prodané noci 3L' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('trojluzak_streda',
                          'trojluzak_ctvrtek',
                          'trojluzak_patek',
                          'trojluzak_sobota',
                          'trojluzak_nedele')
  and (not (
      maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
      or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
      or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
      or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
      or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
      or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
      ))

union

select 'Vr-Ubytovani-2L' as kod, 'Prodané noci 2L' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('dvojluzak_streda',
                          'dvojluzak_ctvrtek',
                          'dvojluzak_patek',
                          'dvojluzak_sobota',
                          'dvojluzak_nedele')
  and (not (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
        or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
        or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
        or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

union

select 'Vr-Ubytovani-1L' as kod, 'Prodané noci 1L' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('jednoluzak_streda',
                          'jednoluzak_ctvrtek',
                          'jednoluzak_patek',
                          'jednoluzak_sobota',
                          'jednoluzak_nedele')
  and (not (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
        or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
        or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
        or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

union

select 'Vr-Ubytovani-spac' as kod, 'Prodané noci spacáky' as nazev, count(sn.id_nakupu) as data
from shop_nakupy sn
         join shop_predmety sp on sp.id_predmetu = sn.id_predmetu
where sp.typ = 2
  and sn.rok = aktualniRocnik()
  and sp.kod_predmetu in ('spacak_streda',
                          'spacak_ctvrtek',
                          'spacak_patek',
                          'spacak_sobota',
                          'spacak_nedele')
  and (not (
    maPravo(sn.id_uzivatele, 1008) -- právo ubytování zdarma
        or (maPravo(sn.id_uzivatele, 1015) and sp.ubytovani_den = 0) -- ubytování zdarma středa
        or (maPravo(sn.id_uzivatele, 1029) and sp.ubytovani_den = 1) -- ubytování zdarma čtvrtek
        or (maPravo(sn.id_uzivatele, 1030) and sp.ubytovani_den = 2) -- ubytování zdarma pátek
        or (maPravo(sn.id_uzivatele, 1031) and sp.ubytovani_den = 3) -- ubytování zdarma sobota
        or (maPravo(sn.id_uzivatele, 1018) and sp.ubytovani_den = 4) -- ubytování zdarma neděle
    ))

union

select concat('Nr-Zdarma-', at.url_typu_mn) as kod, 'Cena za orgy zdarma' as nazev, sum(ase.cena) as data
from akce_seznam ase
         join akce_prihlaseni ap on ase.id_akce = ap.id_akce
         join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and ase.bez_slevy = 0
  and (maPravo(ap.id_uzivatele, 1023)) -- právo Plná sleva na aktivity
group by ase.typ

union

select concat('Vr-Storna-', at.url_typu_mn) as kod, 'Storna za' as nazev, (sum(ase.cena) / 2) as data
from akce_seznam ase
         join akce_prihlaseni_spec ap on ase.id_akce = ap.id_akce
         join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and (ase.bez_slevy = 1 or (not maPravo(ap.id_uzivatele, 1023))) -- není zdarma
  and ap.id_stavu_prihlaseni = 4 -- storno
group by ase.typ

union

select concat('Ir-Std', at.url_typu_mn) as kod, 'Počet' as nazev, sum(delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_seznam ase
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
group by ase.typ

union

select concat('Ir-Kapacita', at.url_typu_mn) as kod, 'Prům. kapacita' as nazev, sum(ase.kapacita * delkaAktivityJakoNasobekStandardni(ase.id_akce)) / sum(delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_seznam ase
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
group by ase.typ

union

select concat('Ir-PrumPocVyp-', at.url_typu_mn) as kod, 'Prům. počet vypravěčů' as nazev, sum((select count(*) from akce_organizatori ao where ao.id_akce = ase.id_akce) * delkaAktivityJakoNasobekStandardni(ase.id_akce)) / sum(delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_seznam ase
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
group by ase.typ

union

select concat('Ir-StdVypraveci-', at.url_typu_mn) as kod, 'Vypravěči' as nazev, sum(delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_organizatori ao
join akce_seznam ase on ase.id_akce = ao.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and not exists(select 1 from uzivatele_role ur where ur.id_uzivatele = ao.id_uzivatele and ur.id_role = 2) -- není full-org
group by ase.typ

union

select concat('Ir-StdVypOrgove-', at.url_typu_mn) as kod, 'Orgové vyp.' as nazev, sum(delkaAktivityJakoNasobekStandardni(ase.id_akce)) as data
from akce_organizatori ao
join akce_seznam ase on ase.id_akce = ao.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and exists(select 1 from uzivatele_role ur where ur.id_uzivatele = ao.id_uzivatele and ur.id_role = 2) -- není full-org
group by ase.typ

union

select concat('Nr-Bonusy', at.url_typu_mn) as kod, 'Bonusy za' as nazev, sum(delkaAktivityJakoNasobekStandardni(ase.id_akce) * systemoveNastaveni('BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU')) as data
from akce_organizatori ao
join akce_seznam ase on ase.id_akce = ao.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and not maPravo(ao.id_uzivatele, 1028) -- Bez bonusu za vedení aktivit
group by ase.typ

union

select concat('Ir-Ucast', at.url_typu_mn) as kod, 'Účast' as nazev, sum(delkaAktivityJakoNasobekStandardni(ase.id_akce))
from akce_prihlaseni ap
join akce_seznam ase on ap.id_akce = ase.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
group by ase.typ

union

select concat('Vr-Vynosy', at.url_typu_mn) as kod, 'Účast' as nazev, sum(ase.cena)
from akce_prihlaseni ap
join akce_seznam ase on ap.id_akce = ase.id_akce
join akce_typy at on ase.typ = at.id_typu
where ase.rok = aktualniRocnik()
  and not maPravo(ap.id_uzivatele, 1023) -- plná sleva na aktivity
group by ase.typ

union

select 'Vsechna tricka jsou TODO' as kod, 'Vsechna tricka jsou TODO' as nazev, 'TODO' as data

union

select concat('Vr-Kostky-', sp.kod_predmetu) as kod, 'kostka prodeje - včetně zdarma' as nazev, count(*) as data
from shop_nakupy sn
  join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%kostk%'
group by sp.id_predmetu

union

select a.kod, a.nazev, count(*) as data
from (
  select 'Ir-Kostky-CelkemZdarma' as kod, 'Kolik z prodaných kostek (všech typů) je zdarma' as nazev, 1 as data
  from shop_nakupy sn
    join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
  where sn.rok = aktualniRocnik()
    and sp.kod_predmetu like '%kostk%'
    and maPravo(sn.id_uzivatele, 1003) -- kostka zdarma
  group by sn.id_uzivatele
) a

union

select concat('Vr-Placky') as kod, 'placky prodeje - včetně zdarma' as nazev, count(*) as data
from shop_nakupy sn
  join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%plack%'

union

select a.kod, a.nazev, count(*) as data
from (
  select 'Ir-Placky-Zdarma' as kod, 'Kolik z prodaných placek je zdarma' as nazev, 1 as data
  from shop_nakupy sn
    join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
  where sn.rok = aktualniRocnik()
    and sp.kod_predmetu like '%plack%'
    and maPravo(sn.id_uzivatele, 1002) -- kostka zdarma
  group by sn.id_uzivatele
) a

union

select concat('Vr-Nicknacky') as kod, 'nicknacky' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%nicknack%'

union

select concat('Vr-Bloky') as kod, 'bloky' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%blok%'

union

select concat('Vr-Ponozky') as kod, 'ponožky' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%ponozk%'

union

select concat('Vr-Tasky') as kod, 'tašky' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%task%'

union

select concat('Xr-Jidla-Snidane') as kod, 'snídaně placené' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%snidane%'
  and not maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

union

select concat('Xr-Jidla-Hlavni') as kod, 'hl. jídla placené' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and (sp.kod_predmetu like '%obed%' or sp.kod_predmetu like '%vecere%')
  and not maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

union

select concat('Nr-JidlaZdarma-Snidane') as kod, 'snídaně placené' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and sp.kod_predmetu like '%snidane%'
  and maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

union

select concat('Nr-JidlaZdarma-Hlavni') as kod, 'hl. jídla placené' as nazev, count(*) as data
from shop_nakupy sn
         join shop_predmety sp on sn.id_predmetu = sp.id_predmetu
where sn.rok = aktualniRocnik()
  and (sp.kod_predmetu like '%obed%' or sp.kod_predmetu like '%vecere%')
  and maPravo(sn.id_uzivatele, 1005) -- jidlo zdarma

SQL,
);

$report->tFormat(get('format'));
