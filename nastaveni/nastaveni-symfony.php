<?php

/** @var $systemoveNastaveni Gamecon\SystemoveNastaveni\SystemoveNastaveni */

if (empty($_ENV['DATABASE_URL'])) {
    $_ENV['DATABASE_URL'] = $systemoveNastaveni->databazoveNastaveni()->symfonyDatabaseUrl();
}
