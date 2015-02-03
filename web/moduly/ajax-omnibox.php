<?php

$this->bezStranky(true);

$q = get('term');
if(!$q || strlen($q) < 2) exit();

$out = array();
foreach(Uzivatel::zHledani($q) as $u) {
  $out[] = array(
    'label' => $u->id().' – '.$u->jmenoNick(),
    'value' => $u->id()
  );
}

echo json_encode($out);
