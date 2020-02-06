<?php
if (post('filtr')) {
  if (post('filtr') === 'vsechno') {
    unset($_SESSION['adminAktivityFiltr']);
  } else {
    $_SESSION['adminAktivityFiltr'] = post('filtr');
  }
}

$varianty = ($moznostFiltrovatVse ?? false)
  ? ['vsechno' => ['popis' => '(všechno)']]
  : [];

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

return $adminAktivityFiltr;
