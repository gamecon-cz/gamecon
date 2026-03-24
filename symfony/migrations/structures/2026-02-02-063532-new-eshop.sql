CREATE TABLE product_tag
(
    id          BIGINT UNSIGNED AUTO_INCREMENT         NOT NULL,
    code        VARCHAR(50)                            NOT NULL,
    name        VARCHAR(255) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX UNIQ_name (name),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

CREATE TABLE product_product_tag
(
    product_id BIGINT UNSIGNED NOT NULL,
    tag_id     BIGINT UNSIGNED NOT NULL,
    INDEX IDX_4F897D834584665A (product_id),
    INDEX IDX_4F897D83BAD26311 (tag_id),
    PRIMARY KEY (product_id, tag_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

CREATE TABLE shop_order
(
    id           BIGINT UNSIGNED AUTO_INCREMENT          NOT NULL,
    customer_id  BIGINT UNSIGNED                         NOT NULL,
    year         SMALLINT                                NOT NULL,
    status       VARCHAR(20)   DEFAULT 'pending'         NOT NULL,
    total_price  NUMERIC(8, 2) DEFAULT '0.00'            NOT NULL,
    created_at   DATETIME      DEFAULT CURRENT_TIMESTAMP NOT NULL,
    completed_at DATETIME      DEFAULT NULL,
    INDEX IDX_323FC9CA9395C3F3 (customer_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

CREATE TABLE product_bundle
(
    id                  BIGINT UNSIGNED AUTO_INCREMENT       NOT NULL,
    name                VARCHAR(255)                         NOT NULL,
    forced              TINYINT(1) DEFAULT 0                 NOT NULL COMMENT 'If true, products cannot be purchased individually',
    applicable_to_roles JSON                                 NOT NULL COMMENT 'Array of role names for which bundle is mandatory (e.g., ["ucastnik"])(DC2Type:json)',
    created_at          DATETIME   DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at          DATETIME   DEFAULT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

CREATE TABLE product_bundle_items
(
    bundle_id  BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    INDEX IDX_F7E27E8DF1FAD9D3 (bundle_id),
    INDEX IDX_F7E27E8D4584665A (product_id),
    PRIMARY KEY (bundle_id, product_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

CREATE TABLE product_discount
(
    id               BIGINT UNSIGNED AUTO_INCREMENT     NOT NULL,
    product_id       BIGINT UNSIGNED                    NOT NULL,
    role             VARCHAR(50)                        NOT NULL COMMENT 'Role name: organizator, vypravec, ucastnik',
    discount_percent NUMERIC(5, 2)                      NOT NULL COMMENT 'Discount percent 0-100 (100 = free)',
    max_quantity     INT      DEFAULT NULL COMMENT 'Max quantity with discount (null = unlimited)',
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at       DATETIME DEFAULT NULL,
    INDEX IDX_2A50DE994584665A (product_id),
    UNIQUE INDEX UNIQ_product_role (product_id, role),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

CREATE TABLE newsletter_prihlaseni_log
(
    id_newsletter_prihlaseni_log BIGINT UNSIGNED AUTO_INCREMENT     NOT NULL,
    email                        VARCHAR(512)                       NOT NULL,
    kdy                          DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    stav                         VARCHAR(127)                       NOT NULL,
    INDEX IDX_email (email),
    PRIMARY KEY (id_newsletter_prihlaseni_log)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

CREATE TABLE newsletter_prihlaseni
(
    id_newsletter_prihlaseni BIGINT UNSIGNED AUTO_INCREMENT     NOT NULL,
    email                    VARCHAR(512)                       NOT NULL,
    kdy                      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE INDEX UNIQ_email (email),
    PRIMARY KEY (id_newsletter_prihlaseni)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_unicode_ci`
  ENGINE = InnoDB;

ALTER TABLE product_product_tag
    ADD CONSTRAINT FK_4F897D834584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE;
ALTER TABLE product_product_tag
    ADD CONSTRAINT FK_4F897D83BAD26311 FOREIGN KEY (tag_id) REFERENCES product_tag (id) ON DELETE CASCADE;
ALTER TABLE shop_order
    ADD CONSTRAINT FK_608DDB6C9395C3F3 FOREIGN KEY (customer_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE;
ALTER TABLE product_bundle_items
    ADD CONSTRAINT FK_F7E27E8DF1FAD9D3 FOREIGN KEY (bundle_id) REFERENCES product_bundle (id) ON DELETE CASCADE;
ALTER TABLE product_bundle_items
    ADD CONSTRAINT FK_F7E27E8D4584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE;
ALTER TABLE product_discount
    ADD CONSTRAINT FK_92F7B4354584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE;

ALTER TABLE stranky RENAME INDEX url_stranky TO UNIQ_3D4EE408803DB254;
ALTER TABLE lokace RENAME INDEX nazev TO UNIQ_nazev_rok;
ALTER TABLE reporty_log_pouziti RENAME INDEX id_reportu TO IDX_FEAC86E4C6E1AB00;
ALTER TABLE reporty_log_pouziti RENAME INDEX id_uzivatele TO IDX_FEAC86E4D84E9520;
ALTER TABLE reporty_log_pouziti RENAME INDEX id_reportu_2 TO IDX_id_reportu_id_uzivatele;

ALTER TABLE akce_seznam
    ADD id_hlavni_lokace BIGINT UNSIGNED DEFAULT NULL,
    CHANGE team_limit team_limit INT DEFAULT NULL COMMENT 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce `kapacita`';
ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_2EE8EBF09E0F2899 FOREIGN KEY (id_hlavni_lokace) REFERENCES lokace (id_lokace) ON DELETE SET NULL;
CREATE INDEX IDX_2EE8EBF09E0F2899 ON akce_seznam (id_hlavni_lokace);
UPDATE akce_seznam
SET id_hlavni_lokace = (SELECT akce_lokace.id_lokace
                        FROM akce_lokace
                        WHERE akce_seznam.id_akce = akce_lokace.id_akce
                          AND akce_lokace.je_hlavni = 1
                        LIMIT 1);

ALTER TABLE akce_seznam RENAME INDEX patri_pod TO IDX_2EE8EBF0AF219F1D;
ALTER TABLE akce_seznam RENAME INDEX typ TO IDX_2EE8EBF0241AA1D;
ALTER TABLE akce_seznam RENAME INDEX stav TO IDX_2EE8EBF0CEB69E0D;
ALTER TABLE akce_seznam RENAME INDEX zamcel TO IDX_2EE8EBF0607A39CF;
ALTER TABLE akce_seznam RENAME INDEX rok TO IDX_rok;
ALTER TABLE akce_seznam RENAME INDEX url_akce TO UNIQ_url_akce_rok_typ;
ALTER TABLE ubytovani RENAME INDEX id_uzivatele TO IDX_F483CEC3D84E9520;
ALTER TABLE uzivatele_role_podle_rocniku RENAME INDEX id_uzivatele TO IDX_9204F263D84E9520;
ALTER TABLE uzivatele_role_podle_rocniku RENAME INDEX id_role TO IDX_9204F263DC499668;
ALTER TABLE uzivatele_role_podle_rocniku RENAME INDEX rocnik TO idx_uzivatele_role_podle_rocniku_rocnik;
ALTER TABLE google_api_user_tokens RENAME INDEX user_id_2 TO IDX_9A526EB4A76ED395;
ALTER TABLE google_api_user_tokens RENAME INDEX user_id TO UNIQ_user_id_google_client_id;
ALTER TABLE platby RENAME INDEX id_uzivatele TO IDX_4852A679D84E9520;
ALTER TABLE platby RENAME INDEX provedl TO IDX_4852A67969513658;
ALTER TABLE platby RENAME INDEX id_uzivatele_2 TO IDX_id_uzivatele_rok;
ALTER TABLE platby RENAME INDEX fio_id TO UNIQ_fio_id;
ALTER TABLE kategorie_sjednocenych_tagu RENAME INDEX id_hlavni_kategorie TO IDX_A82F4189FF2287A1;
ALTER TABLE kategorie_sjednocenych_tagu RENAME INDEX nazev TO UNIQ_nazev;
ALTER TABLE log_udalosti RENAME INDEX id_logujiciho TO IDX_459DF155498E1820;
ALTER TABLE log_udalosti RENAME INDEX metadata TO IDX_metadata;
ALTER TABLE uzivatele_role RENAME INDEX id_uzivatele_2 TO IDX_4F909638D84E9520;
ALTER TABLE uzivatele_role RENAME INDEX id_role TO IDX_4F909638DC499668;
ALTER TABLE uzivatele_role RENAME INDEX posadil TO IDX_4F909638AAB26C2;
ALTER TABLE uzivatele_role RENAME INDEX id_uzivatele TO UNIQ_id_uzivatele_id_role;
ALTER TABLE akce_typy RENAME INDEX stranka_o TO IDX_C12F7955DC7C4C42;
ALTER TABLE uzivatele_hodnoty RENAME INDEX infopult_poznamka TO IDX_infopult_poznamka;
ALTER TABLE uzivatele_hodnoty RENAME INDEX login_uzivatele TO UNIQ_login_uzivatele;
ALTER TABLE uzivatele_hodnoty RENAME INDEX email1_uzivatele TO UNIQ_email1_uzivatele;
ALTER TABLE novinky RENAME INDEX url TO UNIQ_url;
ALTER TABLE google_drive_dirs RENAME INDEX user_id_2 TO IDX_9E13BEAFA76ED395;
ALTER TABLE google_drive_dirs RENAME INDEX tag TO IDX_tag;
ALTER TABLE google_drive_dirs RENAME INDEX dir_id TO UNIQ_dir_id;
ALTER TABLE google_drive_dirs RENAME INDEX user_id TO UNIQ_user_and_name;
ALTER TABLE systemove_nastaveni RENAME INDEX skupina TO IDX_skupina;
ALTER TABLE systemove_nastaveni RENAME INDEX klic TO UNIQ_klic_rocnik_nastaveni;
ALTER TABLE systemove_nastaveni RENAME INDEX nazev TO UNIQ_nazev_rocnik_nastaveni;
ALTER TABLE akce_import RENAME INDEX id_uzivatele TO IDX_D72EE2CDD84E9520;
ALTER TABLE akce_import RENAME INDEX google_sheet_id TO IDX_google_sheet_id;
ALTER TABLE slevy RENAME INDEX id_uzivatele TO IDX_17003B9AD84E9520;
ALTER TABLE slevy RENAME INDEX provedl TO IDX_17003B9A69513658;

ALTER TABLE hromadne_akce_log RENAME INDEX provedl TO IDX_E0A93D8A69513658;
ALTER TABLE hromadne_akce_log RENAME INDEX akce TO IDX_akce;
ALTER TABLE role_seznam RENAME INDEX typ_role TO IDX_typ_role;
ALTER TABLE role_seznam RENAME INDEX vyznam_role TO IDX_vyznam_role;
ALTER TABLE role_seznam RENAME INDEX kod_role TO UNIQ_kod_role;
ALTER TABLE role_seznam RENAME INDEX nazev_role TO UNIQ_nazev_role;
ALTER TABLE akce_prihlaseni_log RENAME INDEX id_akce TO IDX_947919F21E74DA0A;
ALTER TABLE akce_prihlaseni_log RENAME INDEX id_uzivatele TO IDX_947919F2D84E9520;
ALTER TABLE akce_prihlaseni_log RENAME INDEX id_zmenil TO IDX_947919F2E2649593;
ALTER TABLE akce_prihlaseni_log RENAME INDEX typ TO IDX_typ;
ALTER TABLE akce_prihlaseni_log RENAME INDEX zdroj_zmeny TO IDX_zdroj_zmeny;
ALTER TABLE akce_prihlaseni RENAME INDEX id_akce_2 TO IDX_7B7E722B1E74DA0A;
ALTER TABLE akce_prihlaseni RENAME INDEX id_uzivatele TO IDX_7B7E722BD84E9520;
ALTER TABLE akce_prihlaseni RENAME INDEX id_stavu_prihlaseni TO IDX_7B7E722B55D06BC9;
ALTER TABLE akce_prihlaseni RENAME INDEX id_akce TO UNIQ_id_akce_id_uzivatele;
ALTER TABLE shop_nakupy_zrusene
    CHANGE cena_nakupni cena_nakupni NUMERIC(6, 2) NOT NULL,
    CHANGE datum_nakupu datum_nakupu DATETIME NOT NULL;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX id_uzivatele TO IDX_id_uzivatele;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX id_predmetu TO IDX_id_predmetu;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX datum_zruseni TO IDX_datum_zruseni;
ALTER TABLE shop_nakupy_zrusene RENAME INDEX zdroj_zruseni TO IDX_zdroj_zruseni;
ALTER TABLE akce_stavy_log RENAME INDEX id_akce TO IDX_195FCE481E74DA0A;
ALTER TABLE akce_stavy_log RENAME INDEX id_stav TO IDX_195FCE484596820F;
ALTER TABLE uzivatele_url RENAME INDEX url TO UNIQ_BA2D6079F47645AE;
ALTER TABLE uzivatele_url RENAME INDEX id_uzivatele TO IDX_BA2D6079D84E9520;
ALTER TABLE systemove_nastaveni_log RENAME INDEX id_uzivatele TO IDX_8F9C0959D84E9520;
ALTER TABLE systemove_nastaveni_log RENAME INDEX id_nastaveni TO IDX_8F9C0959C8E5E058;
ALTER TABLE reporty RENAME INDEX skript TO UNIQ_skript;
ALTER TABLE role_texty_podle_uzivatele RENAME INDEX id_uzivatele_2 TO IDX_D4CCA4DFD84E9520;
ALTER TABLE role_texty_podle_uzivatele RENAME INDEX id_uzivatele TO UNIQ_id_uzivatele_vyznam_role;
ALTER TABLE akce_sjednocene_tagy RENAME INDEX id_akce TO IDX_714E29671E74DA0A;
ALTER TABLE akce_sjednocene_tagy RENAME INDEX id_tagu TO IDX_714E2967DFF2D11;
ALTER TABLE prava_role RENAME INDEX id_role TO IDX_57A9921ADC499668;
ALTER TABLE prava_role RENAME INDEX id_prava TO IDX_57A9921A1A86105C;
ALTER TABLE sjednocene_tagy RENAME INDEX id_kategorie_tagu TO IDX_EEE57AA8FFC91574;
ALTER TABLE sjednocene_tagy RENAME INDEX nazev TO UNIQ_nazev;
ALTER TABLE mutex RENAME INDEX klic TO UNIQ_EECDB22FEC2A7D56;
ALTER TABLE mutex RENAME INDEX zamknul TO IDX_EECDB22FCF5C09F0;
ALTER TABLE mutex RENAME INDEX akce TO UNIQ_akce;
ALTER TABLE obchod_bunky RENAME INDEX mrizka_id TO IDX_2DA00FBEE5BF0939;
ALTER TABLE shop_nakupy
    DROP FOREIGN KEY shop_nakupy_ibfk_1;
ALTER TABLE shop_nakupy
    ADD order_id            BIGINT UNSIGNED DEFAULT NULL,
    ADD product_name        VARCHAR(255)    DEFAULT NULL,
    ADD product_code        VARCHAR(255)    DEFAULT NULL,
    ADD product_tags        JSON            DEFAULT '[]' COMMENT '(DC2Type:json)',
    ADD product_description LONGTEXT        DEFAULT NULL,
    ADD original_price      NUMERIC(6, 2)   DEFAULT NULL COMMENT 'Original price before discounts',
    ADD discount_amount     NUMERIC(6, 2)   DEFAULT NULL COMMENT 'Discount amount in CZK',
    ADD discount_reason     VARCHAR(255)    DEFAULT NULL COMMENT 'Reason for discount (e.g., "Organizátor - kostka zdarma")',
    CHANGE id_predmetu id_predmetu BIGINT UNSIGNED DEFAULT NULL,
    CHANGE cena_nakupni cena_nakupni NUMERIC(6, 2) NOT NULL COMMENT 'Final purchase price (after discounts)';
ALTER TABLE shop_nakupy
    ADD CONSTRAINT FK_1A37DD218D9F6D38 FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE SET NULL;
ALTER TABLE shop_nakupy
    ADD CONSTRAINT FK_1A37DD213AB9335E FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu) ON DELETE SET NULL;
CREATE INDEX IDX_1A37DD218D9F6D38 ON shop_nakupy (order_id);
ALTER TABLE shop_nakupy RENAME INDEX id_uzivatele TO IDX_1A37DD21D84E9520;
ALTER TABLE shop_nakupy RENAME INDEX id_objednatele TO IDX_1A37DD218369B810;
ALTER TABLE shop_nakupy RENAME INDEX id_predmetu TO IDX_1A37DD213AB9335E;
ALTER TABLE shop_nakupy RENAME INDEX rok TO IDX_rok_id_uzivatele;

INSERT INTO product_tag (code, name, description, created_at)
VALUES ('predmet', 'Předmět', 'Merch (kostky, odznaky, zápisníky)', NOW()),
       ('ubytovani', 'Ubytování', NULL, NOW()),
       ('tricko', 'Tričko', NULL, NOW()),
       ('jidlo', 'Jídlo', NULL, NOW()),
       ('vstupne', 'Vstupné', NULL, NOW()),
       ('parcon', 'ParCon mini-akce', NULL, NOW()),
       ('proplaceni-bonusu', 'Výplata bonusu (interní)', NULL, NOW());

ALTER TABLE shop_predmety
    ADD archived_at         DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    ADD amount_organizers   INT      DEFAULT NULL,
    ADD amount_participants INT      DEFAULT NULL,
    CHANGE nabizet_do nabizet_do DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)';

-- Migrate existing typ values to product_product_tag (before dropping typ column)
INSERT INTO product_product_tag (product_id, tag_id)
SELECT sp.id_predmetu, pt.id
FROM shop_predmety sp
JOIN product_tag pt ON pt.code = CASE sp.typ
    WHEN 1 THEN 'predmet'
    WHEN 2 THEN 'ubytovani'
    WHEN 3 THEN 'tricko'
    WHEN 4 THEN 'jidlo'
    WHEN 5 THEN 'vstupne'
    WHEN 6 THEN 'parcon'
    WHEN 7 THEN 'proplaceni-bonusu'
END
WHERE sp.typ IS NOT NULL
ON DUPLICATE KEY UPDATE product_id = product_id;

-- Archive products from previous years (before dropping model_rok column)
UPDATE shop_predmety
SET archived_at = CONCAT(model_rok, '-12-31 23:59:59')
WHERE model_rok < (SELECT hodnota FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1)
  AND archived_at IS NULL;

DROP INDEX nazev ON shop_predmety;
DROP INDEX kod_predmetu ON shop_predmety;

ALTER TABLE shop_predmety
    DROP COLUMN model_rok,
    DROP COLUMN typ,
    DROP COLUMN je_letosni_hlavni;

CREATE UNIQUE INDEX UNIQ_kod_predmetu ON shop_predmety (kod_predmetu);
CREATE UNIQUE INDEX UNIQ_nazev ON shop_predmety (nazev);

UPDATE shop_nakupy
    INNER JOIN shop_predmety ON shop_nakupy.id_predmetu = shop_predmety.id_predmetu
    LEFT JOIN product_product_tag ON shop_nakupy.id_predmetu = product_product_tag.product_id
    LEFT JOIN product_tag ON product_product_tag.tag_id = product_tag.id
SET shop_nakupy.product_name        = shop_predmety.nazev,
    shop_nakupy.product_code        = shop_predmety.kod_predmetu,
    shop_nakupy.product_tags        = (SELECT JSON_ARRAYAGG(product_tag.code)
                                       FROM product_tag
                                                INNER JOIN product_product_tag ON product_tag.id = product_product_tag.tag_id
                                       WHERE product_product_tag.product_id = shop_nakupy.id_predmetu),
    shop_nakupy.product_description = shop_predmety.popis,
    shop_nakupy.original_price      = shop_nakupy.cena_nakupni
WHERE shop_nakupy.product_name IS NULL;

ALTER TABLE akce_stav RENAME INDEX nazev TO UNIQ_nazev;
ALTER TABLE akce_instance RENAME INDEX id_hlavni_akce TO IDX_F1D05242895FCA4C;
ALTER TABLE akce_prihlaseni_spec RENAME INDEX id_akce_2 TO IDX_78A8F4401E74DA0A;
ALTER TABLE akce_prihlaseni_spec RENAME INDEX id_uzivatele TO IDX_78A8F440D84E9520;
ALTER TABLE akce_prihlaseni_spec RENAME INDEX id_stavu_prihlaseni TO IDX_78A8F44055D06BC9;
ALTER TABLE akce_prihlaseni_spec RENAME INDEX id_akce TO UNIQ_id_akce_id_uzivatele;
ALTER TABLE uzivatele_slucovani_log RENAME INDEX id_smazaneho_uzivatele TO IDX_smazany_uzivatel;
ALTER TABLE uzivatele_slucovani_log RENAME INDEX id_noveho_uzivatele TO IDX_novy_uzivatel;
ALTER TABLE uzivatele_slucovani_log RENAME INDEX kdy TO IDX_kdy;
ALTER TABLE uzivatele_role_log RENAME INDEX id_uzivatele TO IDX_9977B328D84E9520;
ALTER TABLE uzivatele_role_log RENAME INDEX id_role TO IDX_9977B328DC499668;
ALTER TABLE uzivatele_role_log RENAME INDEX id_zmenil TO IDX_9977B328E2649593;
ALTER TABLE akce_organizatori RENAME INDEX id_akce TO IDX_F44FC74E1E74DA0A;
ALTER TABLE akce_organizatori RENAME INDEX id_uzivatele TO IDX_F44FC74ED84E9520;

ALTER TABLE product_tag
    ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)';

-- Backward-compatible view providing virtual typ, model_rok, je_letosni_hlavni columns
CREATE OR REPLACE VIEW shop_predmety_s_typem AS
SELECT
    sp.*,
    (SELECT CASE pt.code
        WHEN 'predmet' THEN 1
        WHEN 'ubytovani' THEN 2
        WHEN 'tricko' THEN 3
        WHEN 'jidlo' THEN 4
        WHEN 'vstupne' THEN 5
        WHEN 'parcon' THEN 6
        WHEN 'proplaceni-bonusu' THEN 7
    END
    FROM product_product_tag ppt
    JOIN product_tag pt ON ppt.tag_id = pt.id
    WHERE ppt.product_id = sp.id_predmetu
      AND pt.code IN ('predmet','ubytovani','tricko','jidlo','vstupne','parcon','proplaceni-bonusu')
    LIMIT 1) AS typ,
    CASE WHEN sp.archived_at IS NULL
         THEN (SELECT CAST(hodnota AS UNSIGNED) FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1)
         ELSE YEAR(sp.archived_at)
    END AS model_rok,
    CASE WHEN sp.archived_at IS NULL THEN 1 ELSE 0 END AS je_letosni_hlavni
FROM shop_predmety sp;
