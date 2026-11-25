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
use Gamecon\Kostymy\VysledekRezervaceKostymuEnum;
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

$castAdresy = $url->cast(1);
if ($url->cast(2) !== null) {
    throw new Nenalezeno();
}
// Nezveřejněný katalog si prohlédne jen správce půjčovny, rezervovat v něm nejde. Vlastní rezervace ale vidí každý:
// správce rezervuje za účastníky i před zveřejněním a e-mail i program je posílají sem.
$zverejnenyKatalog = $nastaveniPujcovny->isKatalogZverejnen();
$katalogPristupny = $zverejnenyKatalog || $u?->maPravo(Pravo::ADMINISTRACE_PUJCOVNA_KOSTYMU);
if (!$katalogPristupny && $castAdresy !== 'moje') {
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
            $vysledekRezervace = $pujcovnaKostymu->rezervuj((int) post('idKostymu'), $aktivitaRezervace, $u, $u);
            oznameniPresmeruj(match ($vysledekRezervace) {
                VysledekRezervaceKostymuEnum::NOVA       => 'Kostým máš rezervovaný, potvrzení ti posíláme e-mailem.',
                VysledekRezervaceKostymuEnum::VYMENENA   => 'Kostým máš vyměněný, potvrzení ti posíláme e-mailem.',
                VysledekRezervaceKostymuEnum::BEZE_ZMENY => 'Tenhle kostým už na aktivitu rezervovaný máš.',
            }, $adresaPujcovny . '/moje');
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
if ($katalogPristupny && !$zverejnenyKatalog) {
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
        $vOprave = $kostym->getStav() === StavKostymuEnum::V_OPRAVE;
        $t->assign([
            'idKostymu'     => $kostym->getId(),
            'dotazAktivity' => $dotazAktivity,
            'nazevKostymu'  => htmlspecialchars($kostym->getNazev()),
            'parametry'     => $parametryKostymu($kostym),
            'tridaKarty'    => $vOprave
                ? 'kostymy_karta-vOprave'
                : '',
        ]);
        $hlavniFotka = $kostym->getHlavniFotka();
        if ($hlavniFotka !== null) {
            // na výšku jako kostým na ramínku, ve dvou sloupcích telefonu i v pěti na monitoru ostrá
            $t->assign('nahledFotky', htmlspecialchars($hlavniFotka->getUrlNahledu(450, 600)));
            $t->parse('kostymy.katalog.kostym.fotka');
        } else {
            $t->parse('kostymy.katalog.kostym.bezFotky');
        }
        if ($vOprave) {
            $t->parse('kostymy.katalog.kostym.vOprave');
        }
        $t->parse('kostymy.katalog.kostym');
    }
    if ($kostymy === []) {
        $t->parse($vybranaAktivita !== null
            ? 'kostymy.katalog.zadnyVolny'
            : 'kostymy.katalog.zadny');
    }
    $t->assign('pocetKostymu', FormatPujcovny::pocetKostymu(count($kostymy)));
    if ($vybranaAktivita !== null || $filtrKatalogu->velikost !== null || $filtrKatalogu->strih !== null || $filtrKatalogu->kategorie !== null) {
        $t->parse('kostymy.katalog.zrusitVyber');
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
    if ($katalogPristupny) {
        $t->parse('kostymy.moje.zpet');
    }
    foreach ($mojeRezervace as $rezervace) {
        $t->assign([
            'idKostymu'     => (int) $rezervace['id_kostymu'],
            // před zveřejněním katalogu detail kostýmu neexistuje
            'odkazKostymu'  => $katalogPristupny
                ? 'href="' . htmlspecialchars($adresaPujcovny . '/' . (int) $rezervace['id_kostymu']) . '"'
                : '',
            'nazevKostymu'  => htmlspecialchars($rezervace['nazev_kostymu']),
            'idAktivity'    => (int) $rezervace['id_akce'],
            'nazevAktivity' => htmlspecialchars($rezervace['nazev_akce']),
            'casAktivity'   => htmlspecialchars(FormatPujcovny::casAktivity($rezervace['zacatek'], $rezervace['konec'])),
        ]);
        $fotka = $katalogKostymu->dej((int) $rezervace['id_kostymu'])?->getHlavniFotka();
        if ($fotka !== null) {
            $t->assign('nahledFotky', htmlspecialchars($fotka->getUrlNahledu(240, 320)));
            $t->parse('kostymy.moje.rezervace.fotka');
        } else {
            $t->parse('kostymy.moje.rezervace.bezFotky');
        }
        if ($rezervace['id_vydeje'] !== null) {
            $t->parse('kostymy.moje.rezervace.vydany');
        }
        // aktivita, která přišla o čas, nemá čas vyzvednutí ani uzávěrku; změnu pak zařídí půjčovna
        if ($rezervace['zacatek'] !== null && $rezervace['konec'] !== null) {
            $zacatek = new DateTimeImmutable($rezervace['zacatek']);
            $konec = new DateTimeImmutable($rezervace['konec']);
            $t->assign([
                'vyzvednutiOd' => htmlspecialchars(FormatPujcovny::denACas($zacatek->modify(sprintf('-%d minutes', $nastaveniPujcovny->getVydejMinutPredZacatkem()))->format('Y-m-d H:i:s'))),
                'vratitDo'     => htmlspecialchars(FormatPujcovny::denACas($konec->modify(sprintf('+%d minutes', $nastaveniPujcovny->getVraceniMinutPoKonci()))->format('Y-m-d H:i:s'))),
            ]);
            $t->parse('kostymy.moje.rezervace.casy');
            $uzaverka = $zacatek->modify(sprintf('-%d hours', $nastaveniPujcovny->getUzaverkaHodinPredZacatkem()));
            // vydaný kostým se nemění, dokud se nevrátí
            if ($rezervace['id_vydeje'] === null && $systemoveNastaveni->ted() <= $uzaverka) {
                $t->assign('uzaverka', htmlspecialchars(FormatPujcovny::denACas($uzaverka->format('Y-m-d H:i:s'))));
                // vyměnit jde jen v katalogu
                if ($zverejnenyKatalog) {
                    $t->parse('kostymy.moje.rezervace.zmenit.vymenit');
                }
                $t->parse('kostymy.moje.rezervace.zmenit');
            } elseif ($rezervace['id_vydeje'] === null && $systemoveNastaveni->ted() < $zacatek) {
                $t->parse('kostymy.moje.rezervace.poUzaverce');
            }
        }
        $t->parse('kostymy.moje.rezervace');
    }
    if ($mojeRezervace === []) {
        if ($zverejnenyKatalog) {
            $t->parse('kostymy.moje.zadna.vyberVKatalogu');
        }
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
    $this->info()->obrazek($hlavniFotka->getUrlNahleduProSocialniSite(1200, 630));
}
$kDispozici = $kostym->getStav() === StavKostymuEnum::K_DISPOZICI;
$t->assign([
    'idKostymu'        => $kostym->getId(),
    'nazevKostymu'     => htmlspecialchars($kostym->getNazev()),
    'popis'            => nl2br(htmlspecialchars($kostym->getPopis())),
    'velikost'         => htmlspecialchars($kostym->getVelikost()->getNazev()),
    'miry'             => htmlspecialchars($kostym->getMiry()),
    'strih'            => htmlspecialchars($kostym->getStrih()->getNazev()),
    'kategorie'        => htmlspecialchars($kostym->getKategorie()),
    'dostupnost'       => htmlspecialchars($kDispozici
        ? 'k půjčení'
        : 'teď v opravě, rezervovat ho nejde'),
    'tridaDostupnosti' => $kDispozici
        ? ''
        : 'kostymy_vOprave',
    'dotazAktivity'    => (int) get('aktivita')
        ? '?aktivita=' . (int) get('aktivita')
        : '',
]);
// první fotka velká, ostatní jako náhledy pod ní; celé fotky ukáže prohlížeč fotek
$fotky = $kostym->getFotky();
foreach ($fotky as $poradiFotky => $fotka) {
    $t->assign([
        'nahledFotky' => htmlspecialchars($poradiFotky === 0
            ? $fotka->getUrlNahledu(900, 1200)
            : $fotka->getUrlNahledu(300, 300)),
        'urlFotky'    => htmlspecialchars($fotka->getUrl()),
    ]);
    $t->parse($poradiFotky === 0
        ? 'kostymy.detail.hlavniFotka'
        : 'kostymy.detail.nahledy.fotka');
}
if (count($fotky) > 1) {
    $t->parse('kostymy.detail.nahledy');
}
if ($fotky === []) {
    $t->parse('kostymy.detail.bezFotky');
}
if ($kostym->getPopis() !== '') {
    $t->parse('kostymy.detail.popis');
}
foreach (['miry', 'kategorie'] as $volitelnyUdaj) {
    if ($volitelnyUdaj === 'miry' ? $kostym->getMiry() !== '' : $kostym->getKategorie() !== '') {
        $t->parse("kostymy.detail.{$volitelnyUdaj}");
    }
}

if (!$u) {
    $t->parse('kostymy.detail.neprihlaseny');
} elseif ($kDispozici && $zverejnenyKatalog) {
    // správce si nezveřejněný katalog jen prohlíží, rezervace by stejně neprošla
    $aktivityKRezervaci = $pujcovnaKostymu->aktivityKRezervaci($u);
    $volneAktivity = [];
    $obsazeneAktivity = 0;
    $aktivityPoUzaverce = 0;
    foreach ($aktivityKRezervaci as $aktivitaKRezervaci) {
        $rezervaceNaAktivitu = $pujcovnaKostymu->rezervaceUcastnika($aktivitaKRezervaci->id(), $u->id());
        if ($rezervaceNaAktivitu?->getIdKostymu() === $kostym->getId()) {
            $t->assign([
                'nazevAktivity' => htmlspecialchars($aktivitaKRezervaci->nazev()),
                'casAktivity'   => htmlspecialchars($aktivitaKRezervaci->denCasSkutecny()),
            ]);
            $t->parse('kostymy.detail.rezervace.uzMa');
        } elseif ($rezervaceNaAktivitu !== null && $pujcovnaKostymu->isPoUzaverce($aktivitaKRezervaci)) {
            // jiný kostým na ni už vyměnit nejde, rezervace by neprošla
            $aktivityPoUzaverce++;
        } elseif (in_array($kostym->getId(), $pujcovnaKostymu->volneKostymy($aktivitaKRezervaci, $u), true)) {
            $volneAktivity[] = [$aktivitaKRezervaci, $rezervaceNaAktivitu];
        } else {
            $obsazeneAktivity++;
        }
    }
    // předvybraná je aktivita z katalogu (?aktivita=), jinak první volná
    $idsVolnychAktivit = array_map(static fn (array $volna): int => $volna[0]->id(), $volneAktivity);
    $idPredvybrane = in_array((int) get('aktivita'), $idsVolnychAktivit, true)
        ? (int) get('aktivita')
        : ($idsVolnychAktivit[0] ?? null);
    foreach ($volneAktivity as [$volnaAktivita, $rezervaceNaAktivitu]) {
        $t->assign([
            'idAktivity'    => $volnaAktivita->id(),
            'nazevAktivity' => htmlspecialchars($volnaAktivita->nazev()),
            'casAktivity'   => htmlspecialchars($volnaAktivita->denCasSkutecny()),
            'vybrano'       => $volnaAktivita->id() === $idPredvybrane
                ? 'checked'
                : '',
        ]);
        if ($rezervaceNaAktivitu !== null) {
            $t->assign('nazevVymeneneho', htmlspecialchars($rezervaceNaAktivitu->getNazevKostymu()));
            $t->parse('kostymy.detail.rezervace.formular.aktivita.vymena');
        }
        $t->parse('kostymy.detail.rezervace.formular.aktivita');
    }
    if ($volneAktivity !== []) {
        $t->parse('kostymy.detail.rezervace.formular');
    } elseif ($aktivityKRezervaci === []) {
        $t->parse('kostymy.detail.rezervace.zadnaAktivita');
    } else {
        if ($obsazeneAktivity > 0) {
            $t->parse('kostymy.detail.rezervace.obsazeny');
        }
        if ($aktivityPoUzaverce > 0) {
            $t->parse('kostymy.detail.rezervace.poUzaverce');
        }
    }
    $t->parse('kostymy.detail.rezervace');
}
$t->parse('kostymy.detail');
