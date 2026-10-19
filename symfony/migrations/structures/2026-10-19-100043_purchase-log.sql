-- No foreign keys: the log has to outlive the purchase and the customer, and a key would take a
-- shared lock on the customer's row with every purchase written.
CREATE TABLE shop_order_item_log
(
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_item_id BIGINT UNSIGNED NOT NULL,
    customer_id   BIGINT UNSIGNED NOT NULL,
    year          SMALLINT        NOT NULL,
    action        VARCHAR(16)     NOT NULL,
    changes       JSON            NOT NULL,
    changed_by_id BIGINT UNSIGNED DEFAULT NULL,
    origin        VARCHAR(255)    NOT NULL,
    changed_at    DATETIME        NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    PRIMARY KEY (id)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;

CREATE INDEX IDX_order_item_log_order_item ON shop_order_item_log (order_item_id);
CREATE INDEX IDX_order_item_log_customer_year ON shop_order_item_log (customer_id, year);
CREATE INDEX IDX_order_item_log_changed_by ON shop_order_item_log (changed_by_id);
CREATE INDEX IDX_order_item_log_origin ON shop_order_item_log (origin);
