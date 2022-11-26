<?php

$this->bezStranky(true);

$out = [];
foreach(Uzivatel::zHledani((string)$_GET['term']) as $u) { // TODO lepší přístup k parametru
  $out[] = [
    'label' => $u->id().' – '.$u->jmenoNick(),
    'value' => $u->id()
  ];
}

echo json_encode($out);
