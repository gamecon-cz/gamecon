insert into r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUE (1038, 'Může rušit nákupy', 'Může rušit nákupy uživatelů (šéf infa, financí...)');
insert into prava_role (id_role, id_prava) VALUES (20, 1038), (24, 1038);
