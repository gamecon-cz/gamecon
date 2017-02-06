<?php

class Platby {

  const
    DNI_ZPET = 7; // kolik dní zpět se mají načítat platby při kontrole nově došlých plateb

  /**
   * Načte a uloží nové platby z FIO, vrátí zaúčtované platby
   */
  static function nactiNove() {
    $maxId = dbOneCol('SELECT MAX(fio_id) FROM platby');
    $vysledek = [];
    foreach(FioPlatba::zPoslednichDni(self::DNI_ZPET) as $p) {
      if(
        bccomp($p->id(), $maxId) > 0 &&
        is_numeric($p->vs()) &&
        ($u = Uzivatel::zId($p->vs())) &&
        $u->gcPrihlasen() &&
        $p->castka() > 0 // TODO umožnit nebo zakázat záporné platby (vs. není přihlášen na GC vs. automatický odečet vrácením na účet)
      ) {
        dbInsert('platby', [
          'id_uzivatele'  =>  $u->id(),
          'fio_id'        =>  $p->id(),
          'castka'        =>  $p->castka(),
          'rok'           =>  ROK,
          'provedl'       =>  Uzivatel::SYSTEM,
          'poznamka'      =>  strlen($p->zprava()) > 4 ? $p->zprava() : null,
        ]);
        $vysledek[] = $p;
      }
    }
    return $vysledek;
  }

}
