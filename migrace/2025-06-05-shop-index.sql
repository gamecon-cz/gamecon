ALTER TABLE shop_predmety
    ADD INDEX idx_nazev (nazev);

ALTER TABLE shop_predmety
    ADD COLUMN je_letosni_hlavni TINYINT(1) NOT NULL DEFAULT 0 AFTER typ;
