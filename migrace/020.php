<?php

$this->q("

ALTER TABLE `akce_organizatori`
ENGINE='InnoDB';

ALTER TABLE `akce_organizatori`
ADD FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `akce_organizatori`
ADD FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- smazat spec. přihlášky na neexistující aktivity (85 řádků)
delete p
from akce_prihlaseni_spec p
left join akce_seznam a on a.id_akce = p.id_akce
where a.id_akce is null;

ALTER TABLE `akce_prihlaseni_spec`
ADD FOREIGN KEY (`id_akce`) REFERENCES `akce_seznam` (`id_akce`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `akce_prihlaseni_spec`
ADD FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `akce_prihlaseni_spec`
ADD FOREIGN KEY (`id_stavu_prihlaseni`) REFERENCES `akce_prihlaseni_stavy` (`id_stavu_prihlaseni`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- ----- --
-- práva --
-- ----- --

ALTER TABLE `r_prava_soupis`
ENGINE='InnoDB';

ALTER TABLE `r_prava_zidle`
ENGINE='InnoDB';

ALTER TABLE `r_uzivatele_zidle`
ENGINE='InnoDB';

ALTER TABLE `r_zidle_soupis`
ENGINE='InnoDB';

ALTER TABLE `r_prava_zidle`
ADD FOREIGN KEY (`id_prava`) REFERENCES `r_prava_soupis` (`id_prava`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `r_prava_zidle`
ADD FOREIGN KEY (`id_zidle`) REFERENCES `r_zidle_soupis` (`id_zidle`) ON DELETE CASCADE ON UPDATE RESTRICT;

-- smazat židle neexistujících uživatelů (2 řádky)
delete uz
from r_uzivatele_zidle uz
left join uzivatele_hodnoty u on u.id_uzivatele = uz.id_uzivatele
where u.id_uzivatele is null;

ALTER TABLE `r_uzivatele_zidle`
ADD FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT ON UPDATE RESTRICT;

INSERT INTO r_zidle_soupis (id_zidle, jmeno_zidle, popis_zidle) VALUES
(-1503, 'GC2015 odjel', ''),
(-1603, 'GC2016 odjel', ''),
(-1703, 'GC2017 odjel', ''),
(-1803, 'GC2018 odjel', ''),
(-1903, 'GC2019 odjel', ''),
(-2003, 'GC2020 odjel', ''),
(-2103, 'GC2021 odjel', ''),
(-2203, 'GC2022 odjel', '');

ALTER TABLE `r_uzivatele_zidle`
ADD FOREIGN KEY (`id_zidle`) REFERENCES `r_zidle_soupis` (`id_zidle`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- ------ --
-- e-shop --
-- ------ --

ALTER TABLE `shop_nakupy`
ENGINE='InnoDB';

ALTER TABLE `shop_predmety`
ENGINE='InnoDB';

-- smazat nákupy neexistujících uživatelů (2 řádky)
delete sn
from shop_nakupy sn
left join uzivatele_hodnoty u on u.id_uzivatele = sn.id_uzivatele
where u.id_uzivatele is null;

ALTER TABLE `shop_nakupy`
ADD FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `shop_nakupy`
ADD FOREIGN KEY (`id_predmetu`) REFERENCES `shop_predmety` (`id_predmetu`) ON DELETE RESTRICT ON UPDATE RESTRICT;

-- ------ --
-- zbytek --
-- ------ --

ALTER TABLE `ubytovani`
CHANGE `id_uzivatele` `id_uzivatele` int NOT NULL FIRST;

ALTER TABLE `ubytovani`
ADD FOREIGN KEY (`id_uzivatele`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT ON UPDATE RESTRICT;

ALTER TABLE `uzivatele_hodnoty`
CHANGE `heslo_md5` `heslo_md5` varchar(255) COLLATE 'ucs2_czech_ci' NOT NULL COMMENT 'přechází se na password_hash' AFTER `datum_narozeni`;

ALTER TABLE `uzivatele_hodnoty`
ADD FOREIGN KEY (`guru`) REFERENCES `uzivatele_hodnoty` (`id_uzivatele`) ON DELETE RESTRICT ON UPDATE RESTRICT;

");
