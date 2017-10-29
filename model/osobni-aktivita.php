<?php

/**
 * Typ aktivit (programová linie)
 * 
 * JanPo
 */
class OsobniAktivita extends Aktivita {

  static function getAktivityUzivatele($id) {
    return self::zWhere('WHERE id_uzivatele = $1', [$id], 'ORDER BY zacatek ASC');
  }
  
  /**
   * Vrátí iterátor s aktivitami podle zadané where klauzule. Alias tabulky
   * akce_seznam je 'a'.
   * @param $where obsah where klauzule (bez úvodního klíč. slova WHERE)
   * @param $args volitelné pole argumentů pro dbQueryS()
   * @param $order volitelně celá klauzule ORDER BY včetně klíč. slova
   * @todo třída která obstará reálný iterátor, nejenom obalení pole (nevýhoda
   *  pole je nezměněná nutnost čekat, než se celá odpověď načte a přesype do
   *  paměti
   */
  protected static function zWhere($where, $args = null, $order = null) {    
    $url_akce       = 'IF(t2.patri_pod, (SELECT MAX(url_akce) FROM akce_seznam WHERE patri_pod = t2.patri_pod), t2.url_akce) as url_temp';
    $prihlaseni     = 'CONCAT(",",GROUP_CONCAT(p.id_uzivatele,u.pohlavi,p.id_stavu_prihlaseni),",") AS prihlaseni';
    $o = dbQueryS("
      SELECT t2.*, $prihlaseni, $url_akce FROM (
          SELECT a.*, at.url_typu, typ_1p
          FROM akce_seznam a
          LEFT JOIN akce_typy at ON (at.id_typu = a.typ)
        ) as t2
        LEFT JOIN akce_prihlaseni p USING (id_akce)
        LEFT JOIN uzivatele_hodnoty u USING (id_uzivatele)
          $where
        GROUP BY t2.id_akce
      $order
    ", $args);
    
//    $o = dbQueryS("
//      SELECT *, $tagy FROM (
//        SELECT t2.*, $prihlaseni, $url_akce FROM (
//          SELECT a.*, at.url_typu, al.poradi, typ_1p
//          FROM akce_seznam a
//          LEFT JOIN akce_typy at ON (at.id_typu = a.typ)
//          LEFT JOIN akce_lokace al ON (al.id_lokace = a.lokace)
//        ) as t2
//        LEFT JOIN akce_prihlaseni p ON (p.id_akce = t2.id_akce)
//        LEFT JOIN uzivatele_hodnoty u ON (u.id_uzivatele = p.id_uzivatele)
//          WHERE $where
//        GROUP BY t2.id_akce
//        
//      ) as t3
//      LEFT JOIN akce_tagy at ON (at.id_akce = t3.id_akce)
//      LEFT JOIN tagy t ON (
//      t.id = at.id_tagu)
//      GROUP BY t3.id_akce
//      ORDER BY $order
//    ", $args);

    $kolekce = new ArrayIterator();
    while($r = mysqli_fetch_assoc($o)) {
      $r['url_akce'] = $r['url_temp'];
      $aktivita = new self($r);
      $aktivita->kolekce = $kolekce;
      $kolekce[$r['id_akce']] = $aktivita;
    }

    return $kolekce;
  }
  
  /**
   * Vrátí název typu aktivity   
   * 
   * @return String $this->a['typ_1p'] název typu aktivity
   */
  function typText(){ 
    return $this->a['typ_1p']; 
  }  
}
