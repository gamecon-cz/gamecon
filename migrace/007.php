<?php

$this->q("

ALTER TABLE `shop_predmety`
ADD `nabizet_do` datetime NULL COMMENT 'automatizovaná náhrada za stav 3' AFTER `auto`;

update shop_predmety set nabizet_do = '2015-06-30 23:59:59' where typ in(1, 3) and stav != 0; -- předměty
update shop_predmety set nabizet_do = '2015-06-30 23:59:59' where typ = 5 and model_rok = 2015 and stav = 2; -- vstupné
update shop_predmety set nabizet_do = addtime('2015-07-13 23:59:59', concat(ubytovani_den, ' 0:0:0')) where typ = 4 and model_rok = 2015; -- jídlo
update shop_predmety set nabizet_do = '2015-07-12 23:59:59' where typ = 2 and model_rok = 2015; -- ubytování

");
