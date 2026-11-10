<?php

use Gamecon\ArchivPrednasek\ArchivPrednasekStranka;
use Gamecon\ArchivPrednasek\ChybaArchivu;
use Gamecon\ArchivPrednasek\SpravaArchivu;
use Gamecon\ArchivPrednasek\YoutubeOdkaz;
use Gamecon\XTemplate\XTemplate;

/**
 * Přednášky z minulých ročníků na webu (/prednasky/archiv), docs/generated/archiv-prednasek.md
 *
 * nazev: Archiv přednášek
 * ikona: chalkboard-user
 * pravo: 105
 * prava: 105, 102
 * submenu_order: 1
 */

/**
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$urlModulu = 'web/archiv-prednasek';
$sprava = new SpravaArchivu($systemoveNastaveni->rocnik());
$e = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES);

// našeptávač karet programu pro spárování (omnibox), bez rámu stránky
if (get('hledatAktivitu') !== null) {
    $rok = (string) get('rok');
    header('Content-Type: application/json');
    echo json_encode(
        $sprava->hledejAktivity((string) get('term'), ctype_digit($rok) ? (int) $rok : null),
        JSON_THROW_ON_ERROR,
    );
    exit;
}

// skrýt ze seznamu nebo vrátit, zpátky na seznam se stejným filtrem
if (post('skryt') !== null || post('vratit') !== null) {
    $sprava->nastavSkrytou((int) post('idPrednasky'), post('skryt') !== null);
    oznameni(post('skryt') !== null
        ? 'Přednáška je z archivu na webu skrytá.'
        : 'Přednáška je zase v archivu na webu.');
}

/** @var array<string, mixed>|null $formular hodnoty formuláře po chybě, ať se neztratí */
$formular = null;
if (post('ulozit') !== null) {
    $idPrednasky = ctype_digit((string) post('idPrednasky'))
        ? (int) post('idPrednasky')
        : null;
    $prednasejici = [];
    $chybaUzivatele = null;
    foreach ((array) post('prednasejiciJmeno') as $index => $jmeno) {
        $uzivatel = trim((string) ($_POST['prednasejiciUzivatel'][$index] ?? ''));
        $puvodniUzivatel = trim((string) ($_POST['prednasejiciPuvodniUzivatel'][$index] ?? ''));
        if ($uzivatel !== '' && ! ctype_digit($uzivatel)) {
            $chybaUzivatele = "U přednášejícího „{$jmeno}“ vyber uživatele z našeptávače (pole čeká jeho ID).";
        }
        $prednasejici[] = [
            'jmeno'          => (string) $jmeno,
            'idUzivatele'    => ctype_digit($uzivatel) ? (int) $uzivatel : null,
            'menitUzivatele' => $uzivatel !== $puvodniUzivatel,
        ];
    }
    $idAkce = trim((string) post('idAkce'));
    try {
        if ($chybaUzivatele !== null) {
            throw new ChybaArchivu($chybaUzivatele);
        }
        if ($idAkce !== '' && ! ctype_digit($idAkce)) {
            throw new ChybaArchivu('Aktivitu programu vyber z našeptávače (pole čeká její ID), nebo pole vymaž.');
        }
        $idPrednasky = $sprava->uloz(
            idPrednasky: $idPrednasky,
            nazev: (string) post('nazev'),
            rok: (string) post('rok'),
            odkazNaYoutube: (string) post('youtube'),
            skryta: (bool) post('skryta'),
            idAkce: $idAkce !== '' ? (int) $idAkce : null,
            prednasejici: $prednasejici,
            klicovaSlova: array_map('strval', (array) post('klicovaSlova')),
        );
        oznameniPresmeruj('Přednáška je uložená.', URL_ADMIN . '/' . $urlModulu . '?id=' . $idPrednasky);
    } catch (ChybaArchivu $chybaArchivu) {
        // hláška je prostý text s názvy a jmény, chyba() ji vypíše jako HTML
        chyba($e($chybaArchivu->getMessage()), false);
        $formular = [
            'id'           => $idPrednasky,
            'nazev'        => (string) post('nazev'),
            'rok'          => (string) post('rok'),
            'youtube'      => (string) post('youtube'),
            'skryta'       => (bool) post('skryta'),
            'idAkce'       => $idAkce,
            'prednasejici' => array_map(static fn (array $osoba, int $index) => [
                'jmeno'                 => $osoba['jmeno'],
                'uzivatel'              => trim((string) ($_POST['prednasejiciUzivatel'][$index] ?? '')),
                'puvodniUzivatel'       => trim((string) ($_POST['prednasejiciPuvodniUzivatel'][$index] ?? '')),
            ], $prednasejici, array_keys($prednasejici)),
            'klicovaSlova' => array_map('strval', (array) post('klicovaSlova')),
        ];
    }
}

$t = new XTemplate(__DIR__ . '/archiv-prednasek.xtpl');
$t->assign([
    'urlModulu'  => $urlModulu,
    'urlArchivu' => $e(URL_WEBU . '/' . ArchivPrednasekStranka::getCestaZDatabaze()),
]);

$id = get('id');
if ($id !== null) {
    if ($formular === null && $id !== 'nova') {
        $detail = ctype_digit((string) $id)
            ? $sprava->getDetail((int) $id)
            : null;
        if ($detail === null) {
            chyba('Taková přednáška v archivu není.');
        }
        $formular = [
            'id'           => $detail['id'],
            'nazev'        => $detail['nazev'],
            'rok'          => (string) $detail['rok'],
            'youtube'      => $detail['youtubeId'] !== null ? YoutubeOdkaz::getUrlVidea($detail['youtubeId']) : '',
            'skryta'       => $detail['skryta'],
            'idAkce'       => $detail['idAkce'] !== null ? (string) $detail['idAkce'] : '',
            'prednasejici' => array_map(static fn (array $osoba) => [
                'jmeno'           => $osoba['jmeno'],
                'uzivatel'        => $osoba['idUzivatele'] !== null ? (string) $osoba['idUzivatele'] : '',
                'puvodniUzivatel' => $osoba['idUzivatele'] !== null ? (string) $osoba['idUzivatele'] : '',
            ], $detail['prednasejici']),
            'klicovaSlova' => $detail['klicovaSlova'],
        ];
    }
    $formular ??= [
        'id'           => null,
        'nazev'        => '',
        'rok'          => (string) ($systemoveNastaveni->rocnik() - 1),
        'youtube'      => '',
        'skryta'       => false,
        'idAkce'       => '',
        'prednasejici' => [],
        'klicovaSlova' => [],
    ];

    $t->assign([
        'nadpis'        => $formular['id'] !== null ? $e($formular['nazev']) : 'Nová přednáška',
        'id'            => $formular['id'] ?? '',
        'nazev'         => $e($formular['nazev']),
        'rok'           => $e($formular['rok']),
        'youtube'       => $e($formular['youtube']),
        'skrytaChecked' => $formular['skryta'] ? ' checked' : '',
        'idAkce'        => $e($formular['idAkce']),
        // hledání karet jen v roce přednášky, dokud se nezaškrtne „všechny ročníky“
        'omniboxRok'    => $e(json_encode(['rok' => (int) $formular['rok']], JSON_THROW_ON_ERROR)),
    ]);

    // napojení přednášejících: kdo to je a kam vede medailonek
    $uzivatele = [];
    $idsUzivatelu = array_values(array_filter(array_map(
        static fn (array $osoba) => ctype_digit($osoba['uzivatel']) ? (int) $osoba['uzivatel'] : null,
        $formular['prednasejici'],
    )));
    foreach ($idsUzivatelu !== [] ? Uzivatel::zIds($idsUzivatelu) : [] as $uzivatel) {
        $uzivatele[$uzivatel->id()] = $uzivatel;
    }
    // dva prázdné řádky na další přednášející
    foreach ([...$formular['prednasejici'], ['jmeno' => '', 'uzivatel' => '', 'puvodniUzivatel' => ''], ['jmeno' => '', 'uzivatel' => '', 'puvodniUzivatel' => '']] as $osoba) {
        $uzivatel = ctype_digit($osoba['uzivatel']) ? ($uzivatele[(int) $osoba['uzivatel']] ?? null) : null;
        $t->assign([
            'jmeno'           => $e($osoba['jmeno']),
            'uzivatel'        => $e($osoba['uzivatel']),
            'puvodniUzivatel' => $e($osoba['puvodniUzivatel']),
            // web ukáže napojeného přednášejícího tak, jak si uživatel nastavil (Zobrazení na webu)
            'popisUzivatele'  => $uzivatel
                ? '<a href="' . $e(URL_WEBU . '/' . $uzivatel->id()) . '" target="_blank">' . $e($uzivatel->jmenoNick() ?? $uzivatel->login()) . '</a>'
                    . ', na webu „' . $e($uzivatel->jmenoNaWebu()) . '“'
                : '',
        ]);
        $t->parse('detail.prednasejici');
    }
    foreach ($sprava->getJmenaPrednasejicich() as $jmeno) {
        $t->assign('moznost', $e($jmeno));
        $t->parse('detail.jmeno');
    }

    // tři prázdná pole na další klíčová slova
    foreach ([...$formular['klicovaSlova'], '', '', ''] as $slovo) {
        $t->assign('slovo', $e($slovo));
        $t->parse('detail.klicoveSlovo');
    }
    foreach ($sprava->getKlicovaSlova() as $slovo) {
        $t->assign('moznost', $e($slovo));
        $t->parse('detail.slovo');
    }

    $youtubeId = YoutubeOdkaz::getIdZOdkazu($formular['youtube']);
    if ($youtubeId !== null) {
        $t->assign([
            'urlVidea'   => $e(YoutubeOdkaz::getUrlVidea($youtubeId)),
            'urlNahledu' => $e(YoutubeOdkaz::getUrlNahledu($youtubeId)),
        ]);
        $t->parse('detail.video');
    }

    $aktivita = ctype_digit($formular['idAkce']) ? $sprava->getAktivita((int) $formular['idAkce']) : null;
    if ($aktivita !== null) {
        $t->assign([
            'nazevAktivity' => $e($aktivita['nazev']),
            'rokAktivity'   => $aktivita['rok'],
            'typAktivity'   => $e($aktivita['typ']),
            'urlAktivity'   => $e('aktivity/upravy?aktivitaId=' . $formular['idAkce']),
        ]);
        $t->parse('detail.aktivita');
    } else {
        $t->parse('detail.bezAktivity');
    }

    $t->parse('detail');
    $t->out('detail');

    return;
}

// seznam
$hodnotaFiltru = static function (string $nazev): ?bool {
    $hodnota = get($nazev);

    return $hodnota === '1' || $hodnota === '0'
        ? $hodnota === '1'
        : null;
};
$filtr = [
    'rok'       => ctype_digit((string) get('rok')) ? (int) get('rok') : null,
    'sparovana' => $hodnotaFiltru('sparovana'),
    'skryta'    => $hodnotaFiltru('skryta'),
    'zaznam'    => $hodnotaFiltru('zaznam'),
    'hledat'    => trim((string) get('hledat')),
];

foreach ($sprava->getRoky() as $rok) {
    $t->assign(['rok' => $rok, 'vybrany' => $filtr['rok'] === $rok ? ' selected' : '']);
    $t->parse('seznam.rok');
}
foreach (['sparovana', 'skryta', 'zaznam'] as $nazev) {
    $t->assign([
        $nazev . 'Ano' => $filtr[$nazev] === true ? ' selected' : '',
        $nazev . 'Ne'  => $filtr[$nazev] === false ? ' selected' : '',
    ]);
}
$t->assign('hledat', $e($filtr['hledat']));

$prednasky = $sprava->getSeznam($filtr);
foreach ($prednasky as $prednaska) {
    $t->assign([
        'id'           => $prednaska['id'],
        'rok'          => $prednaska['rok'],
        'nazev'        => $e($prednaska['nazev']),
        'prednasejici' => $e($prednaska['prednasejici']),
        'sparovani'    => $prednaska['idAkce'] !== null
            ? $e(($prednaska['nazevAkce'] ?? '') . ' (' . $prednaska['rokAkce'] . ', ID ' . $prednaska['idAkce'] . ')')
            : '<em>nespárovaná</em>',
        'zaznam'       => $prednaska['youtubeId'] !== null
            ? '<a href="' . $e(YoutubeOdkaz::getUrlVidea($prednaska['youtubeId'])) . '" target="_blank">záznam</a>'
            : '–',
        'tridaRadku'   => $prednaska['skryta'] ? 'archivPrednasek_skryta' : '',
        'akce'         => $prednaska['skryta'] ? 'vratit' : 'skryt',
        'akceText'     => $prednaska['skryta'] ? 'Vrátit na web' : 'Skrýt',
    ]);
    if ($prednaska['skryta']) {
        $t->parse('seznam.prednaska.skryta');
    }
    $t->parse('seznam.prednaska');
}
$t->assign('pocet', count($prednasky));
if ($prednasky === []) {
    $t->parse('seznam.zadna');
}

$t->parse('seznam');
$t->out('seznam');
