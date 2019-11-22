<?php

if (!$u->maPravo(104)) {
  die('Nemáš právo 104 nutné k zobrazení reportů.');
}

$CSV_SEP = ';'; //separátor pro csv soubory
$NAZEV_SKRIPTU = $podstranka; //převzato z index.php

$skript = $NAZEV_SKRIPTU;
if ($skript === 'quick') {
  $skript .= '-' . get('id');
}
$format = get('format') ?: 'html';

$nyni = new DateTime();

dbQuery(<<<SQL
INSERT INTO reporty_log_pouziti(id_reportu, id_uzivatele, format, cas_pouziti, casova_zona)
VALUES ((SELECT id FROM reporty WHERE skript = $1), $2, $3, $4, $5)
SQL
  , [$skript, $u->id(), $format, $nyni->format('Y-m-d H:i:s'), $nyni->getTimezone()->getName()]
);
