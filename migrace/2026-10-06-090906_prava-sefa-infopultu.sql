INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES (1040, 'Může přeplnit ubytování', 'Smí ubytovat i do plné noci (šéf infa)'),
       (1041, 'Může zamykat a odemykat týmy', 'Smí zamknout a odemknout tým aktivity (šéf infa)'),
       (1042, 'Nemusí potvrzovat na infopultu', 'Při práci na infopultu nemusí potvrzovat chybějící materiály, nedoplatek a podobné (šéf infa)');

INSERT INTO prava_role (id_role, id_prava)
VALUES (24, 1040),
       (24, 1041),
       (24, 1042);
