<?php

namespace Gamecon\Tests\Aktivity;

use Gamecon\Tests\Db\UzivatelDbTest;
use Gamecon\Aktivita\Aktivita;

class AktivitaTymovePrihlasovaniTest extends UzivatelDbTest
{
  private $ctvrtfinale;
  private $semifinaleA;
  private $semifinaleB;
  private $finale;
  private $tymlidr;
  private $clen1;
  private $clen2;

  protected static $initData = '
    # akce_seznam
    id_akce, dite,  stav, typ, teamova, kapacita, team_min, team_max, zacatek,          konec
    1,       "2,3", 1,    1,   1,       3,        2,        3,        2099-01-01 08:00, 2099-01-01 14:00
    2,       4,     4,    1,   0,       3,        NULL,     NULL,     2099-01-01 08:00, 2099-01-01 14:00
    3,       4,     4,    1,   0,       3,        NULL,     NULL,     2099-01-01 15:00, 2099-01-01 16:00
    4,       NULL,  4,    1,   0,       3,        NULL,     NULL,     2099-01-01 08:00, 2099-01-01 14:00
    5,       NULL,  1,    1,   0,       3,        NULL,     NULL,     2099-01-01 08:00, 2099-01-01 14:00
  ';

  public function setUp(): void
  {
    parent::setUp();

    $this->ctvrtfinale = Aktivita::zId(1);
    $this->semifinaleA = Aktivita::zId(2);
    $this->semifinaleB = Aktivita::zId(3);
    $this->finale = Aktivita::zId(4);

    $this->tymlidr = self::prihlasenyUzivatel();
    $this->clen1 = self::prihlasenyUzivatel();
    $this->clen2 = self::prihlasenyUzivatel();
  }

  public function testOdhlaseniPosledniho()
  {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, 2, [$this->semifinaleA, $this->finale]);

    self::assertEquals(2, $this->ctvrtfinale->rawDb()['kapacita']);

    // počet míst se obnoví
    $this->ctvrtfinale->odhlas($this->tymlidr);
    $this->ctvrtfinale->odhlas($this->clen1);
    self::assertEquals(3, $this->ctvrtfinale->rawDb()['kapacita']);

    // opětovné přihlášení se chová jako u týmovky, tj. jako přihlášení týmlídra
    $this->ctvrtfinale->prihlas($this->tymlidr);
    try {
      $this->ctvrtfinale->prihlas($this->clen1);
      self::fail('Aktivita musí být opět zamčená.');
    } catch (\Exception $e) {
    }
  }

  public function testOdhlaseniPredPotvrzenim()
  {
    $this->ctvrtfinale->prihlas($this->tymlidr);

    $this->ctvrtfinale->odhlas($this->tymlidr);
    $this->ctvrtfinale->prihlas($this->clen1);
    self::assertTrue($this->ctvrtfinale->prihlasen($this->clen1));
  }

  public function testOmezeniKapacity()
  {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, 2, [$this->semifinaleA, $this->finale]);
    $this->expectException(\Exception::class);
    $this->expectExceptionMessageMatches('~ plná~');
    $this->ctvrtfinale->prihlas($this->clen2);
  }

  function nastaveniKapacity() {
    return [
      [null, 3],
      [2,    2],
    ];
  }

  /**
   * @dataProvider nastaveniKapacity
   */
  function testZmenaKapacity($nastaveno, $ocekavano) {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, $nastaveno, [$this->semifinaleA, $this->finale]);
    $this->ctvrtfinale->refresh();
    $this->assertEquals($ocekavano, $this->ctvrtfinale->rawDb()['kapacita']);
  }

  public function testPrihlaseniDalsiho()
  {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, 3, [$this->semifinaleA, $this->finale]);
    $this->ctvrtfinale->prihlas($this->clen2);

    // TODO nutnost refreshování vyplývá z chybějících identity map, spravit
    $this->ctvrtfinale->refresh();
    $this->semifinaleA->refresh();
    $this->semifinaleB->refresh();
    $this->finale->refresh();

    self::assertTrue($this->ctvrtfinale->prihlasen($this->clen2));
    self::assertTrue($this->semifinaleA->prihlasen($this->clen2));
    self::assertTrue($this->finale->prihlasen($this->clen2));

    self::assertFalse($this->semifinaleB->prihlasen($this->clen2));
  }

  public function testPrihlaseniTymlidra()
  {
    // aktivita se zamče
    $this->ctvrtfinale->prihlas($this->tymlidr);
    try {
      $this->ctvrtfinale->prihlas($this->clen1);
      self::fail('Aktivita musí být zamčená a přihlášení dalšího člověka musí selhat.');
    } catch (\Exception $e) {
    }

    // je přihlášen na první kolo
    self::assertTrue($this->ctvrtfinale->prihlasen($this->tymlidr));

    // není přihlášen na další kola
    foreach ($this->ctvrtfinale->dalsiKola() as $kolo) {
      foreach ($kolo as $varianta) {
        self::assertFalse($varianta->prihlasen($this->tymlidr));
      }
    }
  }

  public function testPrihlaseniTymu()
  {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([$this->clen1], null, null, [$this->semifinaleA, $this->finale]);

    // TODO nutnost refreshování vyplývá z chybějících identity map, spravit
    $this->ctvrtfinale->refresh();
    $this->semifinaleA->refresh();
    $this->semifinaleB->refresh();
    $this->finale->refresh();

    foreach ([$this->tymlidr, $this->clen1] as $hrac) {
      self::assertTrue($this->ctvrtfinale->prihlasen($hrac));
      self::assertTrue($this->semifinaleA->prihlasen($hrac));
      self::assertTrue($this->finale->prihlasen($hrac));

      self::assertFalse($this->semifinaleB->prihlasen($hrac));
    }
  }

  public function testPrihlaseniTymuOpakovaneNelze()
  {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->ctvrtfinale->prihlasTym([], null, null, [$this->semifinaleA, $this->finale]);
    // TODO co když by se přihlašoval na jiné čtvrtfinále?
    $this->expectException(\Exception::class);
    $this->ctvrtfinale->prihlasTym([], null, null, [$this->semifinaleB, $this->finale]);
  }

  /**
   * @dataProvider spatnaVolbaDalsichKol
   */
  public function testSpatnaVolbaDalsichKolNelze(array $dalsiKolaIds)
  {
    $this->ctvrtfinale->prihlas($this->tymlidr);
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Nepovolený výběr dalších kol.');
    $this->ctvrtfinale->prihlasTym([], null, null, array_map(function ($id) {
      return Aktivita::zId($id);
    }, $dalsiKolaIds));
  }

  public function spatnaVolbaDalsichKol(): array
  {
    return [
      'nevybrání ničeho' => [[]],
      'vybrání i čtvrtfinále' => [[1, 2, 4]],
      'vybrání dvou semifinále' => [[2, 3, 4]],
      'nevybrání finále' => [[2]],
      'špatné pořadí' => [[4, 2]],
      'smetí navíc' => [[2, 4, 5]],
    ];
  }


  // TODO další scénáře:
  //  nevalidní ne-první člen
  //    všechno se rollbackne
  //    (že přihlášení jednoho člověka háže výjimku např. při překrytí tady netřeba testovat)

}
