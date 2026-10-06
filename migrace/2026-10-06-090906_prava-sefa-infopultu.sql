INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava)
VALUES (1040, 'Může přeplnit ubytování', 'Smí ubytovat i do plné noci (šéf infa)'),
       (1041, 'Může zamykat a odemykat týmy', 'Smí zamknout a odemknout tým aktivity (šéf infa)'),
       (1042, 'Nemusí potvrzovat na infopultu', 'Při práci na infopultu nemusí potvrzovat chybějící materiály, nedoplatek a podobné (šéf infa)');

INSERT INTO prava_role (id_role, id_prava)
SELECT role_seznam.id_role, nova_prava.id_prava
FROM role_seznam
         CROSS JOIN (SELECT 1040 AS id_prava
                     UNION ALL
                     SELECT 1041
                     UNION ALL
                     SELECT 1042) AS nova_prava
WHERE role_seznam.kod_role = 'SEF_INFOPULTU';
