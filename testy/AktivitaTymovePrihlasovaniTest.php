<?php

class AktivitaTymovePrihlasovaniTest extends GcDbTest {

  static $initData = '
    # akce_seznam
    id_akce, dite,  stav, typ, teamova, team_min, team_max, zacatek,          konec
    1,       "2,3", 1,    1,   1,       2,        3,        2099-01-01 08:00, 2099-01-01 14:00
    2,       4,     4,    1,   0,       NULL,     NULL,     2099-01-01 08:00, 2099-01-01 14:00
    3,       4,     4,    1,   0,       NULL,     NULL,     2099-01-01 15:00, 2099-01-01 16:00
    4,       NULL,  4,    1,   0,       NULL,     NULL,     2099-01-01 08:00, 2099-01-01 14:00
  ';

  function setUp() {
    parent::setUp();

    $this->ctvrtfinale = Aktivita::zId(1);
    $this->semifinaleA = Aktivita::zId(2);
    $this->semifinaleB = Aktivita::zId(3);
    $this->finale = Aktivita::zId(4);

    $this->tymlidr = self::prihlasenyUzivatel();
    $this->clen1 = self::prihlasenyUzivatel();
    $this->clen2 = self::prihlasenyUzivatel();
  }

  function testOdhlaseniPosledniho() {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, 2, [$this->semifinaleA, $this->finale]);

    $this->assertEquals(2, $this->ctvrtfinale->rawDb()['kapacita']);

    // počet míst se obnoví
    $this->ctvrtfinale->odhlas($this->tymlidr);
    $this->ctvrtfinale->odhlas($this->clen1);
    $this->assertEquals(3, $this->ctvrtfinale->rawDb()['kapacita']);

    // opětovné přihlášení se chová jako u týmovky, tj. jako přihlášení týmlídra
    $this->ctvrtfinale->prihlas($this->tymlidr);
    try {
      $this->ctvrtfinale->prihlas($this->clen1);
      $this->fail('Aktivita musí být opět zamčená.');
    } catch(Exception $e) {}
  }

  function testOdhlaseniPredPotvrzenim() {
    $this->ctvrtfinale->prihlas($this->tymlidr);

    $this->ctvrtfinale->odhlas($this->tymlidr);
    $this->ctvrtfinale->prihlas($this->clen1);
    $this->assertTrue($this->ctvrtfinale->prihlasen($this->clen1));
  }

  /**
   * @expectedException Exception
   * @expectedExceptionMessageRegExp / plná/
   */
  function testOmezeniKapacity() {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, 2, [$this->semifinaleA, $this->finale]);
    $this->ctvrtfinale->prihlas($this->clen2);
  }

  function testPrihlaseniDalsiho() {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, 3, [$this->semifinaleA, $this->finale]);
    $this->ctvrtfinale->prihlas($this->clen2);

    // TODO nutnost refreshování vyplývá z chybějících identity map, spravit
    $this->ctvrtfinale->refresh();
    $this->semifinaleA->refresh();
    $this->semifinaleB->refresh();
    $this->finale->refresh();

    $this->assertTrue($this->ctvrtfinale->prihlasen($this->clen2));
    $this->assertTrue($this->semifinaleA->prihlasen($this->clen2));
    $this->assertTrue($this->finale->prihlasen($this->clen2));

    $this->assertFalse($this->semifinaleB->prihlasen($this->clen2));
  }

  function testPrihlaseniTymlidra() {
    // aktivita se zamče
    $this->ctvrtfinale->prihlas($this->tymlidr);
    try {
      $this->ctvrtfinale->prihlas($this->clen1);
      $this->fail('Aktivita musí být zamčená a přihlášení dalšího člověka musí selhat.');
    } catch(Exception $e) {}

    // je přihlášen na první kolo
    $this->assertTrue($this->ctvrtfinale->prihlasen($this->tymlidr));

    // není přihlášen na další kola
    foreach($this->ctvrtfinale->dalsiKola() as $kolo) {
      foreach($kolo as $varianta) {
        $this->assertFalse($varianta->prihlasen($this->tymlidr));
      }
    }
  }

  function testPrihlaseniTymu() {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, null, [$this->semifinaleA, $this->finale]);

    // TODO nutnost refreshování vyplývá z chybějících identity map, spravit
    $this->ctvrtfinale->refresh();
    $this->semifinaleA->refresh();
    $this->semifinaleB->refresh();
    $this->finale->refresh();

    foreach([$this->tymlidr, $this->clen1] as $hrac) {
      $this->assertTrue($this->ctvrtfinale->prihlasen($hrac));
      $this->assertTrue($this->semifinaleA->prihlasen($hrac));
      $this->assertTrue($this->finale->prihlasen($hrac));

      $this->assertFalse($this->semifinaleB->prihlasen($hrac));
    }
  }

  // TODO další scénáře:
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

}
