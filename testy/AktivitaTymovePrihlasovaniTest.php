<?php

class AktivitaTymovePrihlasovaniTest extends GcDbTest {

  static $initData = '
    # akce_seznam
    id_akce, dite,  stav, typ, teamova, zacatek,          konec
    1,       "2,3", 1,    1,   1,       2099-01-01 08:00, 2099-01-01 14:00
    2,       4,     4,    1,   0,       2099-01-01 08:00, 2099-01-01 14:00
    3,       4,     4,    1,   0,       2099-01-01 15:00, 2099-01-01 16:00
    4,       NULL,  4,    1,   0,       2099-01-01 08:00, 2099-01-01 14:00
  ';

  function setUp() {
    parent::setUp();

    $this->ctvrtfinale = Aktivita::zId(1);
    $this->semifinaleA = Aktivita::zId(2);
    $this->semifinaleB = Aktivita::zId(3);
    $this->finale = Aktivita::zId(4);

    $this->tymlidr = self::prihlasenyUzivatel();
    $this->clen1 = self::prihlasenyUzivatel();
  }

  function testPrihlaseniTymlidra() {
    // aktivita se zamče
    $this->ctvrtfinale->prihlas($this->tymlidr);
    try {
      $this->ctvrtfinale->prihlas($this->clen1);
      $this->fail('Aktivita musí být zamčená a přihlášení dalšího člověka musí selhat.');
    } catch(Exception $e) {}

    // je přihlášen na první kolo
    // TODO refresh?
    $this->assertTrue($this->ctvrtfinale->prihlasen($this->tymlidr));

    // není přihlášen na další kola
    foreach($this->ctvrtfinale->dalsiKola() as $kolo) {
      foreach($kolo as $varianta) {
        $this->assertFalse($varianta->prihlasen($this->tymlidr));
      }
    }
  }

  // tým:
  //  validní všechno
  //    počet míst se nastaví
  //    týmlídr je přihlášen na vybraná kola
  //    každý spoluhráč je přihlášen na vybraná kola
  //  nevalidní ne-první člen
  //    všechno se rollbackne
  //    (že přihlášení jednoho člověka háže výjimku např. při překrytí tady netřeba testovat)
  //  nevalidní volba kol - různé faily (testovat zde, nebo nějak konkrétní metodu?)
  //    volba úplně nesmyslů
  //    vynechání kola
  //    vybrání dvou aktivit stejného kola
  //    vybrání korektně a něco navíc

  /**
   * @doesNotPerformAssertions
   */
  function testOdhlaseniPredPotvrzenim() {
    $this->ctvrtfinale->prihlas($this->tymlidr);

    $this->ctvrtfinale->odhlas($this->tymlidr);
    // TODO refresh?
    $this->ctvrtfinale->prihlas($this->clen1); // již projde
  }

  function testOdhlaseniPosledniho() {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, null, [$this->semifinaleA, $this->finale]);

    // počet míst se obnoví
    // opětovné přihlášení se chová jako u týmovky, tj. jako přihlášení týmlídra
  }

}
