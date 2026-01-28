/* 0) Stránka: dnd-info (vložit jen pokud ještě neexistuje) */
INSERT INTO stranky (url_stranky, obsah, poradi)
SELECT
    'dnd-info',
    '# D&D/JaD turnaj

Stránka je ve fázi příprav.',
    0
WHERE NOT EXISTS (
    SELECT 1 FROM stranky WHERE url_stranky = 'dnd-info'
);

/* 1) Typ akce: D&D/JaD turnaj (url_typu_mn = dnd), stránka_o -> dnd-info */
INSERT INTO akce_typy (
    id_typu, typ_1p, typ_1pmn, url_typu_mn,
    stranka_o, poradi, mail_neucast, popis_kratky,
    aktivni, zobrazit_v_menu, kod_typu
)
SELECT
    x.new_id,
    'D&D/JaD turnaj',
    'D&D/JaD turnaj',
    'dnd',
    (SELECT id_stranky FROM stranky WHERE url_stranky = 'dnd-info' LIMIT 1),
    0,
    0,
    'Turnaj v D&D/JaD.',
    1,
    1,
    'DnD'
FROM (SELECT IFNULL(MAX(id_typu), 0) + 1 AS new_id FROM akce_typy) x
WHERE NOT EXISTS (
    SELECT 1 FROM akce_typy WHERE url_typu_mn = 'dnd'
);

/* 1b) Kdyby typ už existoval, srovnat mu název + navázání na stránku dnd-info */
UPDATE akce_typy
SET
    typ_1p = 'D&D/JaD turnaj',
    typ_1pmn = 'D&D/JaD turnaj',
    stranka_o = (SELECT id_stranky FROM stranky WHERE url_stranky = 'dnd-info' LIMIT 1),
    aktivni = 1,
    zobrazit_v_menu = 1,
    kod_typu = COALESCE(kod_typu, 'DnD')
WHERE url_typu_mn = 'dnd';

/* 2) Pořadí v menu podle abecedy (jen aktivní + zobrazované v menu) */
UPDATE akce_typy t
JOIN (
    SELECT
        o.id_typu,
        (@poradi := @poradi + 1) AS new_poradi
    FROM (
        SELECT id_typu
        FROM akce_typy
        WHERE aktivni = 1
          AND zobrazit_v_menu = 1
        ORDER BY typ_1pmn COLLATE utf8mb3_czech_ci, id_typu
    ) o
    CROSS JOIN (SELECT @poradi := 0) init
) s ON s.id_typu = t.id_typu
SET t.poradi = s.new_poradi;
