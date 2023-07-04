<?php

use Gamecon\XTemplate\XTemplate;
use Gamecon\Uzivatel\Pohlavi;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as Sql;

include __DIR__ . '/osobni_udaje_ovladac.php';

class OsobniUdajeTabulka
{
  private static $udajeSOp = [
    Sql::LOGIN_UZIVATELE        => 'Přezdívka',
    Sql::JMENO_UZIVATELE        => 'Jméno',
    Sql::PRIJMENI_UZIVATELE     => 'Příjmení',
    Sql::POHLAVI                => 'Pohlaví',
    Sql::ULICE_A_CP_UZIVATELE   => 'Ulice',
    Sql::MESTO_UZIVATELE        => 'Město',
    Sql::PSC_UZIVATELE          => 'PSČ',
    Sql::TELEFON_UZIVATELE      => 'Telefon',
    Sql::DATUM_NAROZENI         => 'Narozen',
    Sql::EMAIL1_UZIVATELE       => 'E-mail',
    // další informace nutné pro ubytování
    Sql::TYP_DOKLADU_TOTOZNOSTI => 'Typ dokladu',
    Sql::OP                     => 'Číslo dokladu',
    Sql::STATNI_OBCANSTVI       => 'Státní občanství',
  ];

  private static $udaje = [
    Sql::LOGIN_UZIVATELE        => 'Přezdívka',
    Sql::JMENO_UZIVATELE        => 'Jméno',
    Sql::PRIJMENI_UZIVATELE     => 'Příjmení',
    Sql::POHLAVI                => 'Pohlaví',
    Sql::TELEFON_UZIVATELE      => 'Telefon',
    Sql::DATUM_NAROZENI         => 'Narozen',
    Sql::EMAIL1_UZIVATELE       => 'E-mail',
  ];

  public static function osobniUdajeTabulkaZ(
    Uzivatel|null $uzivatel,
    bool $op = true,
    bool $programOdkaz = false,
  ) {
    if (!$uzivatel)
      return "";
    $x = new XTemplate(__DIR__ . '/osobni_udaje.xtpl');

    // form s osobními údaji
    $udaje         = $op ? OsobniUdajeTabulka::$udajeSOp : OsobniUdajeTabulka::$udaje;
    $r             = dbOneLine('SELECT ' . implode(',', array_keys($udaje)) . ' FROM uzivatele_hodnoty WHERE id_uzivatele = ' . $uzivatel->id());
    $datumNarozeni = new DateTimeImmutable($r['datum_narozeni']);

    foreach ($udaje as $sloupec => $nazev) {
      $hodnota = $r[$sloupec];
      if ($sloupec === Sql::OP) {
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
      if ($sloupec === Sql::TELEFON_UZIVATELE) {
        $zobrazenaHodnota = $uzivatel->telefon();
      }
      if ($sloupec === Sql::DATUM_NAROZENI) {
        $popisek = sprintf('Věk na začátku Gameconu %d let', vekNaZacatkuLetosnihoGameconu($datumNarozeni));
        $nazev = $nazev . $uzivatel->koncovkaDlePohlavi();
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

    if ($programOdkaz)
      $x->parse('udaje.programOdkaz');

    $x->parse('udaje');
    return $x->text('udaje');
  }
}
