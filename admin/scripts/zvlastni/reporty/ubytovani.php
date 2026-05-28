<?php

$r=Report::zSql('
  SELECT u.id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, 
    SUBSTR(p.nazev,1,10) as typ, GROUP_CONCAT(DISTINCT IFNULL(ub.pokoj,"")) as pokoj, MIN(p.ubytovani_den) as prnvi_noc, MAX(p.ubytovani_den) as posledni_noc, ubytovan_s 
  FROM uzivatele_hodnoty u
  JOIN r_uzivatele_zidle z ON(u.id_uzivatele=z.id_uzivatele AND z.id_zidle='.Z_PRIHLASEN.') -- přihlášení na gc
  LEFT JOIN shop_nakupy n ON(n.id_uzivatele=u.id_uzivatele AND rok='.ROK.') -- nákupy tento rok
  LEFT JOIN shop_predmety p ON(p.id_predmetu=n.id_predmetu)                 -- info o předmětech k nákupům
  LEFT JOIN ubytovani ub on(ub.id_uzivatele=u.id_uzivatele and ub.rok=2013) -- info o číslech pokoje
  WHERE p.typ=2 OR ISNULL(p.typ)                                            -- jen řádky s žádným nákupem nebo s ubytováním
  GROUP BY u.id_uzivatele');
$r->tCsv();
//$r->tHtml();

?>
