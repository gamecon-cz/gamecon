<?php

/** @var \Godric\DbMigrations\Migration $this */

$dbName = $this->q("SELECT DATABASE()")->fetchColumn();

// Helper: check if a column exists
$columnExists = function (string $table, string $column) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Helper: check if a table exists
$tableExists = function (string $table) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Helper: check if an index exists on a table
$indexExists = function (string $table, string $indexName) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND INDEX_NAME = '$indexName'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// Helper: rename index only if old name exists and new name does not
$renameIndex = function (string $table, string $oldName, string $newName) use ($indexExists): void {
    if ($indexExists($table, $oldName) && !$indexExists($table, $newName)) {
        $this->q("ALTER TABLE `$table` RENAME INDEX `$oldName` TO `$newName`");
    }
};

// Helper: check if a FK constraint exists
$fkExists = function (string $table, string $constraintName) use ($dbName): bool {
    $result = $this->q(
        "SELECT COUNT(*) AS cnt FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = '$table' AND CONSTRAINT_NAME = '$constraintName' AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
    );

    return (int) $result->fetch(\PDO::FETCH_ASSOC)['cnt'] > 0;
};

// ── Step 1: Create new tables ─────────────────────────────────────────────────

if (!$tableExists('product_tag')) {
    $this->q(<<<'SQL'
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
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

if (!$tableExists('product_product_tag')) {
    $this->q(<<<'SQL'
CREATE TABLE product_product_tag
(
    product_id BIGINT UNSIGNED NOT NULL,
    tag_id     BIGINT UNSIGNED NOT NULL,
    INDEX IDX_4F897D834584665A (product_id),
    INDEX IDX_4F897D83BAD26311 (tag_id),
    PRIMARY KEY (product_id, tag_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

if (!$tableExists('shop_order')) {
    $this->q(<<<'SQL'
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
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

if (!$tableExists('product_bundle')) {
    $this->q(<<<'SQL'
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
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

if (!$tableExists('product_bundle_items')) {
    $this->q(<<<'SQL'
CREATE TABLE product_bundle_items
(
    bundle_id  BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    INDEX IDX_F7E27E8DF1FAD9D3 (bundle_id),
    INDEX IDX_F7E27E8D4584665A (product_id),
    PRIMARY KEY (bundle_id, product_id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

if (!$tableExists('product_discount')) {
    $this->q(<<<'SQL'
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
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

if (!$tableExists('newsletter_prihlaseni_log')) {
    $this->q(<<<'SQL'
CREATE TABLE newsletter_prihlaseni_log
(
    id_newsletter_prihlaseni_log BIGINT UNSIGNED AUTO_INCREMENT     NOT NULL,
    email                        VARCHAR(512)                       NOT NULL,
    kdy                          DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    stav                         VARCHAR(127)                       NOT NULL,
    INDEX IDX_email (email),
    PRIMARY KEY (id_newsletter_prihlaseni_log)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

if (!$tableExists('newsletter_prihlaseni')) {
    $this->q(<<<'SQL'
CREATE TABLE newsletter_prihlaseni
(
    id_newsletter_prihlaseni BIGINT UNSIGNED AUTO_INCREMENT     NOT NULL,
    email                    VARCHAR(512)                       NOT NULL,
    kdy                      DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    UNIQUE INDEX UNIQ_email (email),
    PRIMARY KEY (id_newsletter_prihlaseni)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB
SQL);
}

// ── Step 1b: Ensure referenced PK columns are BIGINT UNSIGNED ─────────────────
// The anonymized dump has shop_predmety.id_predmetu as INT(11) but new FKs
// reference it as BIGINT UNSIGNED. Must match exactly for FK creation.
$idPredmetuType = $this->q(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'shop_predmety' AND COLUMN_NAME = 'id_predmetu'",
)->fetchColumn();
if ($idPredmetuType && !str_contains($idPredmetuType, 'bigint')) {
    $this->q("SET FOREIGN_KEY_CHECKS = 0");
    $this->q("ALTER TABLE shop_predmety MODIFY id_predmetu BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    $this->q("SET FOREIGN_KEY_CHECKS = 1");
}

// Also uzivatele_hodnoty.id_uzivatele (referenced by shop_order.customer_id)
$idUzivateleType = $this->q(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$dbName' AND TABLE_NAME = 'uzivatele_hodnoty' AND COLUMN_NAME = 'id_uzivatele'",
)->fetchColumn();
if ($idUzivateleType && !str_contains($idUzivateleType, 'bigint')) {
    // Must disable FK checks — many tables reference id_uzivatele
    $this->q("SET FOREIGN_KEY_CHECKS = 0");
    $this->q("ALTER TABLE uzivatele_hodnoty MODIFY id_uzivatele BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
    $this->q("SET FOREIGN_KEY_CHECKS = 1");
}

// ── Step 2: Add foreign keys (idempotent) ─────────────────────────────────────

if (!$fkExists('product_product_tag', 'FK_4F897D834584665A')) {
    $this->q("ALTER TABLE product_product_tag ADD CONSTRAINT FK_4F897D834584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE");
}
if (!$fkExists('product_product_tag', 'FK_4F897D83BAD26311')) {
    $this->q("ALTER TABLE product_product_tag ADD CONSTRAINT FK_4F897D83BAD26311 FOREIGN KEY (tag_id) REFERENCES product_tag (id) ON DELETE CASCADE");
}
if (!$fkExists('shop_order', 'FK_608DDB6C9395C3F3')) {
    $this->q("ALTER TABLE shop_order ADD CONSTRAINT FK_608DDB6C9395C3F3 FOREIGN KEY (customer_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE");
}
if (!$fkExists('product_bundle_items', 'FK_F7E27E8DF1FAD9D3')) {
    $this->q("ALTER TABLE product_bundle_items ADD CONSTRAINT FK_F7E27E8DF1FAD9D3 FOREIGN KEY (bundle_id) REFERENCES product_bundle (id) ON DELETE CASCADE");
}
if (!$fkExists('product_bundle_items', 'FK_F7E27E8D4584665A')) {
    $this->q("ALTER TABLE product_bundle_items ADD CONSTRAINT FK_F7E27E8D4584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE");
}
if (!$fkExists('product_discount', 'FK_92F7B4354584665A')) {
    $this->q("ALTER TABLE product_discount ADD CONSTRAINT FK_92F7B4354584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE");
}

// ── Step 3: Rename indexes (idempotent — skip if old name gone or new name already exists) ──

$renameIndex('stranky', 'url_stranky', 'UNIQ_3D4EE408803DB254');
$renameIndex('lokace', 'nazev', 'UNIQ_nazev_rok');
$renameIndex('reporty_log_pouziti', 'id_reportu', 'IDX_FEAC86E4C6E1AB00');
$renameIndex('reporty_log_pouziti', 'id_uzivatele', 'IDX_FEAC86E4D84E9520');
$renameIndex('reporty_log_pouziti', 'id_reportu_2', 'IDX_id_reportu_id_uzivatele');

// akce_seznam: add column + FK before renames
if (!$columnExists('akce_seznam', 'id_hlavni_lokace')) {
    $this->q("ALTER TABLE akce_seznam ADD id_hlavni_lokace BIGINT UNSIGNED DEFAULT NULL, CHANGE team_limit team_limit INT DEFAULT NULL COMMENT 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce `kapacita`'");
    if (!$fkExists('akce_seznam', 'FK_2EE8EBF09E0F2899')) {
        $this->q("ALTER TABLE akce_seznam ADD CONSTRAINT FK_2EE8EBF09E0F2899 FOREIGN KEY (id_hlavni_lokace) REFERENCES lokace (id_lokace) ON DELETE SET NULL");
    }
    if (!$indexExists('akce_seznam', 'IDX_2EE8EBF09E0F2899')) {
        $this->q("CREATE INDEX IDX_2EE8EBF09E0F2899 ON akce_seznam (id_hlavni_lokace)");
    }
    $this->q(<<<'SQL'
UPDATE akce_seznam
SET id_hlavni_lokace = (SELECT akce_lokace.id_lokace
                        FROM akce_lokace
                        WHERE akce_seznam.id_akce = akce_lokace.id_akce
                          AND akce_lokace.je_hlavni = 1
                        LIMIT 1)
SQL);
} else {
    // Column exists but team_limit comment may need updating
    $this->q("ALTER TABLE akce_seznam CHANGE team_limit team_limit INT DEFAULT NULL COMMENT 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce `kapacita`'");
}

$renameIndex('akce_seznam', 'patri_pod', 'IDX_2EE8EBF0AF219F1D');
$renameIndex('akce_seznam', 'typ', 'IDX_2EE8EBF0241AA1D');
$renameIndex('akce_seznam', 'stav', 'IDX_2EE8EBF0CEB69E0D');
$renameIndex('akce_seznam', 'zamcel', 'IDX_2EE8EBF0607A39CF');
$renameIndex('akce_seznam', 'rok', 'IDX_rok');
$renameIndex('akce_seznam', 'url_akce', 'UNIQ_url_akce_rok_typ');
$renameIndex('ubytovani', 'id_uzivatele', 'IDX_F483CEC3D84E9520');
$renameIndex('uzivatele_role_podle_rocniku', 'id_uzivatele', 'IDX_9204F263D84E9520');
$renameIndex('uzivatele_role_podle_rocniku', 'id_role', 'IDX_9204F263DC499668');
$renameIndex('uzivatele_role_podle_rocniku', 'rocnik', 'idx_uzivatele_role_podle_rocniku_rocnik');
$renameIndex('google_api_user_tokens', 'user_id_2', 'IDX_9A526EB4A76ED395');
$renameIndex('google_api_user_tokens', 'user_id', 'UNIQ_user_id_google_client_id');
$renameIndex('platby', 'id_uzivatele', 'IDX_4852A679D84E9520');
$renameIndex('platby', 'provedl', 'IDX_4852A67969513658');
$renameIndex('platby', 'id_uzivatele_2', 'IDX_id_uzivatele_rok');
$renameIndex('platby', 'fio_id', 'UNIQ_fio_id');
$renameIndex('kategorie_sjednocenych_tagu', 'id_hlavni_kategorie', 'IDX_A82F4189FF2287A1');
$renameIndex('kategorie_sjednocenych_tagu', 'nazev', 'UNIQ_nazev');
$renameIndex('log_udalosti', 'id_logujiciho', 'IDX_459DF155498E1820');
$renameIndex('log_udalosti', 'metadata', 'IDX_metadata');
$renameIndex('uzivatele_role', 'id_uzivatele_2', 'IDX_4F909638D84E9520');
$renameIndex('uzivatele_role', 'id_role', 'IDX_4F909638DC499668');
$renameIndex('uzivatele_role', 'posadil', 'IDX_4F909638AAB26C2');
$renameIndex('uzivatele_role', 'id_uzivatele', 'UNIQ_id_uzivatele_id_role');
$renameIndex('akce_typy', 'stranka_o', 'IDX_C12F7955DC7C4C42');
$renameIndex('uzivatele_hodnoty', 'infopult_poznamka', 'IDX_infopult_poznamka');
$renameIndex('uzivatele_hodnoty', 'login_uzivatele', 'UNIQ_login_uzivatele');
$renameIndex('uzivatele_hodnoty', 'email1_uzivatele', 'UNIQ_email1_uzivatele');
$renameIndex('novinky', 'url', 'UNIQ_url');
$renameIndex('google_drive_dirs', 'user_id_2', 'IDX_9E13BEAFA76ED395');
$renameIndex('google_drive_dirs', 'tag', 'IDX_tag');
$renameIndex('google_drive_dirs', 'dir_id', 'UNIQ_dir_id');
$renameIndex('google_drive_dirs', 'user_id', 'UNIQ_user_and_name');
$renameIndex('systemove_nastaveni', 'skupina', 'IDX_skupina');
$renameIndex('systemove_nastaveni', 'klic', 'UNIQ_klic_rocnik_nastaveni');
$renameIndex('systemove_nastaveni', 'nazev', 'UNIQ_nazev_rocnik_nastaveni');
$renameIndex('akce_import', 'id_uzivatele', 'IDX_D72EE2CDD84E9520');
$renameIndex('akce_import', 'google_sheet_id', 'IDX_google_sheet_id');
$renameIndex('slevy', 'id_uzivatele', 'IDX_17003B9AD84E9520');
$renameIndex('slevy', 'provedl', 'IDX_17003B9A69513658');
$renameIndex('hromadne_akce_log', 'provedl', 'IDX_E0A93D8A69513658');
$renameIndex('hromadne_akce_log', 'akce', 'IDX_akce');
$renameIndex('role_seznam', 'typ_role', 'IDX_typ_role');
$renameIndex('role_seznam', 'vyznam_role', 'IDX_vyznam_role');
$renameIndex('role_seznam', 'kod_role', 'UNIQ_kod_role');
$renameIndex('role_seznam', 'nazev_role', 'UNIQ_nazev_role');
$renameIndex('akce_prihlaseni_log', 'id_akce', 'IDX_947919F21E74DA0A');
$renameIndex('akce_prihlaseni_log', 'id_uzivatele', 'IDX_947919F2D84E9520');
$renameIndex('akce_prihlaseni_log', 'id_zmenil', 'IDX_947919F2E2649593');
$renameIndex('akce_prihlaseni_log', 'typ', 'IDX_typ');
$renameIndex('akce_prihlaseni_log', 'zdroj_zmeny', 'IDX_zdroj_zmeny');
$renameIndex('akce_prihlaseni', 'id_akce_2', 'IDX_7B7E722B1E74DA0A');
$renameIndex('akce_prihlaseni', 'id_uzivatele', 'IDX_7B7E722BD84E9520');
$renameIndex('akce_prihlaseni', 'id_stavu_prihlaseni', 'IDX_7B7E722B55D06BC9');
$renameIndex('akce_prihlaseni', 'id_akce', 'UNIQ_id_akce_id_uzivatele');

// shop_nakupy_zrusene column changes
$this->q("ALTER TABLE shop_nakupy_zrusene CHANGE cena_nakupni cena_nakupni NUMERIC(6, 2) NOT NULL, CHANGE datum_nakupu datum_nakupu DATETIME NOT NULL");
$renameIndex('shop_nakupy_zrusene', 'id_uzivatele', 'IDX_id_uzivatele');
$renameIndex('shop_nakupy_zrusene', 'id_predmetu', 'IDX_id_predmetu');
$renameIndex('shop_nakupy_zrusene', 'datum_zruseni', 'IDX_datum_zruseni');
$renameIndex('shop_nakupy_zrusene', 'zdroj_zruseni', 'IDX_zdroj_zruseni');
$renameIndex('akce_stavy_log', 'id_akce', 'IDX_195FCE481E74DA0A');
$renameIndex('akce_stavy_log', 'id_stav', 'IDX_195FCE484596820F');
$renameIndex('uzivatele_url', 'url', 'UNIQ_BA2D6079F47645AE');
$renameIndex('uzivatele_url', 'id_uzivatele', 'IDX_BA2D6079D84E9520');
$renameIndex('systemove_nastaveni_log', 'id_uzivatele', 'IDX_8F9C0959D84E9520');
$renameIndex('systemove_nastaveni_log', 'id_nastaveni', 'IDX_8F9C0959C8E5E058');
$renameIndex('reporty', 'skript', 'UNIQ_skript');
$renameIndex('role_texty_podle_uzivatele', 'id_uzivatele_2', 'IDX_D4CCA4DFD84E9520');
$renameIndex('role_texty_podle_uzivatele', 'id_uzivatele', 'UNIQ_id_uzivatele_vyznam_role');
$renameIndex('akce_sjednocene_tagy', 'id_akce', 'IDX_714E29671E74DA0A');
$renameIndex('akce_sjednocene_tagy', 'id_tagu', 'IDX_714E2967DFF2D11');
$renameIndex('prava_role', 'id_role', 'IDX_57A9921ADC499668');
$renameIndex('prava_role', 'id_prava', 'IDX_57A9921A1A86105C');
$renameIndex('sjednocene_tagy', 'id_kategorie_tagu', 'IDX_EEE57AA8FFC91574');
$renameIndex('sjednocene_tagy', 'nazev', 'UNIQ_nazev');
$renameIndex('mutex', 'klic', 'UNIQ_EECDB22FEC2A7D56');
$renameIndex('mutex', 'zamknul', 'IDX_EECDB22FCF5C09F0');
$renameIndex('mutex', 'akce', 'UNIQ_akce');
$renameIndex('obchod_bunky', 'mrizka_id', 'IDX_2DA00FBEE5BF0939');

// ── Step 4: shop_nakupy — drop old FK, add new columns ────────────────────────

$this->dropForeignKeysIfExist(['shop_nakupy_ibfk_1'], 'shop_nakupy');

if (!$columnExists('shop_nakupy', 'order_id')) {
    $this->q(<<<'SQL'
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
    CHANGE cena_nakupni cena_nakupni NUMERIC(6, 2) NOT NULL COMMENT 'Final purchase price (after discounts)'
SQL);
} else {
    // Columns already exist, just ensure type changes
    $this->q("ALTER TABLE shop_nakupy CHANGE id_predmetu id_predmetu BIGINT UNSIGNED DEFAULT NULL, CHANGE cena_nakupni cena_nakupni NUMERIC(6, 2) NOT NULL COMMENT 'Final purchase price (after discounts)'");
}

if (!$fkExists('shop_nakupy', 'FK_1A37DD218D9F6D38')) {
    $this->q("ALTER TABLE shop_nakupy ADD CONSTRAINT FK_1A37DD218D9F6D38 FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE SET NULL");
}
if (!$fkExists('shop_nakupy', 'FK_1A37DD213AB9335E')) {
    $this->q("ALTER TABLE shop_nakupy ADD CONSTRAINT FK_1A37DD213AB9335E FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu) ON DELETE SET NULL");
}
if (!$indexExists('shop_nakupy', 'IDX_1A37DD218D9F6D38')) {
    $this->q("CREATE INDEX IDX_1A37DD218D9F6D38 ON shop_nakupy (order_id)");
}
$renameIndex('shop_nakupy', 'id_uzivatele', 'IDX_1A37DD21D84E9520');
$renameIndex('shop_nakupy', 'id_objednatele', 'IDX_1A37DD218369B810');
$renameIndex('shop_nakupy', 'id_predmetu', 'IDX_1A37DD213AB9335E');
$renameIndex('shop_nakupy', 'rok', 'IDX_rok_id_uzivatele');

// ── Step 5: Seed product_tag data ─────────────────────────────────────────────

$existingTagCount = (int) $this->q("SELECT COUNT(*) FROM product_tag")->fetchColumn();
if ($existingTagCount === 0) {
    $this->q(<<<'SQL'
INSERT INTO product_tag (code, name, description, created_at)
VALUES ('predmet', 'Předmět', 'Merch (kostky, odznaky, zápisníky)', NOW()),
       ('ubytovani', 'Ubytování', NULL, NOW()),
       ('tricko', 'Tričko', NULL, NOW()),
       ('jidlo', 'Jídlo', NULL, NOW()),
       ('vstupne', 'Vstupné', NULL, NOW()),
       ('parcon', 'ParCon mini-akce', NULL, NOW()),
       ('proplaceni-bonusu', 'Výplata bonusu (interní)', NULL, NOW())
SQL);
}

// ── Step 6: shop_predmety — add new columns, migrate data, drop old columns ──

if (!$columnExists('shop_predmety', 'archived_at')) {
    $this->q(<<<'SQL'
ALTER TABLE shop_predmety
    ADD archived_at         DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    ADD amount_organizers   INT      DEFAULT NULL,
    ADD amount_participants INT      DEFAULT NULL,
    CHANGE nabizet_do nabizet_do DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
SQL);
}

// Migrate typ values to product_product_tag (only if typ column still exists)
if ($columnExists('shop_predmety', 'typ')) {
    $this->q(<<<'SQL'
INSERT INTO product_product_tag (product_id, tag_id)
SELECT shop_predmety.id_predmetu, product_tag.id
FROM shop_predmety
JOIN product_tag ON product_tag.code = CASE shop_predmety.typ
    WHEN 1 THEN 'predmet'
    WHEN 2 THEN 'ubytovani'
    WHEN 3 THEN 'tricko'
    WHEN 4 THEN 'jidlo'
    WHEN 5 THEN 'vstupne'
    WHEN 6 THEN 'parcon'
    WHEN 7 THEN 'proplaceni-bonusu'
END
WHERE shop_predmety.typ IS NOT NULL
ON DUPLICATE KEY UPDATE product_id = product_id
SQL);
}

// Archive products from previous years (only if model_rok column still exists)
if ($columnExists('shop_predmety', 'model_rok')) {
    $this->q(<<<'SQL'
UPDATE shop_predmety
SET archived_at = CONCAT(model_rok, '-12-31 23:59:59')
WHERE model_rok < (SELECT hodnota FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1)
  AND archived_at IS NULL
SQL);
}

// Drop old indexes (only if they exist)
// Production may have composite indexes like UNIQ_nazev_model_rok(nazev, model_rok)
// — these must be dropped before we can drop model_rok column
$oldIndexesToDrop = ['nazev', 'UNIQ_nazev_model_rok', 'kod_predmetu', 'UNIQ_kod_predmetu_model_rok'];
foreach ($oldIndexesToDrop as $oldIndex) {
    if ($indexExists('shop_predmety', $oldIndex)) {
        $this->q("DROP INDEX `$oldIndex` ON shop_predmety");
    }
}

// Drop old columns
if ($columnExists('shop_predmety', 'model_rok')) {
    $this->q("ALTER TABLE shop_predmety DROP COLUMN model_rok");
}
if ($columnExists('shop_predmety', 'typ')) {
    $this->q("ALTER TABLE shop_predmety DROP COLUMN typ");
}
if ($columnExists('shop_predmety', 'je_letosni_hlavni')) {
    $this->q("ALTER TABLE shop_predmety DROP COLUMN je_letosni_hlavni");
}

// Deduplicate kod_predmetu and nazev for archived products before creating unique indexes.
// Production has per-year duplicates (e.g. same kod_predmetu across different model_rok years).
// Keep the newest (highest id) and rename older duplicates by appending _ID.
$this->q(<<<'SQL'
UPDATE shop_predmety AS duplicated
INNER JOIN (
    SELECT kod_predmetu, MAX(id_predmetu) AS keep_id
    FROM shop_predmety
    GROUP BY kod_predmetu
    HAVING COUNT(*) > 1
) AS keeper ON duplicated.kod_predmetu = keeper.kod_predmetu AND duplicated.id_predmetu != keeper.keep_id
SET duplicated.kod_predmetu = CONCAT(duplicated.kod_predmetu, '_', duplicated.id_predmetu)
SQL);

$this->q(<<<'SQL'
UPDATE shop_predmety AS duplicated
INNER JOIN (
    SELECT nazev, MAX(id_predmetu) AS keep_id
    FROM shop_predmety
    GROUP BY nazev
    HAVING COUNT(*) > 1
) AS keeper ON duplicated.nazev = keeper.nazev AND duplicated.id_predmetu != keeper.keep_id
SET duplicated.nazev = CONCAT(duplicated.nazev, ' (#', duplicated.id_predmetu, ')')
SQL);

// Create new unique indexes
if (!$indexExists('shop_predmety', 'UNIQ_kod_predmetu')) {
    $this->q("CREATE UNIQUE INDEX UNIQ_kod_predmetu ON shop_predmety (kod_predmetu)");
}
if (!$indexExists('shop_predmety', 'UNIQ_nazev')) {
    $this->q("CREATE UNIQUE INDEX UNIQ_nazev ON shop_predmety (nazev)");
}

// ── Step 7: Backfill product snapshots in shop_nakupy ─────────────────────────

$this->q(<<<'SQL'
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
WHERE shop_nakupy.product_name IS NULL
SQL);

// ── Step 8: More index renames ────────────────────────────────────────────────

$renameIndex('akce_stav', 'nazev', 'UNIQ_nazev');
$renameIndex('akce_instance', 'id_hlavni_akce', 'IDX_F1D05242895FCA4C');
$renameIndex('akce_prihlaseni_spec', 'id_akce_2', 'IDX_78A8F4401E74DA0A');
$renameIndex('akce_prihlaseni_spec', 'id_uzivatele', 'IDX_78A8F440D84E9520');
$renameIndex('akce_prihlaseni_spec', 'id_stavu_prihlaseni', 'IDX_78A8F44055D06BC9');
$renameIndex('akce_prihlaseni_spec', 'id_akce', 'UNIQ_id_akce_id_uzivatele');
$renameIndex('uzivatele_slucovani_log', 'id_smazaneho_uzivatele', 'IDX_smazany_uzivatel');
$renameIndex('uzivatele_slucovani_log', 'id_noveho_uzivatele', 'IDX_novy_uzivatel');
$renameIndex('uzivatele_slucovani_log', 'kdy', 'IDX_kdy');
$renameIndex('uzivatele_role_log', 'id_uzivatele', 'IDX_9977B328D84E9520');
$renameIndex('uzivatele_role_log', 'id_role', 'IDX_9977B328DC499668');
$renameIndex('uzivatele_role_log', 'id_zmenil', 'IDX_9977B328E2649593');
$renameIndex('akce_organizatori', 'id_akce', 'IDX_F44FC74E1E74DA0A');
$renameIndex('akce_organizatori', 'id_uzivatele', 'IDX_F44FC74ED84E9520');

// ── Step 9: product_tag — add updated_at column ──────────────────────────────

if (!$columnExists('product_tag', 'updated_at')) {
    $this->q("ALTER TABLE product_tag ADD updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE created_at created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)'");
}

// ── Step 10: Backward-compatible view ─────────────────────────────────────────

// Note: podtyp still exists as a real column at this point (dropped later by podtyp-to-hotel-tag migration)
$this->q(<<<'SQL'
CREATE OR REPLACE VIEW shop_predmety_s_typem AS
SELECT
    shop_predmety.*,
    (SELECT CASE product_tag.code
        WHEN 'predmet' THEN 1
        WHEN 'ubytovani' THEN 2
        WHEN 'tricko' THEN 3
        WHEN 'jidlo' THEN 4
        WHEN 'vstupne' THEN 5
        WHEN 'parcon' THEN 6
        WHEN 'proplaceni-bonusu' THEN 7
    END
    FROM product_product_tag
    JOIN product_tag ON product_product_tag.tag_id = product_tag.id
    WHERE product_product_tag.product_id = shop_predmety.id_predmetu
      AND product_tag.code IN ('predmet','ubytovani','tricko','jidlo','vstupne','parcon','proplaceni-bonusu')
    LIMIT 1) AS typ,
    CASE WHEN shop_predmety.archived_at IS NULL
         THEN (SELECT CAST(hodnota AS UNSIGNED) FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1)
         ELSE YEAR(shop_predmety.archived_at)
    END AS model_rok,
    CASE WHEN shop_predmety.archived_at IS NULL THEN 1 ELSE 0 END AS je_letosni_hlavni
FROM shop_predmety
SQL);
