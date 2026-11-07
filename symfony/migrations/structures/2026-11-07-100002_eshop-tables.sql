-- The shop data layer in its final shape: tags, variants, bundles, discounts, orders and the purchase logs.
-- The legacy columns stay until the data has moved (see the *-legacy-catalog-removed migration), and the
-- columns that need a value before they can be NOT NULL are added nullable and tightened where they are filled.

CREATE TABLE product_tag
(
    id          BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    code        VARCHAR(50)                    NOT NULL,
    name        VARCHAR(255) DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at  DATETIME                       NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at  DATETIME                       NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX UNIQ_name (name),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

CREATE TABLE product_product_tag
(
    product_id BIGINT UNSIGNED NOT NULL,
    tag_id     BIGINT UNSIGNED NOT NULL,
    INDEX IDX_4F897D834584665A (product_id),
    INDEX IDX_4F897D83BAD26311 (tag_id),
    PRIMARY KEY (product_id, tag_id),
    CONSTRAINT FK_4F897D834584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu),
    CONSTRAINT FK_4F897D83BAD26311 FOREIGN KEY (tag_id) REFERENCES product_tag (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

CREATE TABLE product_variant
(
    id                      BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    product_id              BIGINT UNSIGNED                NOT NULL,
    name                    VARCHAR(255)   DEFAULT NULL,
    code                    VARCHAR(255)                   NOT NULL,
    price                   NUMERIC(10, 2) DEFAULT NULL,
    reserved_for_organizers INT            DEFAULT NULL COMMENT 'NULL = inherit from parent product',
    accommodation_day       SMALLINT       DEFAULT NULL COMMENT '0-4 for St-Ne, NULL for non-accommodation',
    position                SMALLINT       DEFAULT 0       NOT NULL,
    capacity                INT            DEFAULT NULL,
    state                   SMALLINT                       NOT NULL,
    UNIQUE INDEX UNIQ_variant_code (code),
    PRIMARY KEY (id),
    CONSTRAINT FK_variant_product FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
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
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

CREATE TABLE product_bundle_variant
(
    bundle_id  BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NOT NULL,
    INDEX IDX_bundle_variant_bundle (bundle_id),
    INDEX IDX_bundle_variant_variant (variant_id),
    PRIMARY KEY (bundle_id, variant_id),
    CONSTRAINT FK_bundle_variant_bundle FOREIGN KEY (bundle_id) REFERENCES product_bundle (id) ON DELETE CASCADE,
    CONSTRAINT FK_bundle_variant_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
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
    PRIMARY KEY (id),
    CONSTRAINT FK_92F7B4354584665A FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

CREATE TABLE discount_rule
(
    id             BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    code           VARCHAR(64)                    NOT NULL,
    name           VARCHAR(255)                   NOT NULL,
    description    TEXT                           NULL,
    required_right INT                            NOT NULL,
    priority       SMALLINT DEFAULT 100           NOT NULL,
    parameters     LONGTEXT                       NOT NULL CHECK (JSON_VALID(parameters)),
    year           SMALLINT                       NOT NULL,
    active         TINYINT(1) DEFAULT 1           NOT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at     DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE INDEX UNIQ_discount_rule_code_year (code, year),
    INDEX IDX_discount_rule_right (required_right),
    INDEX IDX_discount_rule_year_active (year, active),
    INDEX IDX_discount_rule_priority (year, active, priority)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

CREATE TABLE shop_order
(
    id                     BIGINT UNSIGNED AUTO_INCREMENT          NOT NULL,
    customer_id            BIGINT UNSIGNED                         NOT NULL,
    year                   SMALLINT                                NOT NULL,
    status                 VARCHAR(20)   DEFAULT 'pending'         NOT NULL,
    total_price            NUMERIC(10, 2) DEFAULT '0.00'           NOT NULL,
    created_at             DATETIME      DEFAULT CURRENT_TIMESTAMP NOT NULL,
    completed_at           DATETIME      DEFAULT NULL,
    roommate               VARCHAR(255)  DEFAULT NULL,
    accommodation_declined TINYINT(1)                              NOT NULL,
    INDEX IDX_323FC9CA9395C3F3 (customer_id),
    PRIMARY KEY (id),
    CONSTRAINT FK_608DDB6C9395C3F3 FOREIGN KEY (customer_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

-- No foreign keys: the log has to outlive the purchase and the customer, and a key would take a
-- shared lock on the customer's row with every purchase written.
CREATE TABLE shop_order_item_log
(
    id            BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    order_item_id BIGINT UNSIGNED                NOT NULL,
    customer_id   BIGINT UNSIGNED                NOT NULL,
    year          SMALLINT                       NOT NULL,
    action        VARCHAR(16)                    NOT NULL,
    changes       JSON                           NOT NULL,
    changed_by_id BIGINT UNSIGNED DEFAULT NULL,
    origin        VARCHAR(255)                   NOT NULL,
    changed_at    DATETIME                       NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    INDEX IDX_order_item_log_order_item (order_item_id),
    INDEX IDX_order_item_log_customer_year (customer_id, year),
    INDEX IDX_order_item_log_changed_by (changed_by_id),
    INDEX IDX_order_item_log_origin (origin),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

-- Not shop_nakupy_zrusene: that is an append-only audit log of real cancellations, while this row is
-- overwritten on every new breakfast selection so that only the most recent one is ever offered back.
CREATE TABLE shop_snidane_snapshot
(
    id_uzivatele BIGINT UNSIGNED NOT NULL,
    rok          SMALLINT        NOT NULL,
    variant_ids  JSON            NOT NULL,
    ulozeno      DATETIME        NOT NULL,
    PRIMARY KEY (id_uzivatele, rok),
    CONSTRAINT shop_snidane_snapshot_uzivatel FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) DEFAULT CHARACTER SET utf8mb4
  COLLATE `utf8mb4_czech_ci`
  ENGINE = InnoDB;

ALTER TABLE shop_predmety
    ADD archived_at              DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
    ADD breakfast_included       TINYINT(1) DEFAULT 0 NOT NULL COMMENT 'True iff cena_aktualni already includes breakfast (e.g. hotel rooms)',
    ADD reserved_for_organizers  INT DEFAULT NULL COMMENT 'Default reserved-for-organizers for variants. NULL = no reservation.',
    CHANGE nabizet_do nabizet_do DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)';

-- id_predmetu stays until every purchase points at its variant; variant_id becomes NOT NULL where it is filled.
ALTER TABLE shop_nakupy
    CHANGE cena_nakupni cena_nakupni NUMERIC(6, 2) NOT NULL COMMENT 'Final purchase price (after discounts)',
    ADD order_id            BIGINT UNSIGNED DEFAULT NULL,
    ADD product_name        VARCHAR(255)    DEFAULT NULL,
    ADD product_code        VARCHAR(255)    DEFAULT NULL,
    ADD product_tags        JSON            DEFAULT '[]' COMMENT '(DC2Type:json)',
    ADD product_description LONGTEXT        DEFAULT NULL,
    ADD original_price      NUMERIC(10, 2)  DEFAULT NULL COMMENT 'Original price before discounts',
    ADD discount_amount     NUMERIC(10, 2)  DEFAULT NULL COMMENT 'Discount amount in CZK',
    ADD discount_reason     VARCHAR(255)    DEFAULT NULL COMMENT 'Reason for discount (e.g., "Organizátor - kostka zdarma")',
    ADD variant_id          BIGINT UNSIGNED DEFAULT NULL,
    ADD variant_name        VARCHAR(255)    DEFAULT NULL,
    ADD variant_code        VARCHAR(255)    DEFAULT NULL,
    ADD bundle_id           BIGINT UNSIGNED DEFAULT NULL,
    ADD discount_snapshot   JSON            DEFAULT NULL COMMENT '(DC2Type:json)',
    ADD override_log        JSON            DEFAULT NULL;

CREATE INDEX IDX_1A37DD218D9F6D38 ON shop_nakupy (order_id);
CREATE INDEX IDX_nakupy_bundle ON shop_nakupy (bundle_id);
CREATE INDEX IDX_nakupy_rok_variant ON shop_nakupy (rok, variant_id);

ALTER TABLE shop_nakupy
    ADD CONSTRAINT FK_1A37DD218D9F6D38 FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE SET NULL,
    ADD CONSTRAINT FK_nakupy_bundle FOREIGN KEY (bundle_id) REFERENCES product_bundle (id) ON DELETE SET NULL;

ALTER TABLE shop_nakupy_zrusene
    CHANGE cena_nakupni cena_nakupni NUMERIC(6, 2) NOT NULL,
    CHANGE datum_nakupu datum_nakupu DATETIME NOT NULL,
    ADD product_name VARCHAR(255)    DEFAULT NULL,
    ADD product_code VARCHAR(255)    DEFAULT NULL,
    ADD variant_id   BIGINT UNSIGNED DEFAULT NULL;

ALTER TABLE obchod_bunky
    ADD variant_id BIGINT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT FK_obchod_bunky_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL;

ALTER TABLE platby
    ADD order_id BIGINT UNSIGNED DEFAULT NULL;

CREATE INDEX IDX_platby_order ON platby (order_id);

ALTER TABLE platby
    ADD CONSTRAINT FK_platby_order FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE SET NULL;
