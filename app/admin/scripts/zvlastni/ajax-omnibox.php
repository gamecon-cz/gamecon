<?php

$uzivatele = Uzivatel::zHledani(get('term'), [ 'mail' => true ]);

echo json_encode(array_map(function($u){
  return [
    'label' => $u->id().' – '.$u->jmenoNick().' ('.$u->mail().')',
    'value' => $u->id()
  ];
}, $uzivatele));
