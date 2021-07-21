<?php

$uzivatele = Uzivatel::zHledani(get('term'), ['mail' => true]);

$dataVOdpovedi = get('dataVOdpovedi') ?: [];
$labelSlozenZ = get('labelSlozenZ');

$sestavData = static function (Uzivatel $uzivatel, array $dataVOdpovedi): array {
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
                $data['mail'] = $uzivatel->mail();
                break;
            case 'zustatek' :
                $data['zustatek'] = $uzivatel->finance()->stavHr(false);
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

$sestavLabel = static function (Uzivatel $uzivatel, ?array $labelSlozenZ) use ($sestavData): string {
    $labelSlozenZ = $labelSlozenZ ?: ['id', 'jmenoNick', 'mail'];
    $data = $sestavData($uzivatel, $labelSlozenZ);
    $labelCasti = [];
    if (!empty($data['id'])) {
        $labelCasti[] = $data['id'];
    }
    if (!empty($data['jmenoNick'])) {
        if ($labelCasti) {
            $labelCasti[] = ' - ';
        }
        $labelCasti[] = $data['jmenoNick'];
    }
    if (!empty($data['jmeno'])) {
        if ($labelCasti) {
            $labelCasti[] = ' - ';
        }
        $labelCasti[] = $data['jmeno'];
    }
    if (!empty($data['mail'])) {
        if ($labelCasti) {
            $labelCasti[] = ' ';
        }
        $labelCasti[] = "({$data['mail']})";
    }
    if (!empty($data['zustatek'])) {
        if ($labelCasti) {
            $labelCasti[] = '; ';
        }
        $labelCasti[] = "{$data['zustatek']}";
    }
    return implode($labelCasti);
};

echo json_encode(
    array_map(
        static function (Uzivatel $uzivatel) use ($sestavLabel, $sestavData, $dataVOdpovedi, $labelSlozenZ) {
            return [
                'label' => $sestavLabel($uzivatel, $labelSlozenZ),
                'data' => $sestavData($uzivatel, $dataVOdpovedi),
                'value' => $uzivatel->id(),
            ];
        },
        $uzivatele
    ),
    JSON_THROW_ON_ERROR
);
