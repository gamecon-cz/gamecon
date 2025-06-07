DELETE FROM reporty where nazev = 'grafy-ankety';
DELETE FROM reporty where nazev = 'parovani-ankety';
DELETE FROM reporty where nazev = 'rozesilani-ankety';

ALTER TABLE uzivatele_hodnoty DROP COLUMN email2_uzivatele;
ALTER TABLE uzivatele_hodnoty DROP COLUMN funkce_uzivatele;
ALTER TABLE uzivatele_hodnoty DROP COLUMN jine_uzivatele;
ALTER TABLE uzivatele_hodnoty DROP COLUMN skola;
