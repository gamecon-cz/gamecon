<?php

// demo kopie aktivit z minulého roku
dbQuery("
  UPDATE akce_seznam
  SET
    rok = 2015,
    zacatek = zacatek + interval 1 year - interval 1 day,
    konec = konec + interval 1 year - interval 1 day
  WHERE rok = 2014
");

// RAND(3) je nastavení seedu aby byly výsledky reprodukovatelné
dbQuery('UPDATE akce_seznam SET stav = 1 WHERE rok = 2015 AND RAND(3) < 0.8');

// legendy uvolnění
dbQuery('delete from akce_prihlaseni where id_akce = 991');

// DrD aktivity otevření
dbQuery('update akce_seznam set stav=1 where typ=9');
dbQuery('update akce_seznam set stav=4 where id_akce in (852,853,856)');

// DrD vyprázdnění 10% aktivit
dbQuery('
delete p
from akce_prihlaseni p
join akce_seznam a on (a.id_akce = p.id_akce)
where a.typ = 9 and a.id_akce % 10 < 1
');

// Default heslo pro uživatele
dbQuery('
update uzivatele_hodnoty u
left join r_uzivatele_zidle z on(z.id_zidle = 2 and u.id_uzivatele = z.id_uzivatele)
set heslo_md5 = md5("stereo")
where z.id_zidle is null
');
