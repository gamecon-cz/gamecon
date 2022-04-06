<?php
require __DIR__ . '/sdilene-hlavicky.php';

// hack proklikávací form na stránku slučování
$form =  <<<'EOT'
  CONCAT('
  <form method="post" action="../finance/slucovani" style="display:inline">
  <input type="hidden" name="u1" value="', a.id_uzivatele, '">
  <input type="hidden" name="u2" value="', b.id_uzivatele, '">
  <input type="submit" name="pripravit" value="sloučit">
  </form>')
EOT;

$pole = [
  'id_uzivatele as id',
  'login_uzivatele as login',
  'jmeno_uzivatele as jméno',
  'prijmeni_uzivatele as příjmení',
  'email1_uzivatele as mail',
  //'email2_uzivatele as "alt. mail"',
  'datum_narozeni as narození',
  'ulice_a_cp_uzivatele as ulice',
];

$r = Report::zSql('
  SELECT
    '.array_uprint($pole, function($e){ return 'a.'.$e; }, ', ').',
    '.$form.' as " ",
    '.array_uprint($pole, function($e){ return 'b.'.$e; }, ', ').'
  FROM uzivatele_hodnoty a
  JOIN uzivatele_hodnoty b ON((
    (a.jmeno_uzivatele = b.jmeno_uzivatele AND a.prijmeni_uzivatele = b.prijmeni_uzivatele AND a.jmeno_uzivatele != "") AND
    (a.datum_narozeni = b.datum_narozeni AND a.datum_narozeni != 0 AND a.datum_narozeni != "1970-01-01" AND a.pohlavi = b.pohlavi)
  ) AND a.id_uzivatele < b.id_uzivatele)
');

$r->tHtml();
