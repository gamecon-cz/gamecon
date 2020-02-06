<?php
if (post('filtr')) {
  if (post('filtr') === 'vsechno') {
    unset($_SESSION['adminAktivityFiltr']);
  } else {
    $_SESSION['adminAktivityFiltr'] = post('filtr');
  }
}

//načtení aktivit a zpracování
if (get('sort')) { //řazení
  setcookie('akceRazeni', get('sort'), time() + 365 * 24 * 60 * 60);
  $_COOKIE['akceRazeni'] = get('sort');
}

$varianty = ['vsechno' => ['popis' => '(všechno)']];

$adminAktivityFiltr = $_SESSION['adminAktivityFiltr'] ?? '';

$o = dbQuery('SELECT * FROM akce_typy');
while ($r = mysqli_fetch_assoc($o)) {
  $varianty[$r['id_typu']] = ['popis' => $r['typ_1pmn'], 'db' => $r['id_typu']];

}
$tplFiltrMoznosti = new XTemplate(__DIR__ . '/_filtr-moznosti.xtpl');

foreach ($varianty as $idTypu => $varianta) {
  $tplFiltrMoznosti->assign('val', $idTypu);
  $tplFiltrMoznosti->assign('nazev_akce', ucfirst($varianta['popis']));
  $tplFiltrMoznosti->assign('sel', $adminAktivityFiltr == $idTypu
    ? 'selected="selected"'
    : ''
  );
  $tplFiltrMoznosti->parse('filtr.moznost');
}

$tplFiltrMoznosti->parse('filtr');
$tplFiltrMoznosti->out('filtr');

$razeni = ['nazev_akce', 'zacatek'];
if (!empty($_COOKIE['akceRazeni'])) {
  array_unshift($razeni, $_COOKIE['akceRazeni']);
}

$filtr = empty($adminAktivityFiltr)
  ? []
  : ['typ' => $varianty[$adminAktivityFiltr]['db']];
$filtr = array_merge(['rok' => ROK], $filtr);

return [$filtr, $razeni];
