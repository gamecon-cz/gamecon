<?php

// Přidání tabulky pro medailonky vypravěčů

$this->q("

ALTER TABLE `shop_predmety`
CHANGE `typ` `typ` tinyint(4) NOT NULL COMMENT '1-předmět, 2-ubytování, 3-tričko, 4-jídlo, 5-vstupné' AFTER `kusu_vyrobeno`;

ALTER TABLE `shop_nakupy`
CHANGE `datum` `datum` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `cena_nakupni`;

INSERT INTO shop_predmety(nazev, stav, typ, model_rok) VALUES ('Dobrovolné vstupné', 2, 5, 2015);
INSERT INTO shop_predmety(nazev, stav, typ, model_rok) VALUES ('Dobrovolné vstupné (pozdě)', 3, 5, 2015);

");
