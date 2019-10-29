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

$ucastPodleRoku = [];
$maxRok = po(REG_GC_DO) ? ROK : ROK - 1;
for($i = 2009; $i <= $maxRok; $i++) {
  $ucastPodleRoku[$i] = 'účast '.$i;
}

$hlavicka1=array_merge(
  ['Účastník','','','','','','','','','Datum narození','','','Bydliště','','','','','',
  'Ubytovací informace','','',''],
  array_fill(0,count($ucastPodleRoku),''),
  ['Celkové náklady','','',
  'Ostatní platby','','','','','','','','','','','','','','','']
);
$hlavicka2=array_merge(
  ['ID','Příjmení','Jméno','Přezdívka','Mail','Židle','Práva','Datum registrace','Prošel infopultem','Den','Měsíc','Rok','Stát','Město','Ulice',
  'PSČ','Škola','Chci bydlet s','První noc','Poslední noc (počátek)','Typ','Dorazil na GC'],
  $ucastPodleRoku,
  [
  'Celkem dní','Cena / den','Ubytování','Předměty a strava',
  'Aktivity','vypravěčská sleva využitá','vypravěčská sleva přiznaná','dobrovolné vstupné','dobrovolné vstupné (pozdě)','stav', 'slevy','zůstatek z minula','připsané platby','první blok','poslední blok','dobrovolník pozice','dobrovolník info','Slevy','Objednávky']
);
$o=dbQuery(
  'SELECT
    uzivatele_hodnoty.*,
    r_uzivatele_zidle.posazen,
    ( SELECT MIN(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok='.ROK.' AND shop_nakupy.id_uzivatele=r_uzivatele_zidle.id_uzivatele AND shop_predmety.typ=2 ) AS den_prvni,
    ( SELECT MAX(shop_predmety.ubytovani_den) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok='.ROK.' AND shop_nakupy.id_uzivatele=r_uzivatele_zidle.id_uzivatele AND shop_predmety.typ=2 ) AS den_posledni,
    ( SELECT MAX(shop_predmety.nazev) FROM shop_nakupy JOIN shop_predmety USING(id_predmetu) WHERE shop_nakupy.rok='.ROK.' AND shop_nakupy.id_uzivatele=r_uzivatele_zidle.id_uzivatele AND shop_predmety.typ=2 ) AS ubytovani_typ,
    ( SELECT GROUP_CONCAT(r_prava_soupis.jmeno_prava SEPARATOR ", ")
      FROM r_uzivatele_zidle
      JOIN r_prava_zidle ON r_uzivatele_zidle.id_zidle=r_prava_zidle.id_zidle
      JOIN r_prava_soupis ON r_prava_soupis.id_prava=r_prava_zidle.id_prava
      WHERE r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle > 0
      GROUP BY r_uzivatele_zidle.id_uzivatele
    ) as pravaZDotazu,
    ( SELECT GROUP_CONCAT(r_zidle_soupis.jmeno_zidle SEPARATOR ", ")
      FROM r_uzivatele_zidle
      LEFT JOIN r_zidle_soupis ON r_uzivatele_zidle.id_zidle = r_zidle_soupis.id_zidle
      WHERE r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele AND r_uzivatele_zidle.id_zidle > 0
      GROUP BY r_uzivatele_zidle.id_uzivatele
    ) as zidleZDotazu,
    pritomen.posazen as prosel_info
  FROM r_uzivatele_zidle
  JOIN uzivatele_hodnoty ON(r_uzivatele_zidle.id_uzivatele=uzivatele_hodnoty.id_uzivatele)
  LEFT JOIN r_uzivatele_zidle pritomen ON(pritomen.id_zidle = $1 AND pritomen.id_uzivatele = uzivatele_hodnoty.id_uzivatele)
  WHERE r_uzivatele_zidle.id_zidle='.Z_PRIHLASEN,
  [Z_PRITOMEN]
);
if(mysqli_num_rows($o)==0)
  exit('V tabulce nejsou žádná data.');

$obsah[] = $hlavicka2;
while($r=mysqli_fetch_assoc($o))
{
  $un=new Uzivatel($r);
  $un->nactiPrava(); //sql subdotaz, zlo
  $f = $un->finance();
  $ucastiHistorie=[];
  foreach($ucastPodleRoku as $rok => $nul)
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
      $f->prehledPopis(),
    ]
  );
}

$report = Report::zPoli($hlavicka1, $obsah); // TODO druhá hlavička
$format = get('format') == 'html' ? 'tHtml' : 'tCsv';
$report->$format();
