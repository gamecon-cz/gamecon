ALTER TABLE uzivatele_hodnoty
    ADD nechce_ubytovani TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '1-uzivatel explicitne nechce ubytovani'
        AFTER ubytovan_s;
