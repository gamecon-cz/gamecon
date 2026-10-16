<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Symbols are now written between percent signs everywhere, so `{jmeno}` becomes `%jmeno%`. The old code
// substituted only %ROCNIK%, %IBAN% and %UCET_CZ%, so any other percent sign is literal text and is doubled
// first (`%%`). `zmeneno_kdy = zmeneno_kdy` keeps this rewrite from showing up as "the CFO changed the text".
$this->q(<<<SQL
UPDATE upominka_vlastni_zneni
SET predmet     = REPLACE(
                      REPLACE(
                          REPLACE(
                              REPLACE(predmet, '%', '%%'),
                              '%%ROCNIK%%', '%ROCNIK%'),
                          '%%IBAN%%', '%IBAN%'),
                      '%%UCET_CZ%%', '%UCET_CZ%'),
    text        = REPLACE(
                      REPLACE(
                          REPLACE(
                              REPLACE(
                                  REPLACE(
                                      REPLACE(
                                          REPLACE(
                                              REPLACE(text, '%', '%%'),
                                              '%%ROCNIK%%', '%ROCNIK%'),
                                          '%%IBAN%%', '%IBAN%'),
                                      '%%UCET_CZ%%', '%UCET_CZ%'),
                                  '{jmeno}', '%jmeno%'),
                              '{vs}', '%vs%'),
                          '{dluh}', '%dluh%'),
                      '{a}', '%a%'),
    zmeneno_kdy = zmeneno_kdy
SQL,
);
