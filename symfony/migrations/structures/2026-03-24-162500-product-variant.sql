-- Product variants (e.g. T-shirt sizes, accommodation days)

CREATE TABLE product_variant (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id             BIGINT UNSIGNED NOT NULL,
    name                   VARCHAR(255)    NOT NULL,
    code                   VARCHAR(255)    NOT NULL,
    price                  NUMERIC(6, 2)   DEFAULT NULL COMMENT 'NULL = inherit from parent product',
    remaining_quantity     INT             DEFAULT NULL COMMENT 'NULL = unlimited capacity',
    reserved_for_organizers INT            DEFAULT NULL COMMENT 'NULL = inherit from parent product',
    accommodation_day      SMALLINT        DEFAULT NULL COMMENT '0-4 for St-Ne, NULL for non-accommodation',
    position               SMALLINT        NOT NULL DEFAULT 0,
    CONSTRAINT UNIQ_variant_code UNIQUE (code),
    CONSTRAINT FK_variant_product FOREIGN KEY (product_id) REFERENCES shop_predmety (id_predmetu) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

-- Add variant reference and snapshot to order items
ALTER TABLE shop_nakupy
    ADD variant_id   BIGINT UNSIGNED DEFAULT NULL,
    ADD variant_name VARCHAR(255)    DEFAULT NULL,
    ADD variant_code VARCHAR(255)    DEFAULT NULL,
    ADD CONSTRAINT FK_nakupy_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE SET NULL;
