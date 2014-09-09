<?php

foreach(array('sponzor', 'partner') as $kategorie) {
  foreach(glob("soubory/obsah/{$kategorie}i/*") as $f) {
    $fn = preg_replace('@.*/(.*)\.(jpg|png|gif)@', '$1', $f);
    if($fn[0] == '_') continue; // skrývání odebraných sponzorů
    $t->assign(array(
      'url' => $fn,
      'img' => $f,
    ));
    $t->parse("sponzori.$kategorie");
  }
}
