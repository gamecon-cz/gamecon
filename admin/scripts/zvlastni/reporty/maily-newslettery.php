<?php

use Gamecon\Role\Role;

require __DIR__ . '/sdilene-hlavicky.php';

$query = <<<SQL
SELECT newsletter_prihlaseni.email
FROM newsletter_prihlaseni
SQL;

$report = Report::zSql($query);
$report->tFormat(get('format'));
