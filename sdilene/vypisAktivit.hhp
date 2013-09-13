<?php

/**
 * Třída výpisu aktivit a lidí přihlášených na ně schopná renderovat xtpl 
 */ 
class VypisAktivit
{
  
  private $dba=null;
  
  public function __construct($dba)
  {
    $this->dba=$dba;
  }
  
  /**
   * Vyrenderuje aktivity v daném xtpl souboru, jako název renderovaného bloku
   * použije $blok, pokud není zadán podblok, použije jako podblok cestu 
   * "$blok.ucastnik".
   */            
  public function tiskXtpl($xtpl,$blok,$podblok=null)
  {
    if(!$podblok)
      $podblok=$blok.'.ucastnik';
    $r=null;
    $dr=mysql_fetch_assoc($this->dba);
    while($r=$dr)
    {
      $dr=mysql_fetch_assoc($this->dba);
      $xtpl->assign($r);
      $xtpl->parse($podblok);
      //todo - 0 účastníků?
      if($r['id_akce']!=$dr['id_akce'])
      {
        $xtpl->assign('org',jmenoNick(array(
          'login_uzivatele'=>$r['orgLogin'], 
          'jmeno_uzivatele'=>$r['orgJmeno'], 
          'prijmeni_uzivatele'=>$r['orgPrijmeni'])));
        $xtpl->assign('cas',datum2($r));
        $xtpl->parse($blok);
      }
    }
  }
  
  public static function zPoleId($pole)
  {
    $pole=implode(' || id_akce=',$pole);
    return self::zSqlWhere('(id_akce='.$pole.')');
  }
  
  public static function zSqlWhere($where)
  {
    $a=dbQuery('
      SELECT a.*, u.*,
        org.login_uzivatele as orgLogin, 
        org.jmeno_uzivatele as orgJmeno, 
        org.prijmeni_uzivatele as orgPrijmeni,
        l.nazev as mistnost
      FROM akce_seznam a
      LEFT JOIN akce_prihlaseni p USING(id_akce)
      LEFT JOIN uzivatele_hodnoty u USING(id_uzivatele)
      LEFT JOIN uzivatele_hodnoty org ON(org.id_uzivatele=a.organizator)
      JOIN akce_lokace l ON(a.lokace=l.id_lokace)
      WHERE a.rok='.ROK.($where?(' AND '.$where):''));
    return new VypisAktivit($a);
  }
  
}

?>
