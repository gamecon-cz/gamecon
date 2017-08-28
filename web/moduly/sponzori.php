<?php

$this->bezDekorace(true);

foreach(['sponzor', 'partner'] as $kategorie) {
  foreach(glob("soubory/obsah/{$kategorie}i/*") as $f) {
    $fn = preg_replace('@.*/(.*)\.(jpg|png|gif)@', '$1', $f, -1, $n);
    if($fn[0] == '_' || $n == 0) continue; // skrývání odebraných sponzorů
    $t->assign([
      'url' => $fn,
      'img' => $f,
    ]);
    $t->parse("sponzori.$kategorie");
  }
}
