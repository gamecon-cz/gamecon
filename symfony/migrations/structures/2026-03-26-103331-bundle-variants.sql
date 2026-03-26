-- Switch ProductBundle from product-based to variant-based join table

DROP TABLE IF EXISTS product_bundle_items;

CREATE TABLE product_bundle_variant (
    bundle_id  BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (bundle_id, variant_id),
    INDEX IDX_bundle_variant_bundle (bundle_id),
    INDEX IDX_bundle_variant_variant (variant_id),
    CONSTRAINT FK_bundle_variant_bundle FOREIGN KEY (bundle_id)
        REFERENCES product_bundle (id) ON DELETE CASCADE,
    CONSTRAINT FK_bundle_variant_variant FOREIGN KEY (variant_id)
        REFERENCES product_variant (id) ON DELETE CASCADE
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Track bundle membership on order items
ALTER TABLE shop_nakupy ADD COLUMN bundle_id BIGINT UNSIGNED DEFAULT NULL;
ALTER TABLE shop_nakupy ADD INDEX IDX_nakupy_bundle (bundle_id);
ALTER TABLE shop_nakupy ADD CONSTRAINT FK_nakupy_bundle
    FOREIGN KEY (bundle_id) REFERENCES product_bundle (id) ON DELETE SET NULL;
