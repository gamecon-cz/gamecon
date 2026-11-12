<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Kostymy\ChybaPujcovny;
use Gamecon\Kostymy\FiltrKatalogu;
use Gamecon\Kostymy\FormatPujcovny;
use Gamecon\Kostymy\KatalogKostymu;
use Gamecon\Kostymy\Kostym;
use Gamecon\Kostymy\PrehledyPujcovny;
use Gamecon\Kostymy\PujcovnaKostymu;
use Gamecon\Kostymy\StavKostymuEnum;
use Gamecon\Kostymy\StrihKostymuEnum;
use Gamecon\Kostymy\VelikostKostymuEnum;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Půjčovna kostýmů: katalog (/kostymy), detail s rezervací (/kostymy/<id>) a vlastní rezervace (/kostymy/moje).
 * Viz docs/generated/pujcovna-kostymu.md
 *
 * @var Modul $this
 * @var Url $url
 * @var \Gamecon\XTemplate\XTemplate $t
 * @var Uzivatel|null $u
 * @var SystemoveNastaveni $systemoveNastaveni
 */

$this->blackarrowStyl(true);
$this->info()->nazev('Půjčovna kostýmů');

$pujcovnaKostymu = PujcovnaKostymu::zSystemovehoNastaveni($systemoveNastaveni);
$nastaveniPujcovny = $pujcovnaKostymu->getNastaveni();
$katalogKostymu = new KatalogKostymu();
$adresaPujcovny = URL_WEBU . '/kostymy';

// nezveřejněný katalog si prohlédne jen správce půjčovny, rezervovat v něm nejde
if (!$nastaveniPujcovny->isKatalogZverejnen() && !$u?->maPravo(Pravo::ADMINISTRACE_PUJCOVNA_KOSTYMU)) {
    throw new Nenalezeno();
}
$castAdresy = $url->cast(1);
if ($url->cast(2) !== null) {
    throw new Nenalezeno();
}

$akceKostymu = (string) post('akceKostymu');
if ($akceKostymu !== '') {
    if (!$u) {
        throw new Neprihlasen();
    }
    try {
        $aktivitaRezervace = Aktivita::zId((int) post('idAktivity'), false, $systemoveNastaveni)
            ?? throw new ChybaPujcovny('Aktivita neexistuje.');
        if ($akceKostymu === 'rezervovat') {
            $pujcovnaKostymu->rezervuj((int) post('idKostymu'), $aktivitaRezervace, $u, $u);
            oznameniPresmeruj('Kostým máš rezervovaný, potvrzení ti přišlo e-mailem.', $adresaPujcovny . '/moje');
        }
        if ($akceKostymu === 'zrusit') {
            $pujcovnaKostymu->zrus($aktivitaRezervace, $u);
            oznameni('Rezervace kostýmu je zrušená.');
        }
    } catch (ChybaPujcovny $chybaPujcovny) {
        chyba(htmlspecialchars($chybaPujcovny->getMessage()));
    }
}

$t->assign([
    'adresaPujcovny'  => $adresaPujcovny,
    'casNaVydej'      => $nastaveniPujcovny->getVydejMinutPredZacatkem(),
    'casNaVraceni'    => $nastaveniPujcovny->getVraceniMinutPoKonci(),
    'uzaverkaHodin'   => $nastaveniPujcovny->getUzaverkaHodinPredZacatkem(),
]);
if (!$nastaveniPujcovny->isKatalogZverejnen()) {
    $t->parse('kostymy.nezverejneny');
}

$parametryKostymu = static fn (Kostym $kostym): string => htmlspecialchars(implode(' · ', array_filter([
    $kostym->getVelikost()->getNazev(),
    $kostym->getStrih()->getNazev(),
    $kostym->getKategorie(),
])));

if ($castAdresy === null) {
    // katalog s filtrem; po výběru vlastní aktivity jen kostýmy volné v jejím čase
    $aktivityUcastnika = $u
        ? $pujcovnaKostymu->aktivityKRezervaci($u)
        : [];
    $idVybraneAktivity = (int) get('aktivita');
    $vybranaAktivita = current(array_filter(
        $aktivityUcastnika,
        static fn (Aktivita $aktivita): bool => $aktivita->id() === $idVybraneAktivity,
    )) ?: null;
    $filtrKatalogu = FiltrKatalogu::zParametru($_GET);
    if ($vybranaAktivita !== null) {
        $filtrKatalogu = $filtrKatalogu->sJenIds($pujcovnaKostymu->volneKostymy($vybranaAktivita, $u))->sJenKDispozici();
    }

    foreach ([
        'velikost' => [VelikostKostymuEnum::cases(), $filtrKatalogu->velikost],
        'strih'    => [StrihKostymuEnum::cases(), $filtrKatalogu->strih],
    ] as $blokFiltru => [$moznosti, $vybrana]) {
        foreach ($moznosti as $moznost) {
            $t->assign([
                'hodnota' => $moznost->value,
                'popisek' => htmlspecialchars($moznost->getNazev()),
                'vybrano' => $moznost === $vybrana
                    ? 'selected'
                    : '',
            ]);
            $t->parse("kostymy.katalog.{$blokFiltru}");
        }
    }
    foreach ($katalogKostymu->kategorie() as $kategorie) {
        $t->assign([
            'hodnota' => htmlspecialchars($kategorie),
            'popisek' => htmlspecialchars($kategorie),
            'vybrano' => $kategorie === $filtrKatalogu->kategorie
                ? 'selected'
                : '',
        ]);
        $t->parse('kostymy.katalog.kategorie');
    }
    foreach ($aktivityUcastnika as $aktivitaUcastnika) {
        $t->assign([
            'hodnota' => $aktivitaUcastnika->id(),
            'popisek' => htmlspecialchars($aktivitaUcastnika->nazev() . ' (' . $aktivitaUcastnika->denCasSkutecny() . ')'),
            'vybrano' => $aktivitaUcastnika === $vybranaAktivita
                ? 'selected'
                : '',
        ]);
        $t->parse('kostymy.katalog.filtrAktivit.aktivita');
    }
    if ($aktivityUcastnika !== []) {
        $t->parse('kostymy.katalog.filtrAktivit');
    }

    $kostymy = array_values(array_filter(
        $katalogKostymu->vyber($filtrKatalogu),
        static fn (Kostym $kostym): bool => $kostym->getStav() !== StavKostymuEnum::VYRAZENY,
    ));
    $dotazAktivity = $vybranaAktivita !== null
        ? '?aktivita=' . $vybranaAktivita->id()
        : '';
    foreach ($kostymy as $kostym) {
        $t->assign([
            'idKostymu'    => $kostym->getId(),
            'dotazAktivity' => $dotazAktivity,
            'nazevKostymu' => htmlspecialchars($kostym->getNazev()),
            'parametry'    => $parametryKostymu($kostym),
            'nahledFotky'  => htmlspecialchars($kostym->getHlavniFotka()?->getUrlNahledu(400, 400) ?? ''),
        ]);
        if ($kostym->getHlavniFotka() !== null) {
            $t->parse('kostymy.katalog.kostym.fotka');
        }
        if ($kostym->getStav() === StavKostymuEnum::V_OPRAVE) {
            $t->parse('kostymy.katalog.kostym.vOprave');
        }
        $t->parse('kostymy.katalog.kostym');
    }
    if ($kostymy === []) {
        $t->parse($vybranaAktivita !== null
            ? 'kostymy.katalog.zadnyVolny'
            : 'kostymy.katalog.zadny');
    }
    if ($u) {
        $t->parse('kostymy.katalog.mojeRezervace');
    } else {
        $t->parse('kostymy.katalog.neprihlaseny');
    }
    $t->parse('kostymy.katalog');

    return 1;
}

if ($castAdresy === 'moje') {
    if (!$u) {
        throw new Neprihlasen();
    }
    $this->info()->nazev('Moje kostýmy');
    $prehledyPujcovny = new PrehledyPujcovny($systemoveNastaveni, $nastaveniPujcovny);
    $mojeRezervace = $prehledyPujcovny->rezervaceUcastnika($u->id());
    foreach ($mojeRezervace as $rezervace) {
        $zacatek = new DateTimeImmutable($rezervace['zacatek']);
        $konec = new DateTimeImmutable($rezervace['konec']);
        $t->assign([
            'idKostymu'    => (int) $rezervace['id_kostymu'],
            'nazevKostymu' => htmlspecialchars($rezervace['nazev_kostymu']),
            'idAktivity'   => (int) $rezervace['id_akce'],
            'nazevAktivity' => htmlspecialchars($rezervace['nazev_akce']),
            'casAktivity'  => htmlspecialchars(FormatPujcovny::casAktivity($rezervace['zacatek'], $rezervace['konec'])),
            'vyzvednutiOd' => htmlspecialchars(FormatPujcovny::denACas($zacatek->modify(sprintf('-%d minutes', $nastaveniPujcovny->getVydejMinutPredZacatkem()))->format('Y-m-d H:i:s'))),
            'vratitDo'     => htmlspecialchars(FormatPujcovny::denACas($konec->modify(sprintf('+%d minutes', $nastaveniPujcovny->getVraceniMinutPoKonci()))->format('Y-m-d H:i:s'))),
        ]);
        $uzaverka = $zacatek->modify(sprintf('-%d hours', $nastaveniPujcovny->getUzaverkaHodinPredZacatkem()));
        if ($rezervace['id_vydeje'] !== null) {
            $t->parse('kostymy.moje.rezervace.vydany');
        } elseif ($systemoveNastaveni->ted() <= $uzaverka) {
            $t->assign('uzaverka', htmlspecialchars(FormatPujcovny::denACas($uzaverka->format('Y-m-d H:i:s'))));
            $t->parse('kostymy.moje.rezervace.zmenit');
        } elseif ($systemoveNastaveni->ted() < $zacatek) {
            $t->parse('kostymy.moje.rezervace.poUzaverce');
        }
        $t->parse('kostymy.moje.rezervace');
    }
    if ($mojeRezervace === []) {
        $t->parse('kostymy.moje.zadna');
    }
    foreach ($prehledyPujcovny->nevraceneUcastnika($u->id()) as $nevraceny) {
        $t->assign([
            'nazevKostymu' => htmlspecialchars($nevraceny['nazev_kostymu']),
            'vratitDo'     => htmlspecialchars(FormatPujcovny::denACas($nevraceny['vratit_do']) ?: 'co nejdřív'),
        ]);
        $t->parse('kostymy.moje.nevraceny');
    }
    $t->parse('kostymy.moje');

    return 1;
}

if (!ctype_digit($castAdresy)) {
    throw new Nenalezeno();
}
$kostym = $katalogKostymu->dej((int) $castAdresy);
if ($kostym === null || $kostym->getStav() === StavKostymuEnum::VYRAZENY) {
    throw new Nenalezeno();
}
$this->info()->nazev($kostym->getNazev() . ' – půjčovna kostýmů');
$hlavniFotka = $kostym->getHlavniFotka();
if ($hlavniFotka !== null) {
    $this->info()->obrazek($hlavniFotka->getUrlNahledu(1200, 630));
}
$t->assign([
    'idKostymu'    => $kostym->getId(),
    'nazevKostymu' => htmlspecialchars($kostym->getNazev()),
    'popis'        => nl2br(htmlspecialchars($kostym->getPopis())),
    'velikost'     => htmlspecialchars($kostym->getVelikost()->getNazev()),
    'miry'         => htmlspecialchars($kostym->getMiry()),
    'strih'        => htmlspecialchars($kostym->getStrih()->getNazev()),
    'kategorie'    => htmlspecialchars($kostym->getKategorie()),
    'dostupnost'   => htmlspecialchars($kostym->getStav() === StavKostymuEnum::K_DISPOZICI
        ? 'k půjčení'
        : 'teď v opravě, rezervovat ho nejde'),
    'dotazAktivity' => (int) get('aktivita')
        ? '?aktivita=' . (int) get('aktivita')
        : '',
]);
foreach ($kostym->getFotky() as $fotka) {
    $t->assign([
        'nahledFotky' => htmlspecialchars($fotka->getUrlNahledu(600, 600)),
        'urlFotky'    => htmlspecialchars($fotka->getUrl()),
    ]);
    $t->parse('kostymy.detail.fotka');
}
foreach (['miry', 'kategorie'] as $volitelnyUdaj) {
    if ($volitelnyUdaj === 'miry' ? $kostym->getMiry() !== '' : $kostym->getKategorie() !== '') {
        $t->parse("kostymy.detail.{$volitelnyUdaj}");
    }
}

if (!$u) {
    $t->parse('kostymy.detail.neprihlaseny');
} elseif ($kostym->getStav() === StavKostymuEnum::K_DISPOZICI) {
    $aktivityKRezervaci = $pujcovnaKostymu->aktivityKRezervaci($u);
    $volneAktivity = 0;
    foreach ($aktivityKRezervaci as $aktivitaKRezervaci) {
        $rezervaceNaAktivitu = $pujcovnaKostymu->rezervaceUcastnika($aktivitaKRezervaci->id(), $u->id());
        $t->assign([
            'idAktivity'    => $aktivitaKRezervaci->id(),
            'nazevAktivity' => htmlspecialchars($aktivitaKRezervaci->nazev() . ' (' . $aktivitaKRezervaci->denCasSkutecny() . ')'),
            'vybrano'       => $aktivitaKRezervaci->id() === (int) get('aktivita')
                ? 'selected'
                : '',
        ]);
        if ($rezervaceNaAktivitu?->getIdKostymu() === $kostym->getId()) {
            $t->parse('kostymy.detail.rezervace.uzMa');
        } elseif (in_array($kostym->getId(), $pujcovnaKostymu->volneKostymy($aktivitaKRezervaci, $u), true)) {
            $t->assign('vymena', $rezervaceNaAktivitu !== null
                ? ' – vymění ' . htmlspecialchars($rezervaceNaAktivitu->getNazevKostymu())
                : '');
            $t->parse('kostymy.detail.rezervace.formular.aktivita');
            $volneAktivity++;
        }
    }
    if ($volneAktivity > 0) {
        $t->parse('kostymy.detail.rezervace.formular');
    } elseif ($aktivityKRezervaci === []) {
        $t->parse('kostymy.detail.rezervace.zadnaAktivita');
    } else {
        $t->parse('kostymy.detail.rezervace.obsazeny');
    }
    $t->parse('kostymy.detail.rezervace');
}
$t->parse('kostymy.detail');
