<?php

$this->bezDekorace(true);

foreach(['sponzor', 'partner'] as $kategorie) {
  $adresarSObrazky = "soubory/obsah/{$kategorie}i";
  $souborSOdkazy = "{$adresarSObrazky}/odkazy.txt";
  if (is_readable($souborSOdkazy)) {
    $odkazy = parse_ini_file($souborSOdkazy);
  }
  foreach(glob("{$adresarSObrazky}/*") as $f) {
    if (!preg_match('@\.(jpeg|jpg|png|gif)$@', $f)) {
      continue;
    }
    $fn = preg_replace('@.*/(.*)\.(jpeg|jpg|png|gif)$@', '$1', $f, -1, $n);
    if($fn[0] == '_' || $n == 0) continue; // skrývání odebraných sponzorů
    $fileBasename = basename($f); // /foo/bar/baz.jpg = baz.jpg
    $t->assign([
      'url' => $odkazy[$fileBasename] ?? "http://$fn",
      'img' => $f,
    ]);
    $t->parse("sponzori.$kategorie");
  }
}
