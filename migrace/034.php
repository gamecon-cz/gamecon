<?php

// Upravime databazi aby se nam tam zasifrovane veci vesly
$this->q("
ALTER TABLE `uzivatele_hodnoty`
CHANGE `op` `op` varchar(4096) COLLATE 'utf8_czech_ci' NOT NULL COMMENT 'zašifrované číslo OP' AFTER `pomoc_vice`;");

// Natahnem si puvoadni obcanske prukazy
$o = dbQuery("
  SELECT `id_uzivatele`, `op`
  FROM `uzivatele_hodnoty`
  WHERE `op` != '' AND `op` IS NOT NULL");

while ($r = mysqli_fetch_assoc($o)) {

  // Zasifrujeme je
  $sifrovany_op = Sifrovatko::zasifruj($r['op']);
  // A provedeme update
  dbQuery("UPDATE `uzivatele_hodnoty`
    SET `op` = '{$sifrovany_op}'
    WHERE `id_uzivatele` = {$r['id_uzivatele']}");

}
