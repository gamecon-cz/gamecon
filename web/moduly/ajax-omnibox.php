<?php

$this->bezStranky(true);

$out = [];
foreach(Uzivatel::zHledani($q) as $u) {
  $out[] = [
    'label' => $u->id().' – '.$u->jmenoNick(),
    'value' => $u->id()
  ];
}

echo json_encode($out);
