<?php

// demo kopie aktivit z minulého roku
dbQuery('
  UPDATE akce_seznam
  SET
    rok = '.ROK.',
    zacatek = zacatek + interval 1 year - interval 1 day,
    konec = konec + interval 1 year - interval 1 day
  WHERE rok = '.(ROK - 1).'
');

// RAND(3) je nastavení seedu aby byly výsledky reprodukovatelné
dbQuery('UPDATE akce_seznam SET stav = IF(typ = 10, 0, 1) WHERE rok = '.ROK.' AND RAND(3) < 0.8');

// legendy uvolnění
dbQuery('
DELETE ap
FROM akce_prihlaseni ap
WHERE ap.id_akce = (SELECT MAX(id_akce) FROM akce_seznam WHERE typ = 8 AND rok = '.ROK.' AND kapacita != 0)
OR ap.id_akce = (SELECT MIN(id_akce) FROM akce_seznam WHERE typ = 8 AND rok = '.ROK.' AND kapacita != 0)
');

// DrD aktivity otevření
dbQuery('update akce_seznam set stav = 1 where typ = 9');
dbQuery('update akce_seznam set stav = 4 where typ = 9 and patri_pod = 0');

// DrD vyprázdnění 10% aktivit
dbQuery('
delete p
from akce_prihlaseni p
join akce_seznam a on (a.id_akce = p.id_akce)
where a.typ = 9 and a.id_akce % 10 < 1
');
