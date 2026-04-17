INSERT INTO role_seznam (
    id_role,
    kod_role,
    nazev_role,
    popis_role,
    rocnik_role,
    typ_role,
    vyznam_role,
    skryta,
    kategorie_role
)
VALUES (
    29,
    'PAUZUJICI_FULL_ORG',
    'Pauzující Full-org',
    'Dočasně pauznutý full org bez přístupu do adminu',
    -1,
    'trvala',
    'PAUZUJICI_FULL_ORG',
    0,
    0
)
ON DUPLICATE KEY UPDATE
    kod_role = VALUES(kod_role),
    nazev_role = VALUES(nazev_role),
    popis_role = VALUES(popis_role),
    rocnik_role = VALUES(rocnik_role),
    typ_role = VALUES(typ_role),
    vyznam_role = VALUES(vyznam_role),
    skryta = VALUES(skryta),
    kategorie_role = VALUES(kategorie_role);

DELETE
FROM prava_role
WHERE id_role = 29;

INSERT INTO prava_role (id_role, id_prava)
SELECT 29, id_prava
FROM prava_role
WHERE id_role = 2
  AND id_prava NOT BETWEEN 100 AND 112;
