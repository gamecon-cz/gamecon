<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The connection to an AI's API, set by a developer in Dev → Připojení AI (Gamecon\Ai\PripojeniAi), one row
// per provider (Gamecon\Ai\PoskytovatelAi), at most one of them used (pouzivat).
// - zasifrovany_token: the API token encrypted with SECRET_CRYPTO_KEY (Sifrovatko), a dump of the table gives
//   nobody the token.
// - prostredi: the database the token was entered in (DB_NAME, a name of at most 64 characters). A copy of
//   the database (ostrá → beta, a preview, a local import) brings the row along, the token is not used there:
//   beta would send texts on the account of ostrá.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS ai_pripojeni
(
    poskytovatel      VARCHAR(32)         NOT NULL,
    zasifrovany_token TEXT                NOT NULL,
    model             VARCHAR(255)                 DEFAULT NULL,
    prostredi         VARCHAR(64)         NOT NULL,
    pouzivat          TINYINT(1)          NOT NULL DEFAULT 0,
    zmenil            BIGINT(20) UNSIGNED          DEFAULT NULL,
    zmeneno_kdy       TIMESTAMP           NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (poskytovatel),
    FOREIGN KEY (zmenil) REFERENCES uzivatele_hodnoty (id_uzivatele) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);

// Where a batch went and where from:
// - poskytovatel_ai: a batch ID means something to its provider only, after a switch of the provider the batches
//   of the former one are asked with its own token. The batches sent so far went to Anthropic, the only provider before.
// - prostredi_ai: the database that sent it (DATABASE()). A copy of the database (ostrá → beta) brings the rows along,
//   the copy must not cancel the batches the original waits for, even with a token of the same account.
$this->q(<<<'SQL'
ALTER TABLE akce_korektura
    ADD COLUMN IF NOT EXISTS poskytovatel_ai VARCHAR(32) NULL DEFAULT NULL AFTER id_davky_ai,
    ADD COLUMN IF NOT EXISTS prostredi_ai    VARCHAR(64) NULL DEFAULT NULL AFTER poskytovatel_ai
SQL,
);
$this->q(<<<'SQL'
UPDATE akce_korektura
SET poskytovatel_ai = COALESCE(poskytovatel_ai, 'anthropic'), prostredi_ai = COALESCE(prostredi_ai, DATABASE())
WHERE id_davky_ai IS NOT NULL
SQL,
);
