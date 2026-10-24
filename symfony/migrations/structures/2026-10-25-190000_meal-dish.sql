-- Jídla z jídelníčku: konkrétní jídla, ze kterých si účastník vybere k objednané porci.
-- Produkt jídla (Oběd pátek) přežívá ročníky, proto jídlo nese vlastní ročník.
CREATE TABLE meal_dish
(
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    variant_id  BIGINT UNSIGNED NOT NULL,
    year        SMALLINT        NOT NULL,
    name        VARCHAR(255)    NOT NULL,
    description VARCHAR(255)             DEFAULT NULL,
    vegetarian  TINYINT(1)      NOT NULL DEFAULT 0,
    is_default  TINYINT(1)      NOT NULL DEFAULT 0,
    position    SMALLINT        NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at  DATETIME        NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id),
    INDEX IDX_8FA35F4C3B69A9AF (variant_id),
    INDEX IDX_meal_dish_year_variant (year, variant_id),
    CONSTRAINT FK_meal_dish_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;

-- Výběr jídla visí na koupené porci. RESTRICT, ne SET NULL: vynulování databází by obešlo
-- audit nákupů, takže smazané jídlo nejdřív odebere výběry samo přes Doctrine (MealMenuEditor).
ALTER TABLE shop_nakupy
    ADD COLUMN meal_dish_id BIGINT UNSIGNED DEFAULT NULL,
    ADD INDEX IDX_1A37DD216D3D02D2 (meal_dish_id),
    ADD CONSTRAINT FK_nakupy_meal_dish FOREIGN KEY (meal_dish_id) REFERENCES meal_dish (id) ON DELETE RESTRICT;
