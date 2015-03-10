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
