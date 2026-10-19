<?php

use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniObrazkuException;
use Gamecon\Web\ZpracovaneObrazky;
use Gamecon\XTemplate\XTemplate;

/**
 * Obrázky, které ukazuje galerie e-shopu v přihlášce (web/moduly/prihlaska/prihlaska.php).
 * Galerie bere všechny obrázky z adresáře ročníku, pořadí i popisek určuje název souboru.
 */

$nahravaniObrazkuEshopu = NahravaniObrazku::proObrazkyEshopu();

if (NahravaniObrazku::bylPozadavekPrilisVelky()) {
    chyba(NahravaniObrazku::zpravaOPrilisVelkemPozadavku());
}

if (post('obrazkyEshopuAkce') === 'nahrat') {
    try {
        $nahranyObrazek = $nahravaniObrazkuEshopu->overNahranySoubor($_FILES['obrazekEshopu'] ?? null);
        $nazevObrazku = trim((string)post('nazev')) !== ''
            ? (string)post('nazev')
            : $nahranyObrazek->puvodniNazevBezPripony();
        $ulozenyObrazek = $nahravaniObrazkuEshopu->ulozJakoJediny(
            $nahranyObrazek,
            '',
            NahravaniObrazku::bezpecnyNazev($nazevObrazku, zachovatDiakritikuAMezery: true),
        );
    } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
        chyba(htmlspecialchars($nahravaniObrazkuException->getMessage()));
    }
    oznameni(sprintf(
        'Obrázek %s je v přihlášce s popiskem „%s“.',
        htmlspecialchars(basename($ulozenyObrazek)),
        htmlspecialchars(ZpracovaneObrazky::popisekObrazkuEshopu($ulozenyObrazek)),
    ));
}

if (post('obrazkyEshopuAkce') === 'smazat') {
    try {
        $nahravaniObrazkuEshopu->smaz(basename((string)post('soubor')));
    } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
        chyba(htmlspecialchars($nahravaniObrazkuException->getMessage()));
    }
    oznameni('Obrázek je smazaný.');
}

$obrazkyEshopuTemplate = new XTemplate(__DIR__ . '/_obrazky-eshopu.xtpl');
$obrazkyEshopuTemplate->assign([
    'rocnik' => ROCNIK,
    'accept' => $nahravaniObrazkuEshopu->accept(),
    'limit'  => htmlspecialchars($nahravaniObrazkuEshopu->popisLimitu()),
]);
$obrazkyEshopu = $nahravaniObrazkuEshopu->obrazkyVAdresari();
foreach ($obrazkyEshopu as $souborObrazku) {
    $cestaKObrazku = $nahravaniObrazkuEshopu->cestaKSouboru($souborObrazku);
    try {
        $nahledObrazku = Nahled::zeSouboru($cestaKObrazku)->pasuj(120, 80)->url();
    } catch (\RuntimeException) {
        $nahledObrazku = '';
    }
    $obrazkyEshopuTemplate->assign([
        'soubor'  => htmlspecialchars($souborObrazku),
        'popisek' => htmlspecialchars(ZpracovaneObrazky::popisekObrazkuEshopu($souborObrazku)),
        'nahled'  => htmlspecialchars($nahledObrazku),
        'url'     => htmlspecialchars((string)ZpracovaneObrazky::urlNaWebu($cestaKObrazku)),
    ]);
    // stejná kontrola, jakou dělá přihláška, než obrázek ukáže
    if (!Obrazek::jeToPodporovanyObrazek($cestaKObrazku)) {
        $obrazkyEshopuTemplate->parse('obrazkyEshopu.obrazek.nezobrazi');
    }
    $obrazkyEshopuTemplate->parse('obrazkyEshopu.obrazek');
}
if ($obrazkyEshopu === []) {
    $obrazkyEshopuTemplate->parse('obrazkyEshopu.zadny');
}
$obrazkyEshopuTemplate->parse('obrazkyEshopu');
$obrazkyEshopuTemplate->out('obrazkyEshopu');
