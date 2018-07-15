<?php

class UzivatelMaVolnoTest extends GcDbTest {

  static $initData = '
    # akce_seznam
    id_akce, stav, typ, rok,     zacatek,          konec
    1,       1,    1,   '.ROK.', 2000-01-01 16:00, 2000-01-01 18:00
    2,       1,    1,   '.ROK.', 2000-01-01 10:00, 2000-01-01 12:00
  ';

  static $uzivatel;

  static function setUpBeforeClass() {
    parent::setUpBeforeClass();
    self::$uzivatel = self::prihlasenyUzivatel();
    Aktivita::zId(1)->prihlas(self::$uzivatel);
    Aktivita::zId(2)->prihlas(self::$uzivatel);
  }

  function ruzneVarianty() {
    return [
      ['17:00', '19:00', null, false],
      ['15:00', '19:00', null, false],
      ['08:00', '19:00', null, false],
      ['18:00', '19:00', null, true],
      ['15:00', '16:00', null, true],
      ['15:00', '19:00', 1,    true],
    ];
  }

  function testZadneAktivity() {
    $this->assertTrue(
      self::prihlasenyUzivatel()->maVolno(
        new DateTime('2000-01-01 00:00'),
        new DateTime('2000-01-01 24:00')
      )
    );
  }

  /**
   * @dataProvider ruzneVarianty
   */
  function testRuzneVarianty($od, $do, $ignorovatId, $vysledek) {
    $this->assertSame(
      $vysledek,
      self::$uzivatel->maVolno(
        new DateTime('2000-01-01 ' . $od),
        new DateTime('2000-01-01 ' . $do),
        Aktivita::zId($ignorovatId)
      )
    );
  }

}
