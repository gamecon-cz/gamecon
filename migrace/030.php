<?php
// Přidá možnost přihlášení jako náhradník
$this->q("INSERT INTO akce_prihlaseni_stavy (id_stavu_prihlaseni, nazev, platba_procent) VALUES (5, 'náhradník (watchlist)', 0)");
$this->q("
ALTER TABLE `akce_prihlaseni_log`
CHANGE `typ` `typ` enum('prihlaseni','odhlaseni','nedostaveni_se','odhlaseni_hromadne','prihlaseni_nahradnik','prihlaseni_watchlist','odhlaseni_watchlist') COLLATE 'utf8_czech_ci' NOT NULL AFTER `cas`;
");
