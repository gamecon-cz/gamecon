<?php

/**
 * Stránka pro tvorbu a správu aktivit.
 *
 * nazev: Aktivity
 * pravo: 102
 */

if(post('filtr')) {
  if(post('filtr')=='vsechno')
    unset($_SESSION['adminAktivityFiltr']);
  else
    $_SESSION['adminAktivityFiltr']=post('filtr');
  back();
}

if(post('smazat')) {
  $a = Aktivita::zId(post('aktivitaId'));
  $a->smaz();
  back();
}

if(post('publikovat')) {
  dbQueryS('UPDATE akce_seznam SET stav=4 WHERE id_akce=$0',
    [post('aktivitaId')]); // TODO převést do modelu
  back();
}

if(post('pripravit')) {
  Aktivita::zId(post('aktivitaId'))->priprav();
  back();
}

if(post('odpripravit')) {
  Aktivita::zId(post('aktivitaId'))->odpriprav();
  back();
}

if(post('aktivovat')) {
  Aktivita::zId(post('aktivitaId'))->aktivuj();
  back();
}

if(post('aktivovatVse')) {
  dbQuery('UPDATE akce_seznam SET stav=1 WHERE stav=5 AND rok='.ROK); // TODO převést do modelu
  back();
}

if(post('instance')) {
  Aktivita::zId(post('aktivitaId'))->instanciuj();
  back();
}

$tpl=new XTemplate('aktivity.xtpl');

//zpracování filtru
$filtr=isset($_SESSION['adminAktivityFiltr'])?
  $_SESSION['adminAktivityFiltr']:'';
$o = dbQuery('SELECT * FROM akce_typy');
$varianty = [];
while($r = mysqli_fetch_assoc($o)) {
  $varianty[$r['id_typu']] = ['popis' => $r['typ_1pmn'], 'db' => $r['id_typu']];
}
foreach($varianty as $k => $v) {
  $tpl->assign('val',$k);
  $tpl->assign('nazev',ucfirst($v['popis']));
  $tpl->assign('sel',$filtr==$k?'selected="selected"':'');
  $tpl->parse('aktivity.filtrMoznost');
}

//načtení aktivit a zpracování
if(get('sort')) { //řazení
  setcookie('akceRazeni',get('sort'),time()+365*24*60*60);
  back();
}
$razeni = empty($_COOKIE['akceRazeni']) ? 'nazev_akce' : $_COOKIE['akceRazeni'];
$razeni = [$razeni, 'nazev_akce', 'zacatek'];

$filtr = empty($filtr) ? [] : ['typ' => $varianty[$filtr]['db']];
$filtr = array_merge(['rok' => ROK], $filtr);

$typy = dbArrayCol('SELECT id_typu, typ_1p FROM akce_typy');
$typy[0] = '';

$mistnosti = dbArrayCol('SELECT id_lokace, nazev FROM akce_lokace');

$aktivity = Aktivita::zFiltru($filtr, $razeni);

foreach($aktivity as $a)
{
  $r = $a->rawDb();
  $tpl->assign([
    'id_akce'   => $a->id(),
    'nazev_akce'     => $a->nazev(),
    'tagy'      => implode(' | ', $a->tagy()),
    'cas'       => $a->denCas(),
    'organizatori' => $a->orgJmena(),
    // TODO fixnout s lepším ORM
    'typ'       => $typy[$r['typ']],
    'mistnost'  => $mistnosti[$r['lokace']],
  ]);
  if($r['patri_pod']) $tpl->parse('aktivity.aktivita.instSymbol');
  if($r['stav']==0) $tpl->parse('aktivity.aktivita.tlacitka.publikovat');
  if($r['stav']==4) $tpl->parse('aktivity.aktivita.tlacitka.pripravit');
  if($r['stav']==5) $tpl->parse('aktivity.aktivita.tlacitka.odpripravit');
  if($r['stav']==5) $tpl->parse('aktivity.aktivita.tlacitka.aktivovat');
  $tpl->parse('aktivity.aktivita.tlacitka');
  $tpl->parse('aktivity.aktivita');
}

if($filtr == ['rok'=>ROK])
  $tpl->parse('aktivity.aktivovatVse');

$tpl->parse('aktivity');
$tpl->out('aktivity');
