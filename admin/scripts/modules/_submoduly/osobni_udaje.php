<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Uzivatel\Pohlavi;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as Sql;

class OsobniUdajeTabulka
{

  public static function osobniUdajeTabulkaZ(
    Uzivatel|null $uzivatel
  ) {
    if (!$uzivatel)
      return "";
    $x = new XTemplate(__DIR__ . '/osobni_udaje.xtpl');

    // form s osobními údaji
    $udaje         = [
      Sql::LOGIN_UZIVATELE        => 'Přezdívka',
      Sql::JMENO_UZIVATELE        => 'Jméno',
      Sql::PRIJMENI_UZIVATELE     => 'Příjmení',
      Sql::POHLAVI                => 'Pohlaví',
      Sql::ULICE_A_CP_UZIVATELE   => 'Ulice',
      Sql::MESTO_UZIVATELE        => 'Město',
      Sql::PSC_UZIVATELE          => 'PSČ',
      Sql::TELEFON_UZIVATELE      => 'Telefon',
      Sql::DATUM_NAROZENI         => 'Narozen' . $uzivatel->koncovkaDlePohlavi(),
      Sql::EMAIL1_UZIVATELE       => 'E-mail',
      // další informace nutné pro ubytování
      Sql::TYP_DOKLADU_TOTOZNOSTI => 'Typ dokladu',
      Sql::OP                     => 'Číslo dokladu',
      Sql::STATNI_OBCANSTVI       => 'Státní občanství',
    ];
    $r             = dbOneLine('SELECT ' . implode(',', array_keys($udaje)) . ' FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uzivatel->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);

    foreach ($udaje as $sloupec => $nazev) {
      $hodnota = $r[$sloupec];
      if ($sloupec === 'op') {
        $hodnota = $uzivatel->cisloOp(); // desifruj cislo obcanskeho prukazu
      }
      $zobrazenaHodnota = $hodnota;
      $vstupniHodnota   = $hodnota;
      $vyber            = [];
      $popisek          = '';
      if ($sloupec === Sql::POHLAVI) {
        $vyber            = Pohlavi::seznamProSelect();
        $zobrazenaHodnota = $vyber[$r[Sql::POHLAVI]] ?? '';
      }
      if ($sloupec === 'telefon_uzivatele') {
        $zobrazenaHodnota = $uzivatel->telefon();
      }
      if ($sloupec === 'datum_narozeni') {
        $popisek = sprintf('Věk na začátku Gameconu %d let', vekNaZacatkuLetosnihoGameconu($datumNarozeni));
      }
      $x->assign([
        'nazev'            => $nazev,
        'sloupec'          => $sloupec,
        'vstupniHodnota'   => $vstupniHodnota,
        'zobrazenaHodnota' => $zobrazenaHodnota,
        'vyber'            => $vyber,
        'popisek'          => $popisek,
      ]);
      if ($popisek) {
        $x->parse('udaje.udaj.nazevSPopiskem');
      } else {
        $x->parse('udaje.udaj.nazevBezPopisku');
      }
      if ($sloupec === Sql::POHLAVI) {
        foreach ($vyber as $optionValue => $optionText) {
          $x->assign([
            'optionValue'    => $optionValue,
            'optionText'     => $optionText,
            'optionSelected' => $vstupniHodnota === $optionValue
              ? 'selected'
              : '',
          ]);
          $x->parse('udaje.udaj.select.option');
        }
        $x->parse('udaje.udaj.select');
      } else {
        $x->parse('udaje.udaj.input');
      }
      $x->parse('udaje.udaj');
    }
    $x->parse('udaje');
    return $x->text('udaje');
  }
}
