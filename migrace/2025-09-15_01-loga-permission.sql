INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES (112, 'Administrace - panel Web Loga', 'Správa log sponzorů a partnerů na webu');

INSERT INTO role_seznam
SET
    id_role = 28,
    kod_role = 'SPRAVCE_PARTNERU',
    nazev_role = 'Správce partnerů',
    popis_role = 'Správa partnerů a sponzorů na webu',
    rocnik_role = -1,
    typ_role = 'trvala',
    vyznam_role = 'SPRAVCE_PARTNERU',
    skryta = 0,
    kategorie_role = 1; -- běžná, i pro nečleny rady

INSERT INTO prava_role (id_role, id_prava)
VALUES (28, 112);
