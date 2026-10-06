CREATE TABLE user_token_version
(
    user_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id),
    CONSTRAINT FK_user_token_version_user FOREIGN KEY (user_id) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci;
