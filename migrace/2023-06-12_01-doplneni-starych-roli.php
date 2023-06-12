<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role, skryta, kategorie_role)
VALUES
(2, 'ORGANIZATOR_ZDARMA', 'Organizátor (zdarma)', 'Člen organizačního týmu GC', -1, 'trvala', 'ORGANIZATOR_ZDARMA', 0, 0),
(9, 'VYPRAVECSKA_SKUPINA', 'Vypravěčská skupina', 'Organizátorská skupina pořádající na GC (dodavatelé, …)', -1, 'trvala', 'VYPRAVECSKA_SKUPINA', 1, 0),
(15, 'CESTNY_ORGANIZATOR', 'Čestný organizátor', 'Bývalý organizátor GC', -1, 'trvala', 'CESTNY_ORGANIZATOR', 0, 0),
(16, 'ADMIN', 'Prezenční admin', 'Pro změnu účastníků v uzavřených aktivitách. NEBEZPEČNÉ, NEPOUŽÍVAT!', -1, 'trvala', 'ADMIN', 0, 0),
(20, 'CFO', 'CFO', 'Organizátor, který může nakládat s financemi GC', -1, 'trvala', 'CFO', 0, 0),
(21, 'PUL_ORG_UBYTKO', 'Půl-org s ubytkem', 'Krom jiného ubytování zdarma', -1, 'trvala', 'PUL_ORG_UBYTKO', 0, 0),
(22, 'PUL_ORG_TRICKO', 'Půl-org s tričkem', 'Krom jiného trička zdarma', -1, 'trvala', 'PUL_ORG_TRICKO', 0, 0),
(23, 'CLEN_RADY', 'Člen rady', 'Členové rady mají zvláštní zodpovědnost a pravomoce', -1, 'trvala', 'CLEN_RADY', 0, 0),
(24, 'SEF_INFOPULTU', 'Šéf infopultu', 'S pravomocemi dělat větší zásahy u přhlášených', -1, 'trvala', 'SEF_INFOPULTU', 0, 0),
(25, 'SEF_PROGRAMU', 'Šéf programu', 'Všeobecné "vedení" programu - obecná dramaturgie, rozvoj sekcí, finance programu', -1, 'trvala', 'SEF_PROGRAMU', 0, 0)
ON DUPLICATE KEY UPDATE id_role = id_role -- nedělat nic
SQL,
);
