<?php

use Gamecon\Role\Role;

if (get('ajax') === 'uzivatel-k-vyplaceni-aktivity') {
    $organizatoriAkciQuery = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.*
FROM uzivatele_hodnoty
JOIN platne_role_uzivatelu
    ON platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele AND platne_role_uzivatelu.id_role IN($0, $1)
GROUP BY uzivatele_hodnoty.id_uzivatele
SQL
        , [0 => Role::LETOSNI_VYPRAVEC, 1 => Role::PRIHLASEN_NA_LETOSNI_GC], // při změně změň hint v šabloně finance.xtpl
    );
    $numberFormatter       = NumberFormatter::create('cs', NumberFormatter::PATTERN_DECIMAL);
    $organizatorAkciData   = [];
    while ($organizatorAkciRadek = mysqli_fetch_assoc($organizatoriAkciQuery)) {
        $organizatorAkci          = new Uzivatel($organizatorAkciRadek);
        $nevyuzityBonusZaAktivity = $organizatorAkci->finance()->nevyuzityBonusZaAktivity();
        if (!$nevyuzityBonusZaAktivity) {
            continue;
        }
        $organizatorAkciData[] = [
            'id'                       => $organizatorAkci->id(),
            'jmeno'                    => $organizatorAkci->jmenoNick(),
            'nevyuzityBonusZaAktivity' => $numberFormatter->formatCurrency($nevyuzityBonusZaAktivity, 'CZK'),
        ];
    }

    header('Content-type: application/json');
    echo json_encode(
        $organizatorAkciData,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
