<?php
/** @var \Godric\DbMigrations\Migration $this */

/**
 * Provázání MDrD a LKD a WG
 * https://trello.com/c/Nm5I8ThV/804-prov%C3%A1z%C3%A1n%C3%AD-mdrd-a-lkd-a-wg
 */
$this->q(<<<SQL
UPDATE akce_seznam
SET dite = (SELECT GROUP_CONCAT(finale.id_akce) FROM (SELECT id_akce /* 1× */  FROM `akce_seznam` WHERE rok = 2021 and nazev_akce = 'Mistrovství v DrD - Finále') AS finale)
WHERE id_akce IN (SELECT semifinale.id_akce FROM (SELECT id_akce /* 2× */ FROM `akce_seznam` WHERE rok = 2021 and nazev_akce = 'Mistrovství v DrD - Semifinále') AS semifinale);

UPDATE akce_seznam
SET dite = (SELECT GROUP_CONCAT(semifinale.id_akce) FROM (SELECT id_akce /* 2× */ FROM `akce_seznam` WHERE rok = 2021 and nazev_akce = 'Mistrovství v DrD - Semifinále') AS semifinale)
WHERE id_akce IN (SELECT ctvrtfinale.id_akce FROM (SELECT id_akce /* 36× */ FROM `akce_seznam` WHERE rok = 2021 and nazev_akce = 'Mistrovství v DrD - Čtvrtfinále') AS ctvrtfinale);

UPDATE akce_seznam
SET dite = 3726 -- Turnaj Age of Sigmar (den 1) https://admin.gamecon.cz/aktivity/upravy?aktivitaId=3726
WHERE id_akce IN (3727); -- Turnaj Age of Sigmar (den 2) https://admin.gamecon.cz/aktivity/upravy?aktivitaId=3727

UPDATE akce_seznam
SET dite = 3604 -- (čt+pá) Druhé kolo Legend https://admin.gamecon.cz/aktivity/upravy?aktivitaId=3604
WHERE id_akce IN (3499 /* Skvrna (čt+pá) */, 3504 /* Čas na vizitku (čt+pá) */, 3591 /* Terapie osvobozuje (čt+pá) */, 3606 /* Bílá paní od Cimbuří (čt+pá) */);

UPDATE akce_seznam
SET dite = 3605 -- (pá+so) Druhé kolo Legend https://admin.gamecon.cz/aktivity/upravy?aktivitaId=3605
WHERE id_akce IN (3507 /* Rákos, písek a krev (pá+so) */, 3590 /* Co vápno nezakrylo (pá+so) */, 3647 /* Jezte kroupy! (pá+so) */);
SQL
);
