<?php

if (!$u->maPravo(104)) {
  die('Nemáš právo 104 nutné k zobrazení reportů.');
}

$CSV_SEP = ';'; //separátor pro csv soubory
$NAZEV_SKRIPTU = $podstranka; //převzato z index.php

dbQuery(<<<SQL
INSERT INTO log_pouziti_reportu(id_univerzalniho_reportu, id_uzivatele)
VALUES ((SELECT id FROM univerzalni_reporty WHERE skript = $1), $2)
SQL
  , [$NAZEV_SKRIPTU, $u->id()]
);
