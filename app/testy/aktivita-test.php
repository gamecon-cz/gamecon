<?php

class AktivitaTest extends Test {

  function testOdhlaseniZTeamu() {
    $z = Aktivita::zId(1217);
    $s = Aktivita::zId(1211);
    $f = Aktivita::zId(1213);
    $u = Uzivatel::zId(1394);
    $z->prihlas($u);
    $s->prihlas($u, Aktivita::STAV);
    $f->prihlas($u, Aktivita::STAV);
    $this->assert($z->prihlasen($u));
    $this->assert($s->prihlasen($u));
    $this->assert($f->prihlasen($u));
    $z->odhlas($u);
    $z->otoc();
    $s->otoc();
    $f->otoc();
    $this->assert(!$z->prihlasen($u));
    $this->assert(!$s->prihlasen($u));
    $this->assert(!$f->prihlasen($u));
  }

  function testVyberTeamu() {
    $z = Aktivita::zId(1217);
    $s = Aktivita::zId(1211);
    $f = Aktivita::zId(1213);
    $u = Uzivatel::zId(1394);
    $z->prihlas($u);
    $_POST = [
      'aTeamFormNazev'    =>  'Testovka',
      Aktivita::KOLA      =>  [ 1 => $s->id(), 2 => $f->id() ],
      'aTeamForm'         =>  [ '', '', '', '', '', '', '-1' ],
      'aTeamFormAktivita' =>  $z->id(),
    ];
    //$this->assertNotException(function()use($z){ $z->vyberTeamuZpracuj(); });
    $z->vyberTeamuZpracuj($u);
    //var_dump(dbOneLine('select * from akce_seznam where id_akce = 1217'));
    $z->otoc();
    $s->otoc();
    $f->otoc();
    $this->assert($z->prihlasen($u));
    $this->assert($s->prihlasen($u));
    $this->assert($f->prihlasen($u));
  }


}
