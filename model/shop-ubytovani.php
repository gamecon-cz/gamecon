<?php

class ShopUbytovani {

  private
    $dny,     // asoc. 2D pole [den][typ] => předmět
    $typy,    // asoc. pole [typ] => předmět sloužící jako vzor daného typu
    $pnDny = 'shopUbytovaniDny',
    $pnPokoj = 'shopUbytovaniPokoj',
    $u;

  function __construct($predmety, $uzivatel) {
    $this->u = $uzivatel;
    foreach($predmety as $p) {
      $nazev = Shop::bezDne($p['nazev']);
      if(!isset($this->typy[$nazev])) $this->typy[$nazev] = $p;
      $this->dny[$p['ubytovani_den']][$nazev] = $p;
    }
  }

  function html() {
    $t = new XTemplate(__DIR__.'/shop-ubytovani.xtpl');
    $t->assign([
      'poDokonceni'   =>  $this->u->gcPrihlasen() ? '' : 'po dokončení registrace',
      'spolubydlici'  =>  dbOneCol('SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele='.$this->u->id()),
      'postnameSpolubydlici'  =>  $this->pnPokoj,
      'vyska'         =>  '1.4em',
      'ka'            =>  $ka = $this->u->pohlavi() == 'f' ? 'ka' : '',
      'uzivatele'     =>  $this->mozniUzivatele(),
      'viceScript'    =>  file_get_contents(WWW.'/soubory/doplnovani-vice.js'),
    ]);
    $this->htmlDny($t);
    // sloupce popisků
    $maxLen = 13;
    foreach($this->typy as $typ => $predmet) {
      $t->assign([
        'typ'   =>  mb_strlen($typ) > $maxLen ? mb_substr($typ, 0, $maxLen).'&hellip;' : $typ,
        'hint'  =>  $predmet['popis'],
        'cena'  =>  round($predmet['cena_aktualni']),
      ]);
      $t->parse($predmet['popis'] ? 'ubytovani.typ.hinted' : 'ubytovani.typ.normal');
      $t->parse('ubytovani.typ');
      $t->parse('ubytovani.cena');
    }
    // specifická info podle uživatele a stavu nabídky
    if(reset($this->typy)['stav'] == 3)           $t->parse('ubytovani.konec');
    if($this->u->maPravo(P_TRIKO_ZDARMA))         $t->parse('ubytovani.infoOrg');
    elseif($this->u->maPravo(P_SLEVA_AKTIVITY))   $t->parse('ubytovani.infoVypravec');
    $t->parse('ubytovani');
    return $t->text('ubytovani');
  }

  /** Zparsuje šablonu s ubytováním po dnech */
  private function htmlDny($t) {
    foreach($this->dny as $den => $typy) { // typy _v daný den_
      $ubytovan = false;
      $typVzor = reset($typy);
      $t->assign('postnameDen', $this->pnDny.'['.$den.']');
      foreach($this->typy as $typ => $rozsah) {
        $sel = '';
        if($this->ubytovan($den, $typ)) {
          $ubytovan = true;
          $sel = 'checked';
        }
        $t->assign([
          'idPredmetu'  =>  isset($this->dny[$den][$typ]) ? $this->dny[$den][$typ]['id_predmetu'] : null,
          'sel'         =>  $sel,
          'lock'        =>  !$sel && ( !$this->existujeUbytovani($den,$typ) || $this->plno($den,$typ) ) ? 'disabled' : '',
          'obsazeno'    =>  $this->obsazenoMist($den, $typ),
          'kapacita'    =>  $this->kapacita($den, $typ),
        ])->parse('ubytovani.den.typ');
      }
      $t->assign([
        'den'   =>  mb_ucfirst(substr($typVzor['nazev'], strrpos($typVzor['nazev'], ' ') + 1)),
        'sel'   =>  $ubytovan ? '' : 'checked',
        'lock'  =>  $ubytovan && $typVzor['stav'] == 3 && !$typVzor['nabizet'] ? 'disabled' : '',
      ])->parse('ubytovani.den');
    }
  }

  function zpracuj() {
    if(isset($_POST[$this->pnDny])) {
      // smazat veškeré stávající ubytování uživatele
      dbQuery('
        DELETE n.* FROM shop_nakupy n
        JOIN shop_predmety p USING(id_predmetu)
        WHERE n.id_uzivatele='.$this->u->id().' AND p.typ=2 AND n.rok='.ROK);
      // vložit jeho zaklikané věci - note: není zabezpečeno
      $q = 'INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES '."\n";
      foreach($_POST[$this->pnDny] as $predmet)
        if($predmet)
          $q.='('.$this->u->id().','.(int)$predmet.','.ROK.',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu='.(int)$predmet.'),NOW()),'."\n";
      $q = substr($q,0,-2);
      if(substr($q,-1) == ')') //hack, test že se vložila aspoň jedna položka
        dbQuery($q);
      // uložit s kým chce být na pokoji
      if($_POST[$this->pnPokoj])
        dbQueryS('UPDATE uzivatele_hodnoty SET ubytovan_s=$0 WHERE id_uzivatele='.$this->u->id(),[$_POST[$this->pnPokoj]]);
      else
        dbQuery('UPDATE uzivatele_hodnoty SET ubytovan_s=NULL WHERE id_uzivatele='.$this->u->id());
      return true;
    }
    return false;
  }

  /////////////
  // private //
  /////////////

  /** Vrátí, jestli daná kombinace den a typ je validní. */
  private function existujeUbytovani($den, $typ) {
    return isset($this->dny[$den][$typ])
      && $this->dny[$den][$typ]['nabizet'] == true;
  }

  /** Vrátí kapacitu */
  private function kapacita($den, $typ) {
    if(!isset($this->dny[$den][$typ])) return 0;
    $ub = $this->dny[$den][$typ];
    return max(0, $ub['kusu_vyrobeno']);
  }

  /** Vrátí počet obsazených míst pro daný den a typu ubytování */
  private function obsazenoMist($den, $typ) {
    return $this->kapacita($den,$typ) - $this->zbyvaMist($den,$typ);
  }

  /** Vrátí, jestli je v daný den a typ ubytování plno */
  private function plno($den, $typ) {
    return $this->zbyvaMist($den,$typ) <= 0;
  }

  /**
   * Vrátí, jestli uživatel pro tento shop má ubytování v kombinaci den, typ
   * @param int $den číslo dne jak je v databázi
   * @param string $typ typ ubytování ve smyslu názvu z DB bez posledního slova
   * @return bool je ubytován?
   */
  private function ubytovan($den, $typ) {
    return isset($this->dny[$den][$typ])
      && $this->dny[$den][$typ]['kusu_uzivatele']>0;
  }

  /** Vrátí počet volných míst */
  private function zbyvaMist($den, $typ) {
    if(!isset($this->dny[$den][$typ])) return 0;
    $ub = $this->dny[$den][$typ];
    return max(0, $ub['kusu_vyrobeno'] - $ub['kusu_prodano']);
  }

  /**
   * Vrátí seznam uživatelů ve formátu Jméno Příjmení (Login) tak aby byl zpra-
   * covatelný neajaxovým našeptávátkem (čili ["položka","položka",...])
   */
  protected function mozniUzivatele() {
    $a = [];
    $o = dbQuery('
      SELECT CONCAT(jmeno_uzivatele," ",prijmeni_uzivatele," (",login_uzivatele,")")
      FROM uzivatele_hodnoty
      WHERE jmeno_uzivatele != "" AND prijmeni_uzivatele != "" AND id_uzivatele != $1
    ', [$this->u->id()]);
    while($u = mysqli_fetch_row($o)) $a[] = $u[0];
    return json_encode($a);
  }

}
