<?php

$uzivatele = Uzivatel::zHledani(get('term'), ['mail' => true]);

$dataVOdpovedi = get('dataVOdpovedi') ?: [];

$sestavData = function (Uzivatel $uzivatel) use ($dataVOdpovedi): array {
    $data = [];
    foreach ($dataVOdpovedi as $polozka) {
        switch ($polozka) {
            case 'id' :
                $data['id'] = $uzivatel->id();
                break;
            case 'jmenoNick' :
                $data['jmenoNick'] = $uzivatel->jmenoNick();
                break;
            case 'jmeno' :
                $data['jmeno'] = $uzivatel->jmeno();
                break;
            case 'mail' :
                $data['mail'] = '(' . $uzivatel->mail() . ')';
                break;
            case 'telefon' :
                $data['telefon'] = $uzivatel->telefon();
                break;
            default :
                trigger_error("Nepodporovana polozka pro Omnibox: '$polozka'", E_USER_WARNING);
        }
    }
    return $data;
};

echo json_encode(array_map(function (Uzivatel $uzivatel) use ($sestavData) {
    return [
        'label' => $uzivatel->id() . ' – ' . $uzivatel->jmenoNick() . ' (' . $uzivatel->mail() . ')',
        'data' => $sestavData($uzivatel),
        'value' => $uzivatel->id(),
    ];
}, $uzivatele));
