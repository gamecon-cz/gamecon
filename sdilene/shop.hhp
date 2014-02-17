<?php

/**
 * Třída starající se o e-shop, nákupy, formy a související
 */ 

class Shop
{
  
  protected 
    $u, 
    $ubytovani=array(), 
    $tricka=array(), 
    $predmety=array(), 
    $ubytovaniOd,
    $ubytovaniDo,
    $ubytovaniTypy=array(),
    $klicU='shopU', //klíč formu pro identifikaci polí
    $klicUPokoj='shopUPokoj', //s kým chce být na pokoji
    $klicP='shopP', //klíč formu pro identifikaci polí
    $klicT='shopT', //klíč formu pro identifikaci polí s tričkama
    $klicS='shopS'; //klíč formu pro identifikaci polí se slevami
    //$quiet //todo
  
  protected static $skoly=array(
    'UK Univerzita Karlova Praha',
    'MU Masarykova univerzita Brno',
    'VUT Vysoké učení technické Brno',
    'VŠE Vysoká škola ekonomická Praha',
    'ČVUT České vysoké učení technické Praha',
    'VŠB-TU Vysoká škola báňská-Technická univerzita Ostrava',
    'ZU Západočeská univerzita v Plzni',
    'UP Univerzita Palackého v Olomouci',
    'ČZU Česká zemědělská univerzita v Praze',
    'MENDELU Mendelova zemědělská a lesnická univerzita v Brně',
    'UTB Univerzita Tomáše Bati ve Zlíně',
    'JU Jihočeská univerzita v Českých Budějovicích',
    'Univerzita Pardubice',
    'TU Technická univerzita v Liberci',
    'UJEP Univerzita J. E. Purkyně v Ústí nad Labem',
    'Univerzita Hradec Králové',
    'SU Slezská univerzita v Opavě',
    'VŠO Vysoká škola obchodní v Praze',
    'UJAK Univerzita Jana Amose Komenského',
    'VŠCHT Vysoká škola chemicko-technologická v Praze'
  );
  
  /**
   * Konstruktor
   */
  function __construct(Uzivatel $u)
  {
    $this->u=$u;
    // vybrat všechny předměty pro tento rok + předměty v nabídce + předměty, které si koupil 
    $o=dbQuery('
      SELECT 
        p.*,
        IF(p.model_rok='.ROK.',nazev,CONCAT(nazev," ",model_rok)) as nazev,
        COUNT(IF(n.rok='.ROK.',1,NULL)) kusu_prodano,
        COUNT(IF(n.id_uzivatele='.$this->u->id().' AND n.rok='.ROK.',1,NULL)) kusu_uzivatele
      FROM shop_predmety p
      LEFT JOIN shop_nakupy n USING(id_predmetu)
      WHERE p.model_rok='.ROK.' OR n.rok='.ROK.' OR p.v_nabidce
      GROUP BY id_predmetu
      ORDER BY typ, ubytovani_den, IF(nazev LIKE "Penzion%",CONCAT("zzz",nazev),IF(nazev LIKE "Chata%",CONCAT("zzzz",nazev),nazev)), model_rok DESC'); //ruční dořazení penzionů na konec
    
    while($r=mysql_fetch_assoc($o))
      if($r['typ']==1 || $r['typ']==4)
      { //předmět nebo jídlo
        $this->predmety[]=$r;
      }
      elseif($r['typ']==2)
      { //ubytování
        $this->ubytovani[$r['ubytovani_den']][self::typUbytovani($r)]=$r;
        $this->ubytovaniTypy[self::typUbytovani($r)]=1;
      }
      elseif($r['typ']==3)
      { //tričko
        $this->tricka[]=$r;
      }
      else
        throw new Exception('Objevil se nepodporovaný typ předmětu s č.'.$r['typ']);
  }
  
  /**
   * Vrátí html kód formuláře s předměty a tričky (bez form značek kvůli 
   * integraci více věcí naráz).
   * @todo vyprodání věcí   
   */
  function predmetyHtml()
  {
    $out='';
    $out="
    <script>
      function prikup(id,tlacitko)
      {
        var pocet=$('[name=\"{$this->klicP}['+id+']\"]').val();
        pocet++;
        $('[name=\"{$this->klicP}['+id+']\"]').val(pocet);
        $('#pocet'+id).html(pocet);
        if(pocet==1) // po inkrementu
          $(tlacitko).siblings('.minus').removeClass('neaktivni');
        return false;
      }
      function sniz(id,tlacitko)
      {
        var pocet=$('[name=\"{$this->klicP}['+id+']\"]').val();
        if(pocet>0)
        {
          pocet--;
          $('[name=\"{$this->klicP}['+id+']\"]').val(pocet);
          $('#pocet'+id).html(pocet);
        }
        if(pocet<=0) // po dekrementu
          $(tlacitko).addClass('neaktivni');
        return false;
      }
    </script>";
    
    $out='<i>Objednávání triček a předmětů ukončeno</i><br>';
    $out.='<table class="predmety">';
    foreach($this->predmety as $predmet)
      if($predmet['v_nabidce'] || $predmet['kusu_uzivatele']>0)
        $out.='<tr><td>'.$predmet['nazev'].' <input type="hidden" '.
        'name="'.$this->klicP.'['.$predmet['id_predmetu'].']" '.
        'value="'.$predmet['kusu_uzivatele'].'"></td>'.
        '<td>'.round($predmet['cena_aktualni']).'&thinsp;Kč</td>'.
        '<td><span id="pocet'.$predmet['id_predmetu'].'">'.$predmet['kusu_uzivatele'].'</span>&times;</td>'.
        ($predmet['v_nabidce']?
          '<td><a href="#" onclick="return sniz('.$predmet['id_predmetu'].',this)" class="minus'.($predmet['kusu_uzivatele']?'':' neaktivni').'">-</a> '.
          '<a href="#" onclick="return prikup('.$predmet['id_predmetu'].',this)" class="plus">+</a></td>'
          :
          '').
        "</tr>\n";
      else if($predmet['typ']==4 && ($this->u->maPravo(P_JIDLO) || $this->u->maPravo(P_JIDLO_ZDARMA)))
        //jídlo
        $out.='<tr><td>'.$predmet['nazev'].'</td><td>'.round($predmet['cena_aktualni']).'&thinsp;Kč</td>'.
        '<td><input type="checkbox" name="'.$this->klicP.'['.$predmet['id_predmetu'].']" value="1"'.($predmet['kusu_uzivatele']?' checked':'').'></td>'.
        "</td></tr>\n";
    $out.='</table>';
    
    $out.=$this->trickaHtml();
    
    // slovně popsané slvey fixme nedokonalé, na pevno zadrátované
    if($this->u->maPravo(P_TRIKO_ZDARMA))
      $out.='<br><i>Jako pro organizátora pro tebe výš uvedené ceny neplatí a máš jedno tričko, kostku, placku a veškeré jídlo zdarma :)<br>* večeře ve čtvrtek a snídaně+oběd v neděli</i>';
    else if($this->u->maPravo(P_TRIKO_ZAPUL))
      $out.='<br><i>Jako vypravěč máš poloviční slevu na tričko. Kostku a placku máš zdarma. Výš uvedené ceny pro tebe tedy neplatí.<br>* večeře ve čtvrtek a snídaně+oběd v neděli</i>';
    
    return $out;
  }
  
  /**
   * Vrátí html kód formuláře pro naklikání slev
   */
  function slevyHtml()
  {
    $skola=reset(dbOneLine('SELECT skola FROM uzivatele_hodnoty WHERE id_uzivatele='.$this->u->id())).'';
    $novacci=reset(dbOneLine('SELECT GROUP_CONCAT(id_uzivatele) FROM uzivatele_hodnoty WHERE guru='.$this->u->id().' AND YEAR(registrovan)>='.ROK));
    $out='';
    // student
    $out.='Jsem <b>student</b> a mám nárok na slevu <b>20%</b>: '.
    '<input type="checkbox" name="'.$this->klicS.'[student]" value="1"'.
    ($this->u->sediNaZidli(Z_STUDENT)?' checked':'').'> '.
    '<input type="text" style="width: 200px" id="skola" placeholder="škola" name="'.$this->klicS.'[skola]" value="'.$skola.'">';
    $out.='<script>$("#skola").autocomplete({source:["'.implode('","',self::$skoly).'"]});</script>';
    // včasná platba
    if(SLEVA_AKTIVNI)
    {
      $slevaDo=new DateTime(SLEVA_DO);
      //$slevaDo=$slevaDo->sub(new DateInterval('P1D'));
      $out.='<br>Pokud <b>zaplatíš do '.($slevaDo->format('j.n.')).
      '</b> máš slevu dalších <b>20%</b>.';
    }
    $out.='<br>Vezmu <b>nováčka</b>, za každého mám slevu dalších <b>20%</b>: '.
    '<input type="text" placeholder="id nováčka" name="'.$this->klicS.'[novacek]" value="'.$novacci.'">'.
    '<br><br><i>Jako nováček se bere každý účastník, který <b>skutečně dojede</b> a na GameConu nebyl aspoň 3 roky nebo nikdy. Pokud nováčka nebereš, nech pole prázdné. Pokud bereš víc nováčků, napiš jejich ID (číslo, které mají uvedené v pravém horním rohu webu) oddělená čárkou.</i>'.
    '<br><i>Pro slevu za včasnou platbu je potřeba, aby peníze dorazily do <b>30.6. na účet GC</b>. Převod může trvat až 2 dny.</i>';
    return $out;
  }     
  
  /**
   * Vrátí html kód s rádiobuttonky pro vyklikání ubytování
   * @todo nějaký custom prvek do názvů (name) nebo centralizace unikátních náz-
   * vů pro GC stránky celkově.   
   */
  function ubytovaniHtml()
  {
    $vyska='17px';
    $out='';
    // sloupec popisků
    $out.='<div style="width:110px; float:left">';
    $out.='<div>&nbsp;</div>';
    foreach($this->ubytovaniTypy as $typ => $rozsah)
      $out.='<div style="height:'.$vyska.'">'.$typ.'</div>';
    $out.='<div>Žádné</div>';
    $out.='</div>';
    // sloupec cen
    $out.='<div style="width:70px; float:left">';
    $out.='<div>&nbsp;</div>';
    foreach(reset($this->ubytovani) as $ubytovani)
      $out.='<div style="text-align:right;height:'.$vyska.'">'.round($ubytovani['cena_aktualni']).'&thinsp;Kč&emsp;&emsp; </div>';
    $out.='</div>';
    // sloupce s radiobuttony
    foreach($this->ubytovani as $den => $tmp)
    {
      $out.='<div style="width:57px; float:left">';
      $ubytovan=false;
      $nazev=reset($tmp);
      $nazev=$nazev['nazev'];
      $out.='<div>'.mb_ucfirst(substr($nazev,strrpos($nazev,' ')+1)).'</div>'; //poslední slovo názvu, fixme
      foreach($this->ubytovaniTypy as $typ => $rozsah)
      {
        if($this->ubytovan($den,$typ)){
          $ubytovan=true;
          $sel='checked'; }
        else
          $sel='';
        $lock=( !$sel && ( !$this->existujeUbytovani($den,$typ) || $this->plno($den,$typ) ) )?'disabled':'';
        $out.='<div style="height:'.$vyska.'">';
        $out.='<input style="margin:0" type="radio" '. 
          'name="'.$this->klicU.'['.$den.']" '. 
          'value="'.$this->ubytovaniPredmet($den,$typ).'" '.
          $sel.' '.$lock.'>';
        $out.=$this->existujeUbytovani($den,$typ)?(' <span style="font-size:80%;position:relative;top:-2px">'.$this->zbyvaMist($den,$typ).'/'.$this->kapacita($den,$typ).'</span>'):'';
        $out.='</div>';
      }
      $sel=$ubytovan?'':'checked';
      $out.='<div><input style="margin:0" type="radio" name="'.$this->klicU.'['.$den.']" value="" '.$sel.'></div>';
      $out.='</div>';
    }
    $out.='<div style="clear:both"></div>';
    
    // jen textové informace o ubytováních
    $out.='
      <a href="#" onclick="$(\'#infoUbytovani\').slideToggle();return false">informace o ubytování</a>
      <div style="display:none" id="infoUbytovani">
      <p>Čísla ukazují počet volných míst. Ubytování je pro zájemce (a nadšence a organizátory :) k dispozici už od středy, program začíná ale až <strong>čtvrtek</strong> odpoledne. Ubytování v neděli znamená noc z neděle na pondělí, kdy už opět neprobíhá program a v pondělí je potřeba pokoj vyklidit v 9:00.</p>
      <p>Ubytovat se na pokojích později než od čtvrtka je také možné, jen je třeba se připravit, že na postelích již někdo před vámi spal (a vzít si např. vlastní spacák).</p>
      <p>
        <b>Penzion Charlie:</b> Ubytování pro náročné, kteří chtějí mít svůj klid. 5 minut pomalé chůze od místa konání GameConu. Cena včetně DPH, rekreačního poplatku městu, snídaně, parkovného a wi-fi.<br>
        <b>Penzion Witch:</b> Nadstandardní ubytování v apartmánech pro 2-4 osoby. 5 minut pomalé chůze od místa konání GameConu. Cena včetně DPH, rekreačního poplatku městu.<br>
        <b>Chata SK a Richor:</b> Chaty se nachází v kempu 10-15 minut chůze od Internátu, v areálu jsou nově opravené sprchy (za poplatek 10 Kč), restaurace, minigolf. Studenti mají ubytování v chatkách o 15 Kč na den levnější.<br> 
        V případě dotazů k ubytování se obraťte na <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.
      </p>
      </div><br>
    ';
    
    // ubytování na pokoji s
    $spolubydlici=reset(dbOneLine('SELECT ubytovan_s FROM uzivatele_hodnoty WHERE id_uzivatele='.$this->u->id())); //první položka
    $out.='<br>Na pokoji chci být s (jména oddělená čárkou, nebo nech prázdné):';
    $out.='<br><input style="width: 400px" type="text" id="naPokojiS"'.
      'name="'.$this->klicUPokoj.'" value="'.$spolubydlici.'"><br>';
    $out.='<script src="'.URL_WEBU.'/files/doplnovani-vice.js"></script>';
    $out.='<script>doplnovatVice($("#naPokojiS"),'.$this->mozniUzivatele().');</script>';
    
    // slovně popsané slvey fixme nedokonalé, na pevno zadrátované
    if($this->u->maPravo(P_TRIKO_ZDARMA))
      $out.='<br><i>Jako organizátor máš veškeré ubytování také zdarma.</i>';
    else if($this->u->maPravo(P_TRIKO_ZAPUL))
      $out.='<br><i>Jako vypravěč máš na ubytování (i aktivity) slevu ve výši cca jeden nocleh+jídlo za dvě uspořádané aktivity. Její přesnou výšku najdeš '.($this->u->gcPrihlasen()?'':'po dokončení registrace ').'v svém finančním přehledu.</i>';
    
    return $out;
  }
  
  /**
   * Zpracuje část formuláře s předměty a tričky
   * Čáry máry s ručním počítáním diference (místo smazání a náhrady) jsou nut-
   * né kvůli zachování původní nákupní ceny (aktuální cena se totiž mohla od
   * nákupu změnit).         
   */
  function zpracujPredmety()
  {
    if(isset($_POST[$this->klicP]) && isset($_POST[$this->klicT]))
    {
      // pole s předměty, které jsou vyplněné ve formuláři
      $nove=array();
      foreach($_POST[$this->klicP] as $idPredmetu => $pocet)
        for($i=0;$i<$pocet;$i++)
          $nove[]=(int)$idPredmetu;
      foreach($_POST[$this->klicT] as $idTricka) // připojení triček
        if($idTricka) // odstranění výběrů „žádné tričko“
          $nove[]=(int)$idTricka;
      sort($nove);
      // pole s předměty, které už má objednané dříve (bez ubytování)
      $stare=array();
      $o=dbQuery('SELECT id_predmetu FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE id_uzivatele='.$this->u->id().' AND rok='.ROK.' AND typ!=2 ORDER BY id_predmetu');
      while($r=mysql_fetch_assoc($o))
        $stare[]=(int)$r['id_predmetu'];
      // určení rozdílů polí (note: array_diff ignoruje vícenásobné výskyty hodnot a nedá se použít)
      $i=$j=0;
      $odstranit=array(); //čísla (kvůli nutností více delete dotazů s limitem)
      $pridat=''; //část sql dotazu
      while(!empty($nove[$i]) || !empty($stare[$j]))
        if(empty($stare[$j]) || (!empty($nove[$i]) && $nove[$i]<$stare[$j]))
          // tento prvek není v staré objednávce
          // zapíšeme si ho pro přidání a přeskočíme na další
          $pridat.="\n".'('.$this->u->id().','.$nove[$i].','.ROK.',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu='.$nove[$i++].'),NOW()),'; //$i se inkrementuje se po provedení druhého!
        else if(empty($nove[$i]) || $stare[$j]<$nove[$i])
          // tento prvek ze staré objednávky není v nové objednávce
          // zapíšeme si ho, že má být odstraněn, a skočíme na další
          $odstranit[]=$stare[$j++];
        else
          // prvky jsou shodné, skočíme o jedna v obou seznamech a neděláme nic
          $i++ == $j++; //porovnání bez efektu
      // odstranění předmětů, které z objednávky oproti DB zmizely
      foreach($odstranit as $idPredmetu)
        dbQuery('DELETE FROM shop_nakupy WHERE id_uzivatele='.$this->u->id().' AND id_predmetu='.$idPredmetu.' AND rok='.ROK.' LIMIT 1');
      // přidání předmětů, které doposud objednané nemá
      $q='INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES ';
      $q.=$pridat;
      if(substr($q,-1)!=' ') //hack testující, jestli se přidala nějaká část
        dbQuery(substr($q,0,-1)); //odstranění nadbytečné čárky z poslední přidávané části a spuštění dotazu
      
      //throw new Exception('neimplementováno');
      //die();
    }
  }   
  
  /**
   * Zpracuje část formuláře s ubytováním
   * @return bool jestli došlo k zpracování dat   
   */
  function zpracujUbytovani()
  {
    if(isset($_POST[$this->klicU]))
    {
      // smazat veškeré stávající ubytování uživatele
      dbQuery('
        DELETE n.* FROM shop_nakupy n 
        JOIN shop_predmety p USING(id_predmetu) 
        WHERE n.id_uzivatele='.$this->u->id().' AND p.typ=2 AND n.rok='.ROK);
      // vložit jeho zaklikané věci - note: není zabezpečeno
      $q='INSERT INTO shop_nakupy(id_uzivatele,id_predmetu,rok,cena_nakupni,datum) VALUES '."\n";
      foreach($_POST[$this->klicU] as $predmet)
        if($predmet)
          $q.='('.$this->u->id().','.(int)$predmet.','.ROK.',(SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu='.(int)$predmet.'),NOW()),'."\n";
      $q=substr($q,0,-2);
      if(substr($q,-1)==')') //hack, test že se vložila aspoň jedna položka
        dbQuery($q);
      // uložit s kým chce být na pokoji
      if($_POST[$this->klicUPokoj])
        dbQueryS('UPDATE uzivatele_hodnoty SET ubytovan_s=$0 WHERE id_uzivatele='.$this->u->id(),array($_POST[$this->klicUPokoj]));
      else
        dbQuery('UPDATE uzivatele_hodnoty SET ubytovan_s=NULL WHERE id_uzivatele='.$this->u->id());
      return true;
    }
    return false;
  }
  
  /**
   * Zpracuje část formuláře se slevami
   */
  function zpracujSlevy()
  {
    if(isset($_POST[$this->klicS]))
    {
      $slevy=$_POST[$this->klicS];
      if(@$slevy['student'])
        $this->u->dejZidli(Z_STUDENT);
      else
        $this->u->vemZidli(Z_STUDENT);
      if(@$slevy['skola'])
        dbQueryS('UPDATE uzivatele_hodnoty SET skola=$0 WHERE id_uzivatele='.$this->u->id(),array($slevy['skola']));
      else
        dbQueryS('UPDATE uzivatele_hodnoty SET skola=NULL WHERE id_uzivatele='.$this->u->id());
      dbQuery('UPDATE uzivatele_hodnoty SET guru=NULL WHERE guru='.$this->u->id()); // reset nováčků
      if(@$slevy['novacek'])
      {
        $novacci=preg_replace('/\s+/','',$slevy['novacek']);
        $novacci=explode(',',$novacci);
        if(count($novacci)>0 && count($novacci)<10)
        {
          $upd='UPDATE uzivatele_hodnoty SET guru='.$this->u->id().' WHERE 0';
          foreach($novacci as $novacek)
            $upd.=' OR id_uzivatele='.(int)$novacek;
          dbQuery($upd);
        }
      }
    }
  }          
  
  ////////////////////
  // Protected věci //
  ////////////////////
  
  /**
   * Vrátí reálnou cenu předmětu pro konkrétního uživatele.
   * Pozor: není autoritativní, je jen copypasta z SQL formulace téhož v třídě 
   * Finance (viz)
   * @param $p databázový výstup předmětu
   */   
  protected function cena($p)
  {
    return $p['cena_aktualni'];
  }
  
  /**
   * Vrátí, jestli daná kombinace den a typ je validní.
   */
  protected function existujeUbytovani($den,$typ)
  {
    return isset($this->ubytovani[$den][$typ]) 
      && $this->ubytovani[$den][$typ]['v_nabidce'];
  }
  
  /**
   * Vrátí kapacitu
   */
  protected function kapacita($den,$typ)
  {
    $ub=$this->ubytovani[$den][$typ];
    return max(0,$ub['kusu_vyrobeno']);
  }
  
  /**
   * Vrátí seznam uživatelů ve formátu Jméno Příjmení (Login) tak aby byl zpra-
   * covatelný neajaxovým našeptávátkem (čili ["položka","položka",...])
   */
  protected function mozniUzivatele()
  {
    $out='';
    $o=dbQuery('SELECT CONCAT(jmeno_uzivatele," ",prijmeni_uzivatele," (",login_uzivatele,")") FROM uzivatele_hodnoty WHERE jmeno_uzivatele!="" AND prijmeni_uzivatele!="" AND id_uzivatele!='.$this->u->id());
    while($u=mysql_fetch_row($o))
      $out.='"'.$u[0].'",';
    return '['.substr($out,0,-1).']';
  }
  
  /**
   * Vrátí, jestli je v daný den a typ ubytování plno
   */
  protected function plno($den,$typ)
  {
    return $this->zbyvaMist($den,$typ)<=0;
  }
  
  /**
   * Vrátí html kód vybíracího políčka (políček) pro výběr triček
   * Obecná myšlenka: Máme roletový výběr s všemi možnostmi dostupnými součas-
   * nému uživateli, každé tričko je jedna roleta s vybraným kusem plus dole
   * je klikátko "další" pro přidání dalších triček.
   * @todo funkce na přidávání dalších políček na více triček
   * @todo různé ceny triček zeserou default cenu               
   */
  protected function trickaHtml()
  {
    // načtení variant, které může mít daný uživatel v selectboxu
    $moznosti=array();
    foreach($this->tricka as $tricko)
      if($tricko['v_nabidce'] || 
        ( strpos($tricko['nazev'],'modré')!==false && $this->u->maPravo(P_TRIKO_ZAPUL) ) || // modrá trička
        ( strpos($tricko['nazev'],'červené')!==false && $this->u->maPravo(P_TRIKO_ZDARMA) ) // červená trička
      ) // může si toto tričko objednávat
        if($tricko['kusu_vyrobeno'] && $tricko['kusu_prodano']>=$tricko['kusu_vyrobeno'] && !$tricko['kusu_uzivatele']) // vyprodáno
          $moznosti[]=array_merge($tricko,array('vyprodano'=>true));
        else
          $moznosti[]=array_merge($tricko,array('vyprodano'=>false));
    $defaultCena=(int)$tricko['cena_aktualni']; // pokud máme víc cen, zesere se to!
    // vygenerování selectboxů
    $trickaPlus=array_merge($this->tricka,array(array('kusu_uzivatele'=>1,'id_predmetu'=>0))); // zarážka dělající poslední výběrové políčko
    $out='';
    $j=0;
    /*
    // zakomentování výběru triček - fixme
    // problém je v tom, že u orgů už se pracuje s tím že v_nabidce=0 a přesto
    // se tričko zobrazí, proto je potřeba to vyřešit jinak (další sloupec?
    // změnit význam sloupce v_nabidce? …)
    */
    foreach($trickaPlus as $tricko)
      for($i=0;$i<$tricko['kusu_uzivatele'];$i++) // pro každý typ vygenerovat tolik selectboxů, kolikrát ho má koupený
      {
        // hack na zabránění změny - onclick a onchange a style pro obnovení fce pryč
        $out.='<select name="'.$this->klicT.'['.$j.']" style="color:#888;border:none;background:transparent" onclick="this.s=this.selectedIndex;return false" onchange="this.selectedIndex=s">';
        $out.='<option value="0">(žádné tričko)</option>';
        foreach($moznosti as $moznost)
          $out.=
            '<option value="'.$moznost['id_predmetu'].'"'.
            ($moznost['vyprodano']?' disabled':'').
            ($moznost['kusu_uzivatele'] && $tricko['id_predmetu']==$moznost['id_predmetu']?' selected':'').'>'.
            $moznost['nazev'].($moznost['vyprodano']?' (vyprodáno)':'').'</option>';
        $out.='</select>';
        // ceny triček
        if($tricko['id_predmetu']) // ne poslední tričko
          $out.=' '.round($tricko['cena_aktualni']).'&thinsp;Kč';
        else
          $out.=' '.$defaultCena.'&thinsp;Kč';
        $out.='<br>';
        $j++;
      }
    
    return $out;
    
  }     
  
  /**
   * Zpracuje řádek z databáze a vrátí „nějaký“ identifikátor typu ubytování.
   * Aktuálně vrací název bez posledního slova a mezery (ty jsou vyhrazeny pro
   * den)
   */
  protected static function typUbytovani($r)
  {
    return substr($r['nazev'],0,strrpos($r['nazev'],' '));
  }
  
  /**
   * Vrátí, jestli uživatel pro tento shop má ubytování v kombinaci den, typ
   * @param int $den číslo dne jak je v databázi   
   * @param string $typ typ ubytování ve smyslu názvu z DB bez posledního slova
   * @return bool je ubytován?      
   */
  protected function ubytovan($den,$typ)
  {
    return isset($this->ubytovani[$den][$typ]) 
      && $this->ubytovani[$den][$typ]['kusu_uzivatele']>0;
  }
  
  /**
   * Vrátí id předmětu, který odpovídá dané kombinaci ubytování
   */
  protected function ubytovaniPredmet($den,$typ)
  {
    if(isset($this->ubytovani[$den][$typ]['id_predmetu']))
      return $this->ubytovani[$den][$typ]['id_predmetu'];
    else
      return '';
  }     
  
  /**
   * Vrátí počet volných míst
   */
  protected function zbyvaMist($den,$typ)
  {
    $ub=$this->ubytovani[$den][$typ];
    return max(0,$ub['kusu_vyrobeno']-$ub['kusu_prodano']);
  }             
        
}

?>
