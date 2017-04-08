<?php

/**
 * Třída starající se o formulář a zpracování přihlášky na Parcon
 *
 * Přihláška na Parcon je interně reprezentovaná jako spec. typ předmětu v
 * e-shopu.
 */
class ShopParcon {

  private
    $postname = 'cShopParcon', // název proměnné POST, kterou tento objekt v formuláři používá
    $predmet,
    $uzivatel;

  /**
   * @param $predmety předměty typu parcon, které má uživatel objednané
   * @param $uzivatel uživate, pro něhož se formulář zpracovává nebo vypisuje
   */
  function __construct($predmety, $uzivatel) {
    if(count($predmety) != 1) throw new Exception('Předmět typu Parcon musí existovat právě jeden');
    if($predmety[0]['kusu_uzivatele'] > 1) throw new Exception('Není možné mít Parcon přihlášen víckrát');
    $this->predmet = $predmety[0];
    $this->uzivatel = $uzivatel;
  }

  /**
   * @return vrátí html kód vnitřku formuláře (bez <form></form>)
   */
  function html() {
    $t = new XTemplate(__DIR__ . '/shop-parcon.xtpl');
    $t->assign([
      'cena'      =>  round($this->predmet['cena_aktualni']),
      'checked'   =>  $this->predmet['kusu_uzivatele'] >= 1 ? 'checked' : '',
      'postname'  =>  $this->postname,
      'ka'        =>  $this->uzivatel->pohlavi() == 'f' ? 'ka' : '',
    ]);
    if($this->uzivatel->maPravo(P_PARCON_ZDARMA)) $t->parse('parcon.zdarma');
    $t->parse('parcon');
    return $t->text('parcon');
  }

  /**
   * Zpracuje případné odeslané hodnoty z formuláře
   */
  function zpracuj() {
    if($this->predmet['kusu_uzivatele'] == 0 && post($this->postname)) {
      dbInsert('shop_nakupy', [
        'id_uzivatele'  =>  $this->uzivatel->id(),
        'id_predmetu'   =>  $this->predmet['id_predmetu'],
        'rok'           =>  ROK,
        'cena_nakupni'  =>  $this->predmet['cena_aktualni'],
      ]);
    }

    if($this->predmet['kusu_uzivatele'] >= 1 && !post($this->postname)) {
      dbDelete('shop_nakupy', [
        'id_uzivatele'  =>  $this->uzivatel->id(),
        'id_predmetu'   =>  $this->predmet['id_predmetu'],
        'rok'           =>  ROK,
      ]);
    }
  }

}
