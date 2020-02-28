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

if (post('filtrRoku')) {
  if (post('filtrRoku') === 'letos') {
    unset($_SESSION['adminAktivityFiltrRoku']);
  } else {
    $_SESSION['adminAktivityFiltrRoku'] = post('filtrRoku');
  }
}

$tplFiltrMoznosti = new XTemplate(__DIR__ . '/_filtr-moznosti.xtpl');

$filtrRoku = !empty($filtrovatPodleRoku) && !empty($_SESSION['adminAktivityFiltrRoku']) && $_SESSION['adminAktivityFiltrRoku'] >= 2000 && $_SESSION['adminAktivityFiltrRoku'] <= ROK
  ? $_SESSION['adminAktivityFiltrRoku']
  : ROK;

$varianty = ['vsechno' => ['popis' => '(všechno)']];
$adminAktivityFiltr = $_SESSION['adminAktivityFiltr'] ?? '';
$typy = dbFetchAll(<<<SQL
SELECT akce_typy.id_typu, akce_typy.typ_1pmn AS nazev_typu, COUNT(*) AS pocet_aktivit
FROM akce_seznam
JOIN akce_typy ON akce_seznam.typ = akce_typy.id_typu
WHERE akce_seznam.rok = $1
GROUP BY akce_typy.id_typu
SQL
  , [$filtrRoku]
);
$pocetAktivitCelkem = array_sum(array_map(static function (array $typ) {
  return $typ['pocet_aktivit'];
}, $typy));
$varianty['vsechno']['pocet_aktivit'] = $pocetAktivitCelkem;
foreach ($typy as $typ) {
  $varianty[$typ['id_typu']] = ['popis' => $typ['nazev_typu'], 'db' => $typ['id_typu'], 'pocet_aktivit' => $typ['pocet_aktivit']];
}

foreach ($varianty as $idTypu => $varianta) {
  $tplFiltrMoznosti->assign('idTypu', $idTypu);
  $tplFiltrMoznosti->assign('nazev_programove_linie', sprintf('%s (aktivit %d)', ucfirst($varianta['popis']), $varianta['pocet_aktivit']));
  $tplFiltrMoznosti->assign('selected', $adminAktivityFiltr == $idTypu
    ? 'selected="selected"'
    : ''
  );
  $tplFiltrMoznosti->parse('filtr.programoveLinie.programovaLinie');
}
$tplFiltrMoznosti->parse('filtr.programoveLinie');

if (!empty($filtrovatPodleRoku)) {
  $poctyAktivitVLetech = dbArrayCol('SELECT rok, COUNT(*) AS pocet FROM akce_seznam WHERE ROK > 2000 GROUP BY rok ORDER BY rok DESC');
  foreach ($poctyAktivitVLetech as $rok => $pocetAktivit) {
    $tplFiltrMoznosti->assign('rok', $rok);
    $tplFiltrMoznosti->assign('nazevRoku', $rok == ROK ? 'letos' : $rok);
    $tplFiltrMoznosti->assign('pocetAktivit', $pocetAktivit);
    $tplFiltrMoznosti->assign('selected', $filtrRoku == $rok
      ? 'selected="selected"'
      : ''
    );
    $tplFiltrMoznosti->parse('filtr.roky.rok');
  }
  $tplFiltrMoznosti->parse('filtr.roky');
}

$tplFiltrMoznosti->parse('filtr');
$tplFiltrMoznosti->out('filtr');

$razeni = ['nazev_akce', 'zacatek'];
if (!empty($_COOKIE['akceRazeni'])) {
  array_unshift($razeni, $_COOKIE['akceRazeni']);
}

$filtr = empty($varianty[$adminAktivityFiltr]['db'])
  ? []
  : ['typ' => $varianty[$adminAktivityFiltr]['db']];
$filtr['rok'] = $filtrRoku;

return [$filtr, $razeni];
