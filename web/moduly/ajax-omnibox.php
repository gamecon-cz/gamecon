<?php

$this->bezStranky(true);

$out  = [];
$term = get('term');
if ($term) {
    foreach (Uzivatel::zHledani($term) as $u) {
        $out[] = [
            'label' => $u->id() . ' – ' . $u->jmenoNick(),
            'value' => $u->id(),
        ];
    }
}

echo json_encode($out);
