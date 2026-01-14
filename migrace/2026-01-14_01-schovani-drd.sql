/* 1) DrD: poradi -3, nezobrazovat v menu */
UPDATE akce_typy
SET poradi = -3,
    zobrazit_v_menu = 0
WHERE url_typu_mn = 'drd';

/* 2) Přejmenování "akční a bonusové aktivity" -> "akční hry a bonusy" */
UPDATE akce_typy
SET typ_1pmn = 'akční hry a bonusy'
WHERE url_typu_mn = 'bonusy';

/* 3) Nový typ: Celohra (jen pokud ještě neexistuje a existuje aspoň jedna stránka) */
INSERT INTO akce_typy (
    id_typu, typ_1p, typ_1pmn, url_typu_mn,
    stranka_o, poradi, mail_neucast, popis_kratky,
    aktivni, zobrazit_v_menu, kod_typu
)
SELECT
    x.new_id,
    'Celohra',
    'Celohry',
    'celohra',
    (SELECT MIN(id_stranky) FROM stranky),  -- existující stránka v aktuální DB
    0,      -- dočasně, přepočítá se v kroku 4
    0,
    'Celohra',
    1,
    1,
    'ch'
FROM (
    SELECT COALESCE(MAX(id_typu), 0) + 1 AS new_id
    FROM akce_typy
) x
WHERE NOT EXISTS (
    SELECT 1 FROM akce_typy WHERE url_typu_mn = 'celohra'
)
AND EXISTS (
    SELECT 1 FROM stranky
);

/* 4) Ostatní: přepočítat poradi (jen ty s poradi >= 0) podle abecedy typ_1pmn */
UPDATE akce_typy t
JOIN (
    SELECT a.id_typu, (@rn := @rn + 1) AS new_poradi
    FROM akce_typy a
    JOIN (SELECT @rn := 0) vars
    WHERE a.poradi >= 0
    ORDER BY a.typ_1pmn COLLATE utf8mb3_czech_ci
) s ON s.id_typu = t.id_typu
SET t.poradi = s.new_poradi
WHERE t.poradi >= 0;
