<?php

$this->q("

INSERT INTO shop_predmety(id_predmetu, nazev, model_rok, cena_aktualni, stav, auto, nabizet_do, kusu_vyrobeno, typ, ubytovani_den, popis)
SELECT NULL, replace(nazev, 'Tričko', 'Tílko'), model_rok, cena_aktualni, stav, auto, nabizet_do, kusu_vyrobeno, typ, ubytovani_den, popis
FROM shop_predmety
WHERE model_rok = 2016 AND typ = 3 AND nazev LIKE '%dámské%'

");
