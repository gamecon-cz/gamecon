<?php

class AktivitaTagyTest extends GcDbTest {

  static $initData = '
    # akce_seznam
    id_akce, patri_pod
    1,       0
    2,       10
    3,       10
    4,       20
    5,       20

    # tagy
    id, nazev
    1,  Tak určitě

    # akce_tagy
    id_akce, id_tagu
    4,       1
    5,       1
    1,       1
  ';

  function aktivity() {
    return [
      'obyčejná aktivita, nastavení více štítků'    =>  [1, 1, ['První', 'druhý']],
      'obyčejná aktivita, nastavení žádných štítků' =>  [1, 1, []],
      'skupina, nastavení více štítků'              =>  [2, 3, ['První', 'druhý']],
      'skupina, nastavení žádných štítků'           =>  [4, 5, []],
      'skupina, druhá aktivita'                     =>  [3, 2, ['První', 'druhý']],
    ];
  }

  /**
   * @dataProvider aktivity
   */
  function testNastaveni($idNastavovaneAktivity, $idCteneAktivity, $nastaveneTagy) {
    $a = Aktivita::zId($idNastavovaneAktivity);
    $a->nastavTagy($nastaveneTagy);
    $b = Aktivita::zId($idCteneAktivity);
    $this->assertEquals($nastaveneTagy, $b->tagy(),
      "Tagy nastavené aktivitě $idNastavovaneAktivity musí odpovídat tagům přečteným z aktivity $idCteneAktivity."
    );
  }

  /**
   * @dataProvider aktivity
   */
  function testKopiePriInstanciaci($idAktivity, $_, $tagy) {
    $a = Aktivita::zId($idAktivity);
    $a->nastavTagy($tagy);
    $b = $a->instanciuj();
    $this->assertEquals($tagy, $b->tagy(),
      "Tagy se musí propsat i do nově vytvořené instance"
    );
  }

}
