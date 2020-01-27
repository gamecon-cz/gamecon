<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE google_api_user_tokens (id SERIAL, token JSON NOT NULL, user_id INTEGER NOT NULL PRIMARY KEY)
SQL
);
