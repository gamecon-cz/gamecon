<?php

use Gamecon\Kostymy\ChybaPujcovny;
use Gamecon\Kostymy\FormatPujcovny;
use Gamecon\Kostymy\KatalogKostymu;
use Gamecon\Kostymy\PrehledyPujcovny;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\Kostymy\StrihKostymuEnum;
use Gamecon\Kostymy\VelikostKostymuEnum;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\XTemplate\XTemplate;

/**
 * Katalog půjčovny kostýmů: kostýmy, fotky, stav (k dispozici, v opravě, vyřazený).
 * Viz docs/generated/pujcovna-kostymu.md
 *
 * nazev: Půjčovna kostýmů
 * ikona: shirt
 * pravo: 115
 * submenu_group: 1
 * submenu_order: 1
 * submenu_nazev: Katalog
 */

NahravaniObrazku::hlidejPrilisVelkyPozadavek();

$katalogKostymu = new KatalogKostymu();
$adresaKatalogu = URL_ADMIN . '/pujcovna';

$akceKatalogu = (string) post('akceKatalogu');
if ($akceKatalogu !== '') {
    $idKostymuZFormulare = (int) post('idKostymu');
    try {
        switch ($akceKatalogu) {
            case 'ulozit':
                $idUlozenehoKostymu = $katalogKostymu->uloz(
                    array_intersect_key($_POST, array_flip(['nazev', 'popis', 'velikost', 'miry', 'strih', 'kategorie', 'stav', 'interni_poznamka', 'poradi'])),
                    $idKostymuZFormulare ?: null,
                );
                oznameniPresmeruj('Kostým je uložený.', $adresaKatalogu . '?id=' . $idUlozenehoKostymu);
                break;
            case 'smazat':
                $katalogKostymu->smaz($idKostymuZFormulare);
                oznameniPresmeruj('Kostým je smazaný.', $adresaKatalogu);
                break;
            case 'nahratFotku':
                NahravaniObrazku::sOhlasenimChyby(fn () => $katalogKostymu->pridejFotku(
                    $idKostymuZFormulare,
                    $katalogKostymu->nahravaniFotek()->overNahranySoubor($_FILES['fotka'] ?? null),
                ));
                oznameni('Fotka je nahraná.');
                break;
            case 'smazatFotku':
                NahravaniObrazku::sOhlasenimChyby(fn () => $katalogKostymu->smazFotku($idKostymuZFormulare, (int) post('idFotky')));
                oznameni('Fotka je smazaná.');
                break;
            case 'hlavniFotka':
                $katalogKostymu->nastavHlavniFotku($idKostymuZFormulare, (int) post('idFotky'));
                oznameni('Fotka je hlavní.');
                break;
        }
    } catch (ChybaPujcovny $chybaPujcovny) {
        chyba(htmlspecialchars($chybaPujcovny->getMessage()));
    }
}

$tplKatalogu = new XTemplate(__DIR__ . '/pujcovna.xtpl');
$tplKatalogu->assign('adresaKatalogu', $adresaKatalogu);
$idUpravovanehoKostymu = (int) get('id');
$upravovanyKostym = $idUpravovanehoKostymu
    ? $katalogKostymu->dej($idUpravovanehoKostymu)
    : null;

if ($upravovanyKostym !== null || get('novy') !== null) {
    $tplKatalogu->assign([
        'idKostymu'       => $upravovanyKostym?->getId() ?? '',
        'nazev'           => htmlspecialchars($upravovanyKostym?->getNazev() ?? ''),
        'popis'           => htmlspecialchars($upravovanyKostym?->getPopis() ?? ''),
        'miry'            => htmlspecialchars($upravovanyKostym?->getMiry() ?? ''),
        'kategorie'       => htmlspecialchars($upravovanyKostym?->getKategorie() ?? ''),
        'interniPoznamka' => htmlspecialchars($upravovanyKostym?->getInterniPoznamka() ?? ''),
        'poradi'          => $upravovanyKostym?->getPoradi() ?? 0,
        'nadpis'          => $upravovanyKostym !== null
            ? htmlspecialchars($upravovanyKostym->getNazev())
            : 'Nový kostým',
    ]);
    foreach ([
        'velikost' => [VelikostKostymuEnum::cases(), $upravovanyKostym?->getVelikost()],
        'strih'    => [StrihKostymuEnum::cases(), $upravovanyKostym?->getStrih()],
        'stav'     => [StavKostymuEnum::cases(), $upravovanyKostym?->getStav() ?? StavKostymuEnum::K_DISPOZICI],
    ] as $blokVyberu => [$moznosti, $vybrana]) {
        foreach ($moznosti as $moznost) {
            $tplKatalogu->assign([
                'hodnota'  => $moznost->value,
                'popisek'  => htmlspecialchars($moznost->getNazev()),
                'vybrano'  => $moznost === $vybrana
                    ? 'selected'
                    : '',
            ]);
            $tplKatalogu->parse("katalog.formular.{$blokVyberu}");
        }
    }
    foreach ($katalogKostymu->kategorie() as $existujiciKategorie) {
        $tplKatalogu->assign('existujiciKategorie', htmlspecialchars($existujiciKategorie));
        $tplKatalogu->parse('katalog.formular.kategorie');
    }

    if ($upravovanyKostym !== null) {
        $nahravaniFotekKostymu = $katalogKostymu->nahravaniFotek();
        $tplKatalogu->assign([
            'accept' => htmlspecialchars($nahravaniFotekKostymu->accept()),
            'limit'  => htmlspecialchars($nahravaniFotekKostymu->popisLimitu()),
        ]);
        foreach ($upravovanyKostym->getFotky() as $poradiFotky => $fotkaKostymu) {
            $tplKatalogu->assign([
                'idFotky'    => $fotkaKostymu->getId(),
                'nahledFotky' => htmlspecialchars($fotkaKostymu->getUrlNahledu(160, 160)),
                'urlFotky'   => htmlspecialchars($fotkaKostymu->getUrl()),
            ]);
            $tplKatalogu->parse($poradiFotky === 0
                ? 'katalog.formular.fotky.fotka.hlavni'
                : 'katalog.formular.fotky.fotka.nastavitHlavni');
            $tplKatalogu->parse('katalog.formular.fotky.fotka');
        }
        $tplKatalogu->parse('katalog.formular.fotky');

        $rezervaceKostymu = PrehledyPujcovny::zGlobals()->rezervaceKostymu($upravovanyKostym->getId());
        $ucastniciRezervaci = FormatPujcovny::uzivatelePodleId(array_column($rezervaceKostymu, 'id_uzivatele'));
        foreach ($rezervaceKostymu as $radekRezervace) {
            $tplKatalogu->assign([
                'casAktivity'  => htmlspecialchars(FormatPujcovny::casAktivity($radekRezervace['zacatek'], $radekRezervace['konec'])),
                'nazevAktivity' => htmlspecialchars($radekRezervace['nazev_akce']),
                'idUcastnika'  => (int) $radekRezervace['id_uzivatele'],
                'jmenoUcastnika' => htmlspecialchars(FormatPujcovny::jmeno($ucastniciRezervaci[(int) $radekRezervace['id_uzivatele']] ?? null)),
                'vydano'       => $radekRezervace['id_vydeje'] !== null
                    ? 'vydaný'
                    : '',
            ]);
            $tplKatalogu->parse('katalog.formular.rezervace.radek');
        }
        if ($rezervaceKostymu === []) {
            $tplKatalogu->parse('katalog.formular.rezervace.zadna');
        }
        $tplKatalogu->parse('katalog.formular.rezervace');
        $tplKatalogu->parse('katalog.formular.smazat');
    }
    $tplKatalogu->parse('katalog.formular');
} else {
    $kostymyKatalogu = $katalogKostymu->vyber();
    foreach ($kostymyKatalogu as $kostymKatalogu) {
        $tplKatalogu->assign([
            'idKostymu' => $kostymKatalogu->getId(),
            'nazev'     => htmlspecialchars($kostymKatalogu->getNazev()),
            'velikost'  => htmlspecialchars($kostymKatalogu->getVelikost()->getNazev()),
            'strih'     => htmlspecialchars($kostymKatalogu->getStrih()->getNazev()),
            'kategorie' => htmlspecialchars($kostymKatalogu->getKategorie()),
            'stav'      => htmlspecialchars($kostymKatalogu->getStav()->getNazev()),
            'tridaStavu' => $kostymKatalogu->getStav()->value,
            'poradi'    => $kostymKatalogu->getPoradi(),
        ]);
        $hlavniFotka = $kostymKatalogu->getHlavniFotka();
        if ($hlavniFotka !== null) {
            $tplKatalogu->assign('nahledFotky', htmlspecialchars($hlavniFotka->getUrlNahledu(60, 60)));
            $tplKatalogu->parse('katalog.seznam.kostym.fotka');
        }
        $tplKatalogu->parse('katalog.seznam.kostym');
    }
    if ($kostymyKatalogu === []) {
        $tplKatalogu->parse('katalog.seznam.zadny');
    }
    $tplKatalogu->parse('katalog.seznam');
}

$tplKatalogu->parse('katalog');
$tplKatalogu->out('katalog');
