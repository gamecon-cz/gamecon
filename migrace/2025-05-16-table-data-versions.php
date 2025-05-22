<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE _table_data_versions (
    table_name VARCHAR(255) NOT NULL,
    version INT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (table_name)
)
SQL,
);

$this->q(<<<SQL
CREATE TABLE _tables_used_in_view_data_versions (
    view_name VARCHAR(255) NOT NULL,
    table_used_in_view VARCHAR(255) NOT NULL,
    PRIMARY KEY (view_name, table_used_in_view),
    FOREIGN KEY (view_name) REFERENCES _table_data_versions (table_name) ON DELETE CASCADE,
    FOREIGN KEY (table_used_in_view) REFERENCES _table_data_versions (table_name) ON DELETE CASCADE
)
SQL,
);
