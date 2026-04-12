<?php

use Gamecon\XTemplate\XTemplate;

/**
 * Editace šéfů linií, kontaktů a obrázků v hlavičkách aktivit
 *
 * nazev: Hlavičky linií
 * pravo: 105
 */

$adresarObrazkuLinii = ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/linie-ikony';
$fallbackObrazekUrl = URL_WEBU . '/soubory/systemove/avatary/default.png';
$podporovanePriponyObrazkuLinii = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$mimeNaPriponuObrazkuLinie = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

$informaceOObrazkuLinie = static function (int $idTypu) use ($adresarObrazkuLinii, $fallbackObrazekUrl, $podporovanePriponyObrazkuLinii): array {
    foreach ($podporovanePriponyObrazkuLinii as $priponaObrazkuLinie) {
        $vlastniObrazek = $adresarObrazkuLinii . '/org_' . $idTypu . '.' . $priponaObrazkuLinie;
        if (is_file($vlastniObrazek) && filesize($vlastniObrazek) > 0) {
            return [
                'url' => URL_WEBU . '/soubory/systemove/linie-ikony/org_' . $idTypu . '.' . $priponaObrazkuLinie . '?v=' . filemtime($vlastniObrazek),
                'cesta' => 'soubory/systemove/linie-ikony/org_' . $idTypu . '.' . $priponaObrazkuLinie,
                'stav' => 'Používá se vlastní obrázek.',
            ];
        }
    }

    $starsiObrazek = $adresarObrazkuLinii . '/' . $idTypu . '.png';
    if (is_file($starsiObrazek) && filesize($starsiObrazek) > 0) {
        return [
            'url' => URL_WEBU . '/soubory/systemove/linie-ikony/' . $idTypu . '.png?v=' . filemtime($starsiObrazek),
            'cesta' => 'soubory/systemove/linie-ikony/' . $idTypu . '.png',
            'stav' => 'Používá se starší obrázek linie (*.png).',
        ];
    }

    return [
        'url' => $fallbackObrazekUrl,
        'cesta' => 'soubory/systemove/avatary/default.png',
        'stav' => 'Vlastní obrázek chybí, používá se fallback.',
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
        dbQuery('DELETE FROM akce_typy_hlavicky WHERE id_typu = $1', [$idTypu]);
        oznameni('Hlavička linie byla vymazána, použije se fallback.');
    } else {
        dbQueryS(
            <<<'SQL'
INSERT INTO akce_typy_hlavicky (id_typu, sekce, jmeno, email)
VALUES ($1, $2, $3, $4)
ON DUPLICATE KEY UPDATE
    sekce = VALUES(sekce),
    jmeno = VALUES(jmeno),
    email = VALUES(email)
SQL,
            [
                $idTypu,
                $sekce !== '' ? $sekce : null,
                $jmeno !== '' ? $jmeno : null,
                $email !== '' ? $email : null,
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

    $obrazek = $_FILES['obrazek'] ?? null;
    if (!$obrazek || ($obrazek['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        chyba('Nepodařilo se nahrát obrázek.');
    }

    $informaceOObrazku = getimagesize($obrazek['tmp_name']);
    if (!$informaceOObrazku || empty($informaceOObrazku['mime'])) {
        chyba('Nahraný soubor není platný obrázek.');
    }
    $mime = $informaceOObrazku['mime'];
    $pripona = $mimeNaPriponuObrazkuLinie[$mime] ?? null;
    if (!$pripona) {
        chyba('Obrázek musí být JPG, PNG, WebP nebo GIF.');
    }

    if (!is_dir($adresarObrazkuLinii) && !mkdir($adresarObrazkuLinii, 0775, true) && !is_dir($adresarObrazkuLinii)) {
        chyba('Nepodařilo se vytvořit adresář pro obrázky linií.');
    }

    foreach ($podporovanePriponyObrazkuLinii as $staraPriponaObrazkuLinie) {
        $staraCesta = $adresarObrazkuLinii . '/org_' . $idTypu . '.' . $staraPriponaObrazkuLinie;
        if (is_file($staraCesta) && !unlink($staraCesta)) {
            chyba('Nepodařilo se odstranit starší variantu obrázku.');
        }
    }

    $cilovaCesta = $adresarObrazkuLinii . '/org_' . $idTypu . '.' . $pripona;
    if (!move_uploaded_file($obrazek['tmp_name'], $cilovaCesta)) {
        chyba('Nepodařilo se uložit obrázek.');
    }

    oznameni('Obrázek linie byl nahrán.');
}

if (post('action') === 'delete-line-header-image') {
    $idTypu = (int)post('id_typu');
    if (!$validniIdTypu($idTypu)) {
        chyba('Neplatná linie.');
    }

    foreach ($podporovanePriponyObrazkuLinii as $priponaObrazkuLinie) {
        $cilovaCesta = $adresarObrazkuLinii . '/org_' . $idTypu . '.' . $priponaObrazkuLinie;
        if (is_file($cilovaCesta) && !unlink($cilovaCesta)) {
            chyba('Nepodařilo se smazat obrázek linie.');
        }
    }

    oznameni('Vlastní obrázek linie byl smazán, použije se fallback.');
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
