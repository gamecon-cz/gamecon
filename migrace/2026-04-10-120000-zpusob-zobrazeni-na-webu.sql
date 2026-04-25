ALTER TABLE uzivatele_hodnoty
    ADD zpusob_zobrazeni_na_webu TINYINT(1) NOT NULL DEFAULT 0
        COMMENT '0-pouze přezdívka, 1-jméno + příjmení, 2-jméno + přezdívka + příjmení'
        AFTER statni_obcanstvi;
