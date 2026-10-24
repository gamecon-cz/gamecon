<?php

use Gamecon\Ubytovani\DoplneniSamotaru;
use Gamecon\Ubytovani\UbytovaciSkupiny;
use Gamecon\XTemplate\XTemplate;

/**
 * Doplnění účastníků, kteří přijedou sami, na pokoje s volnou postelí, viz DoplneniSamotaru.
 * Až po konci prodeje ubytování, předtím drží skupiny volné postele pro kamarády s kódem.
 *
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */
$prodejUbytovaniUkoncen = $systemoveNastaveni->prodejUbytovaniUkoncen();

if (post('doplnitSamotare')) {
    if (!$prodejUbytovaniUkoncen) {
        chyba('Samotáře jde doplnit až po konci prodeje ubytování, do té doby drží skupiny volné postele pro kamarády.');
    }
    $doplneno = (new DoplneniSamotaru($systemoveNastaveni->rocnik()))->dopln();
    oznameni(sprintf(
        'Doplněno do existujících skupin: %d. Nové pokoje: %d, lidí v nich: %d.',
        $doplneno['doSkupin'],
        $doplneno['novychSkupin'],
        $doplneno['doNovychSkupin'],
    ));
}

$doplneniTemplate = new XTemplate(__DIR__ . '/_ubytovani-doplneni-samotaru.xtpl');
$ubytovaciSkupiny = new UbytovaciSkupiny($systemoveNastaveni->rocnik());
$idsSamotaru = $ubytovaciSkupiny->idsSamotaru();
$ubytovaniSamotaru = $ubytovaciSkupiny->ubytovaniClenu($idsSamotaru);
foreach (Uzivatel::zIds($idsSamotaru) as $samotar) {
    $ubytovani = $ubytovaniSamotaru[(int)$samotar->id()] ?? null;
    $doplneniTemplate->assign([
        'jmeno'   => htmlspecialchars((string)$samotar->jmenoNick()),
        'id'      => $samotar->id(),
        'pohlavi' => $samotar->jeZena()
            ? 'žena'
            : 'muž',
        'ubytovani' => htmlspecialchars($ubytovani !== null
            ? $ubytovaciSkupiny->popisUbytovani($ubytovani)
            : 'nemá objednané ubytování'),
    ]);
    $doplneniTemplate->parse('doplneni.cekajici.samotar');
}
if ($idsSamotaru !== []) {
    $doplneniTemplate->parse('doplneni.cekajici');
}
$doplneniTemplate->assign([
    'pocetCekajicich' => count($idsSamotaru),
    'konecProdeje'    => $systemoveNastaveni->prodejUbytovaniDo()->format('j. n. Y'),
]);
$doplneniTemplate->parse($prodejUbytovaniUkoncen
    ? 'doplneni.tlacitko'
    : 'doplneni.predKoncemProdeje');
$doplneniTemplate->parse('doplneni');
$doplneniTemplate->out('doplneni');
