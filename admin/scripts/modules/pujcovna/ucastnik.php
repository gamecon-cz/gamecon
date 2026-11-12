<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Kostymy\ChybaPujcovny;
use Gamecon\Kostymy\FiltrKatalogu;
use Gamecon\Kostymy\FormatPujcovny;
use Gamecon\Kostymy\KatalogKostymu;
use Gamecon\Kostymy\PrehledyPujcovny;
use Gamecon\Kostymy\PujcovnaKostymu;
use Gamecon\Kostymy\StavPriVraceniKostymuEnum;
use Gamecon\Kostymy\VydejKostymu;
use Gamecon\XTemplate\XTemplate;

/**
 * Výdej a vrácení kostýmů a rezervace za účastníka. Najde jen toho, kdo je letos přihlášený na aktivitu,
 * na kterou jde půjčit kostým, nebo má kostým u sebe.
 *
 * nazev: Výdej a vrácení
 * ikona: hand-holding
 * pravo: 115
 * submenu_group: 1
 * submenu_order: 2
 */

/** @var Uzivatel $u */

$adresaVydeje = URL_ADMIN . '/pujcovna/ucastnik';
$pujcovnaKostymu = PujcovnaKostymu::zGlobals();
$prehledyPujcovny = PrehledyPujcovny::zGlobals();
$idUcastnikaPujcovny = (int) get('id');
// stejný okruh jako hledání, jinak by adresa s ID vydala kontakt kohokoli
$ucastnikPujcovny = $idUcastnikaPujcovny && $prehledyPujcovny->jeUcastnikPujcovny($idUcastnikaPujcovny)
    ? Uzivatel::zId($idUcastnikaPujcovny)
    : null;

$akceVydeje = (string) post('akceVydeje');
if ($akceVydeje !== '' && $ucastnikPujcovny !== null) {
    try {
        switch ($akceVydeje) {
            case 'rezervovat':
                $aktivitaRezervace = Aktivita::zId((int) post('idAktivity'))
                    ?? throw new ChybaPujcovny('Aktivita neexistuje.');
                $pujcovnaKostymu->rezervuj((int) post('idKostymu'), $aktivitaRezervace, $ucastnikPujcovny, $u, jakoSpravce: true);
                oznameniPresmeruj('Kostým je rezervovaný, účastníkovi odešel e-mail.', $adresaVydeje . '?id=' . $ucastnikPujcovny->id());
                break;
            case 'zrusit':
                $aktivitaRezervace = Aktivita::zId((int) post('idAktivity'))
                    ?? throw new ChybaPujcovny('Aktivita neexistuje.');
                $pujcovnaKostymu->zrus($aktivitaRezervace, $ucastnikPujcovny, jakoSpravce: true);
                oznameni('Rezervace je zrušená.');
                break;
            case 'vydat':
                VydejKostymu::zGlobals()->vydej((int) post('idRezervace'), $u);
                oznameni('Kostým je vydaný.');
                break;
            case 'vratit':
                $stavPriVraceni = StavPriVraceniKostymuEnum::tryFrom((string) post('stavPriVraceni'))
                    ?? throw new ChybaPujcovny('Vyber, v jakém stavu se kostým vrátil.');
                VydejKostymu::zGlobals()->vrat((int) post('idVydeje'), $u, $stavPriVraceni, (string) post('poznamka'));
                oznameni($stavPriVraceni === StavPriVraceniKostymuEnum::POSKOZENY
                    ? 'Kostým je vrácený a přepnutý do opravy.'
                    : 'Kostým je vrácený.');
                break;
        }
    } catch (ChybaPujcovny $chybaPujcovny) {
        chyba(htmlspecialchars($chybaPujcovny->getMessage()));
    }
}

$tplVydeje = new XTemplate(__DIR__ . '/ucastnik.xtpl');
$hledanyUcastnik = trim((string) get('hledat'));
$tplVydeje->assign([
    'adresaVydeje' => $adresaVydeje,
    'hledat'       => htmlspecialchars($hledanyUcastnik),
]);

if ($idUcastnikaPujcovny && $ucastnikPujcovny === null) {
    $tplVydeje->parse('vydej.mimoOkruh');
}

if ($hledanyUcastnik !== '' && $ucastnikPujcovny === null) {
    $nalezeni = FormatPujcovny::uzivatelePodleId($prehledyPujcovny->hledejUcastniky($hledanyUcastnik));
    foreach ($nalezeni as $nalezeny) {
        $tplVydeje->assign([
            'idNalezeneho'    => $nalezeny->id(),
            'jmenoNalezeneho' => htmlspecialchars(FormatPujcovny::jmeno($nalezeny)),
            'mailNalezeneho'  => htmlspecialchars((string) $nalezeny->mail()),
        ]);
        $tplVydeje->parse('vydej.vysledky.nalezeny');
    }
    if ($nalezeni === []) {
        $tplVydeje->parse('vydej.vysledky.nikdo');
    }
    $tplVydeje->parse('vydej.vysledky');
}

if ($ucastnikPujcovny !== null) {
    $tplVydeje->assign([
        'idUcastnika'    => $ucastnikPujcovny->id(),
        'jmenoUcastnika' => htmlspecialchars(FormatPujcovny::jmeno($ucastnikPujcovny)),
        'mailUcastnika'  => htmlspecialchars((string) $ucastnikPujcovny->mail()),
        'telefon'        => htmlspecialchars(trim($ucastnikPujcovny->telefon())),
    ]);

    $rezervaceUcastnikaPujcovny = $prehledyPujcovny->rezervaceUcastnika($ucastnikPujcovny->id());
    foreach ($rezervaceUcastnikaPujcovny as $radekRezervace) {
        $tplVydeje->assign([
            'idRezervace'   => (int) $radekRezervace['id_rezervace'],
            'idAktivity'    => (int) $radekRezervace['id_akce'],
            'casAktivity'   => htmlspecialchars(FormatPujcovny::casAktivity($radekRezervace['zacatek'], $radekRezervace['konec'])),
            'nazevAktivity' => htmlspecialchars($radekRezervace['nazev_akce']),
            'idKostymu'     => (int) $radekRezervace['id_kostymu'],
            'nazevKostymu'  => htmlspecialchars($radekRezervace['nazev_kostymu']),
        ]);
        if ($radekRezervace['id_vydeje'] !== null) {
            $tplVydeje->assign('vydano', htmlspecialchars(FormatPujcovny::denACas($radekRezervace['vydano'])));
            $tplVydeje->parse('vydej.ucastnik.rezervace.radek.vydany');
        } else {
            $tplVydeje->parse('vydej.ucastnik.rezervace.radek.akce');
        }
        $tplVydeje->parse('vydej.ucastnik.rezervace.radek');
    }
    if ($rezervaceUcastnikaPujcovny === []) {
        $tplVydeje->parse('vydej.ucastnik.rezervace.zadna');
    }
    $tplVydeje->parse('vydej.ucastnik.rezervace');

    $nevraceneUcastnika = $prehledyPujcovny->nevraceneUcastnika($ucastnikPujcovny->id());
    foreach ($nevraceneUcastnika as $radekVydeje) {
        $tplVydeje->assign([
            'idVydeje'      => (int) $radekVydeje['id_vydeje'],
            'nazevKostymu'  => htmlspecialchars($radekVydeje['nazev_kostymu']),
            'nazevAktivity' => htmlspecialchars($radekVydeje['nazev_akce'] ?? '(aktivita už neexistuje)'),
            'vydano'        => htmlspecialchars(FormatPujcovny::denACas($radekVydeje['vydano'])),
            'vratitDo'      => htmlspecialchars(FormatPujcovny::denACas($radekVydeje['vratit_do'])),
            'bezRezervace'  => $radekVydeje['id_rezervace'] === null
                ? '(rezervace zanikla, účastník už na aktivitu není přihlášený)'
                : '',
        ]);
        foreach (StavPriVraceniKostymuEnum::cases() as $stavPriVraceni) {
            $tplVydeje->assign([
                'hodnota' => $stavPriVraceni->value,
                'popisek' => htmlspecialchars($stavPriVraceni->getNazev()),
            ]);
            $tplVydeje->parse('vydej.ucastnik.nevracene.radek.stav');
        }
        $tplVydeje->parse('vydej.ucastnik.nevracene.radek');
    }
    if ($nevraceneUcastnika !== []) {
        $tplVydeje->parse('vydej.ucastnik.nevracene');
    }

    $aktivityKRezervaci = $pujcovnaKostymu->aktivityKRezervaci($ucastnikPujcovny);
    $idVybraneAktivity = (int) get('aktivita');
    $vybranaAktivita = null;
    foreach ($aktivityKRezervaci as $aktivitaKRezervaci) {
        $tplVydeje->assign([
            'idAktivity'    => $aktivitaKRezervaci->id(),
            'nazevAktivity' => htmlspecialchars($aktivitaKRezervaci->nazev()),
            'casAktivity'   => htmlspecialchars($aktivitaKRezervaci->denCasSkutecny()),
            'vybrana'       => $aktivitaKRezervaci->id() === $idVybraneAktivity
                ? 'aria-current="page"'
                : '',
        ]);
        if ($aktivitaKRezervaci->id() === $idVybraneAktivity) {
            $vybranaAktivita = $aktivitaKRezervaci;
        }
        $tplVydeje->parse('vydej.ucastnik.novaRezervace.aktivita');
    }
    if ($aktivityKRezervaci === []) {
        $tplVydeje->parse('vydej.ucastnik.novaRezervace.zadnaAktivita');
    }
    if ($vybranaAktivita !== null) {
        $tplVydeje->assign([
            'idAktivity'    => $vybranaAktivita->id(),
            'nazevAktivity' => htmlspecialchars($vybranaAktivita->nazev()),
        ]);
        $volneKostymy = (new KatalogKostymu())->vyber(new FiltrKatalogu(jenIds: $pujcovnaKostymu->volneKostymy($vybranaAktivita, $ucastnikPujcovny)));
        foreach ($volneKostymy as $volnyKostym) {
            $tplVydeje->assign([
                'idKostymu'    => $volnyKostym->getId(),
                'nazevKostymu' => htmlspecialchars($volnyKostym->getNazev()),
                'parametry'    => htmlspecialchars(implode(', ', array_filter([
                    $volnyKostym->getVelikost()->getNazev(),
                    $volnyKostym->getStrih()->getNazev(),
                    $volnyKostym->getKategorie(),
                ]))),
                'nahledFotky'  => htmlspecialchars($volnyKostym->getHlavniFotka()?->getUrlNahledu(60, 60) ?? ''),
            ]);
            $tplVydeje->parse('vydej.ucastnik.novaRezervace.volne.kostym');
        }
        if ($volneKostymy === []) {
            $tplVydeje->parse('vydej.ucastnik.novaRezervace.volne.zadny');
        }
        $tplVydeje->parse('vydej.ucastnik.novaRezervace.volne');
    }
    $tplVydeje->parse('vydej.ucastnik.novaRezervace');
    $tplVydeje->parse('vydej.ucastnik');
}

$tplVydeje->parse('vydej');
$tplVydeje->out('vydej');
