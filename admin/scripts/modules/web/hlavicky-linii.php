<?php

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\ZpracovaneObrazky;
use Gamecon\XTemplate\XTemplate;

/**
 * Editace šéfů linií, kontaktů a obrázků v hlavičkách aktivit
 *
 * nazev: Hlavičky linií
 * pravo: 105
 */

$nahravaniObrazkuLinii = NahravaniObrazku::proObrazkyLinii();
NahravaniObrazku::hlidejPrilisVelkyPozadavek();

$informaceOObrazkuLinie = static function (int $idTypu): array {
    $cestaVcetneVerze = cestaObrazkuLinie($idTypu);
    $cestaBezVerze = strtok($cestaVcetneVerze, '?');
    $nazevSouboru = basename($cestaBezVerze);

    if (str_starts_with($nazevSouboru, 'org_')) {
        $stav = 'Používá se vlastní obrázek.';
    } elseif ($nazevSouboru === 'default.png') {
        $stav = 'Vlastní obrázek chybí, používá se fallback.';
    } else {
        $stav = 'Používá se starší obrázek linie (*.png).';
    }

    return [
        'url' => URL_WEBU . '/' . $cestaVcetneVerze,
        'cesta' => $cestaBezVerze,
        'stav' => $stav,
    ];
};

$validniIdTypu = static function (int $idTypu): bool {
    return $idTypu > 0 && (bool)dbFetchSingle(
        'SELECT 1 FROM akce_typy WHERE id_typu = $1 AND zobrazit_v_menu = 1',
        [$idTypu],
    );
};

if (post('action') === 'save-line-header') {
    $idTypu = (int)post('id_typu');
    if (!$validniIdTypu($idTypu)) {
        chyba('Neplatná linie.');
    }

    $sekce = trim((string)post('sekce'));
    $jmeno = trim((string)post('jmeno'));
    $email = trim((string)post('email'));

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        chyba('E-mail není v platném formátu.');
    }

    if ($sekce === '' && $jmeno === '' && $email === '') {
        dbQuery('DELETE FROM akce_typy_hlavicky WHERE id_typu = $1', [1 => $idTypu]);
        oznameni('Hlavička linie byla vymazána, použije se fallback.');
    } else {
        dbQuery(
            <<<'SQL'
INSERT INTO akce_typy_hlavicky (id_typu, sekce, jmeno, email)
VALUES ($1, $2, $3, $4)
ON DUPLICATE KEY UPDATE
    sekce = VALUES(sekce),
    jmeno = VALUES(jmeno),
    email = VALUES(email)
SQL,
            [
                1 => $idTypu,
                2 => $sekce !== '' ? $sekce : null,
                3 => $jmeno !== '' ? $jmeno : null,
                4 => $email !== '' ? $email : null,
            ],
        );

        oznameni('Hlavička linie byla uložena.');
    }
}

if (post('action') === 'upload-line-header-image') {
    $idTypu = (int)post('id_typu');
    if (!$validniIdTypu($idTypu)) {
        chyba('Neplatná linie.');
    }

    NahravaniObrazku::sOhlasenimChyby(fn () => $nahravaniObrazkuLinii->ulozJakoJediny(
        $nahravaniObrazkuLinii->overNahranySoubor($_FILES['obrazek'] ?? null),
        '',
        'org_' . $idTypu,
    ));

    oznameni('Obrázek linie byl nahrán.');
}

if (post('action') === 'delete-line-header-image') {
    $idTypu = (int)post('id_typu');
    if (!$validniIdTypu($idTypu)) {
        chyba('Neplatná linie.');
    }

    NahravaniObrazku::sOhlasenimChyby(fn () => $nahravaniObrazkuLinii->smazVsechnyVarianty('', 'org_' . $idTypu));

    $legacyCesta = ZpracovaneObrazky::adresarObrazkuLinii() . '/' . $idTypu . '.png';
    if (is_file($legacyCesta)) {
        oznameni('Vlastní obrázek linie byl smazán, použije se starší ' . basename($legacyCesta));
    } else {
        oznameni('Vlastní obrázek linie byl smazán, použije se výchozí avatar.');
    }
}

$radky = dbFetchAll(
    <<<'SQL'
SELECT akce_typy.id_typu,
       akce_typy.typ_1pmn,
       akce_typy.url_typu_mn,
       akce_typy_hlavicky.sekce,
       akce_typy_hlavicky.jmeno,
       akce_typy_hlavicky.email
FROM akce_typy
LEFT JOIN akce_typy_hlavicky ON akce_typy_hlavicky.id_typu = akce_typy.id_typu
WHERE akce_typy.zobrazit_v_menu = 1
ORDER BY akce_typy.poradi, akce_typy.typ_1pmn
SQL,
);

$t = new XTemplate(__DIR__ . '/hlavicky-linii.xtpl');

foreach ($radky as $radek) {
    $idTypu = (int)$radek['id_typu'];
    $informaceOObrazku = $informaceOObrazkuLinie($idTypu);

    $t->assign([
        'id_typu' => $idTypu,
        'typ_1pmn' => htmlspecialchars((string)$radek['typ_1pmn']),
        'url_typu_mn' => htmlspecialchars((string)$radek['url_typu_mn']),
        'sekce' => htmlspecialchars((string)($radek['sekce'] ?? '')),
        'jmeno' => htmlspecialchars((string)($radek['jmeno'] ?? '')),
        'email' => htmlspecialchars((string)($radek['email'] ?? '')),
        'obrazek_url' => htmlspecialchars($informaceOObrazku['url']),
        'verejna_url_linie' => htmlspecialchars(URL_WEBU . '/' . $radek['url_typu_mn']),
        'obrazek_cesta' => htmlspecialchars($informaceOObrazku['cesta']),
        'obrazek_stav' => htmlspecialchars($informaceOObrazku['stav']),
    ]);
    $t->parse('hlavickyLinii.radek');
}

$t->parse('hlavickyLinii');
$t->out('hlavickyLinii');
