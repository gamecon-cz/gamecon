<?php
// takzvaný BFGR report

require_once('sdilene-hlavicky.php');

function ed($datum) { // excel datum
  if(!$datum) return null;
  return date('j.n.Y G:i', strtotime($datum));
}

function ec($cislo) { // excel číslo
  return str_replace('.', ',', $cislo);
}

function ut($typ) { // ubytování typ - z názvu předmětu odhadne typ
  return preg_replace('@ ?(pondělí|úterý|středa|čtvrtek|pátek|sobota|neděle) ?@iu', '', $typ);
}

$gcDoted = [];
$maxRok = po(REG_GC_DO) ? ROK : ROK - 1;
for($i = 2009; $i <= $maxRok; $i++) {
  $gcDoted[$i] = 'účast '.$i;
}

$hlavicka1=array_merge(
  ['Účastník','','','','','','','','','Datum narození','','','Bydliště','','','','','',
  'Ubytovací informace','','',''],
  array_fill(0,count($gcDoted),''),
  ['Celkové náklady','','',
  'Ostatní platby','','','','','','','','','','','','','','','']
);
$hlavicka2=array_merge(
  ['ID','Příjmení','Jméno','Přezdívka','Mail','Židle','Práva','Datum registrace','Prošel infopultem','Den','Měsíc','Rok','Stát','Město','Ulice',
  'PSČ','Škola','Chci bydlet s','První noc','Poslední noc (počátek)','Typ','Dorazil na GC'],
  $gcDoted,
  [
  'Celkem dní','Cena / den','Ubytování','Předměty a strava',
  'Aktivity','vypravěčská sleva využitá','vypravěčská sleva přiznaná','dobrovolné vstupné','dobrovolné vstupné (pozdě)','stav', 'slevy','zůstatek z minula','připsané platby','první blok','poslední blok','dobrovolník pozice','dobrovolník info','Slevy','Objednávky']
);
$o=dbQuery('
  SELECT
    u.*,
    z.posazen,
    ( SELECT MIN(p.ubytovani_den) FROM shop_nakupy n JOIN shop_predmety p USING(id_predmetu) WHERE n.rok='.ROK.' AND n.id_uzivatele=z.id_uzivatele AND p.typ=2 ) den_prvni,
    ( SELECT MAX(p.ubytovani_den) FROM shop_nakupy n JOIN shop_predmety p USING(id_predmetu) WHERE n.rok='.ROK.' AND n.id_uzivatele=z.id_uzivatele AND p.typ=2 ) as den_posledni,
    ( SELECT MAX(p.nazev) FROM shop_nakupy n JOIN shop_predmety p USING(id_predmetu) WHERE n.rok='.ROK.' AND n.id_uzivatele=z.id_uzivatele AND p.typ=2 ) as ubytovani_typ,
    ( SELECT GROUP_CONCAT(rps.jmeno_prava SEPARATOR ", ")
      FROM r_uzivatele_zidle ruz
      JOIN r_prava_zidle rpz ON ruz.id_zidle=rpz.id_zidle
      JOIN r_prava_soupis rps ON rps.id_prava=rpz.id_prava
      WHERE ruz.id_uzivatele=u.id_uzivatele AND ruz.id_zidle > 0
      GROUP BY ruz.id_uzivatele
    ) as pravaZDotazu,
    ( SELECT GROUP_CONCAT(rzs.jmeno_zidle SEPARATOR ", ")
      FROM r_uzivatele_zidle ruz
      LEFT JOIN r_zidle_soupis rzs ON ruz.id_zidle = rzs.id_zidle
      WHERE ruz.id_uzivatele=u.id_uzivatele AND ruz.id_zidle > 0
      GROUP BY ruz.id_uzivatele
    ) as zidleZDotazu,
    pritomen.posazen as prosel_info
  FROM r_uzivatele_zidle z
  JOIN uzivatele_hodnoty u ON(z.id_uzivatele=u.id_uzivatele)
  LEFT JOIN r_uzivatele_zidle pritomen ON(pritomen.id_zidle = $1 AND pritomen.id_uzivatele = u.id_uzivatele)
  WHERE z.id_zidle='.Z_PRIHLASEN.'
  ', [Z_PRITOMEN]);
if(mysqli_num_rows($o)==0)
  exit('V tabulce nejsou žádná data.');

$obsah[] = $hlavicka2;
while($r=mysqli_fetch_assoc($o))
{
  $un=new Uzivatel($r);
  $un->nactiPrava(); //sql subdotaz, zlo
  $f = $un->finance();
  $ucastiHistorie=[];
  foreach($gcDoted as $rok => $nul)
    $ucastiHistorie[]=$un->maPravo((int)( '-'.substr($rok,2).'02' ))?'ano':'ne';
  $stat = '';
  try { $stat = $un->stat(); } catch(Exception $e) {}
  $obsah[] = array_merge(
    [
      $r['id_uzivatele'],
      $r['prijmeni_uzivatele'],
      $r['jmeno_uzivatele'],
      $r['login_uzivatele'],
      $r['email1_uzivatele'],
      $r['zidleZDotazu'],
      $r['pravaZDotazu'],
      ed($r['posazen']),
      ed($r['prosel_info']),
      date('j',strtotime($r['datum_narozeni'])),
      date('n',strtotime($r['datum_narozeni'])),
      date('Y',strtotime($r['datum_narozeni'])),
      $stat,
      $r['mesto_uzivatele'],
      $r['ulice_a_cp_uzivatele'],
      $r['psc_uzivatele'],
      $r['skola'],
      $r['ubytovan_s'],
      $r['den_prvni'] === null ? '-' :
        (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add("P$r[den_prvni]D")->format('j.n.Y'),
      $r['den_posledni'] === null ? '-' :
        (new DateTimeCz(DEN_PRVNI_UBYTOVANI))->add("P$r[den_posledni]D")->format('j.n.Y'),
      ut($r['ubytovani_typ']),
      $un->gcPritomen() ? 'ano' : 'ne'
    ],
    $ucastiHistorie,
    [
      $pobyt=( $r['den_prvni']!==null ? $r['den_posledni']-$r['den_prvni']+1 : 0 ),
      $pobyt ? $f->cenaUbytovani()/$pobyt : 0,
      $f->cenaUbytovani(),
      $f->cenaPredmety(),
      $f->cenaAktivity(),
      $f->slevaVypravecVyuzita(),
      $f->slevaVypravecMax(),
      $f->vstupne(),
      $f->vstupnePozde(),
      ec($f->stav()),
      ec($f->slevaObecna()),
      ec($r['zustatek']),
      ec($f->platby()),
      ed($un->prvniBlok()),
      ed($un->posledniBlok()),
      $r['pomoc_typ'],
      $r['pomoc_vice'],
      implode(", ",array_merge($f->slevyVse(),$f->slevyAktivity())),
      strip_tags(strtr($f->prehledHtml(),['</tr>'=>", ", '</td>'=>' '])),
    ]
  );
}

$report = Report::zPoli($hlavicka1, $obsah); // TODO druhá hlavička
$format = get('format') == 'html' ? 'tHtml' : 'tCsv';
$report->$format();
