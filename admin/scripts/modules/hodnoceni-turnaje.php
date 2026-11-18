<?php

declare(strict_types=1);

use Gamecon\Cas\DateTimeCz;
use Gamecon\HodnoceniTurnaje\ChybaSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\HodnoceniTurnaje;
use Gamecon\HodnoceniTurnaje\HodnoceniTurnajeException;
use Gamecon\HodnoceniTurnaje\ParserSablonyHodnoceni;
use Gamecon\Pravo;
use Gamecon\Web\VerzeSouboru;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Clock\NativeClock;

/**
 * Hodnocení skupin turnaje D&D: vypravěč ohodnotí skupinu, kterou vedl v kole, podle kritérií, která nahrál org
 * turnaje; org turnaje vidí výsledky a stáhne je. Pravidla jsou v Gamecon\HodnoceniTurnaje\HodnoceniTurnaje.
 *
 * nazev: Hodnocení turnaje
 * ikona: ranking-star
 * pravo: 117
 * prava: 109, 117
 */

/**
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

$hodnoceniTurnaje = new HodnoceniTurnaje($systemoveNastaveni->rocnik(), new NativeClock());
$jeOrgTurnaje = $u->maPravo(Pravo::ADMINISTRACE_HODNOCENI_TURNAJE);
$idUzivatele = (int)$u->id();

$export = get('export');
if ($jeOrgTurnaje && in_array($export, ['souhrn', 'detail'], true)) {
    ['hlavicky' => $hlavicky, 'radky' => $radky] = $export === 'souhrn'
        ? $hodnoceniTurnaje->getExportVysledku()
        : $hodnoceniTurnaje->getExportHodnoceni();
    Report::zPoli($hlavicky, $radky)->tXlsx(sprintf(
        $export === 'souhrn'
            ? 'Výsledky turnaje D&D %d'
            : 'Hodnocení turnaje D&D %d',
        $hodnoceniTurnaje->getRok(),
    ));
    exit;
}

// Po chybě se stránka vykreslí znovu s tím, co kdo odeslal: text kritérií ani vybrané hodnoty se neztratí.
$odeslanyText = null;
$odeslaneHodnoty = null;
$akce = post('akce');
if ($akce !== null) {
    try {
        // oznameni() přesměruje a skončí
        if ($akce === 'ulozit-hodnoceni') {
            $odeslaneHodnoty = is_array(post('hodnoty'))
                ? post('hodnoty')
                : [];
            $hodnoceniTurnaje->ulozHodnoceni(
                (int)post('aktivita'),
                (int)post('tym'),
                $odeslaneHodnoty,
                $idUzivatele,
                $jeOrgTurnaje,
            );
            oznameni('Hodnocení je uložené.');
        }
        if (! $jeOrgTurnaje) {
            throw new HodnoceniTurnajeException('Tohle smí jen org turnaje.');
        }
        if ($akce === 'nahrat-sablonu') {
            $odeslanyText = (string)post('text');
            $text = $odeslanyText;
            $soubor = $_FILES['soubor'] ?? null;
            if (is_array($soubor) && ($soubor['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ($soubor['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($soubor['tmp_name'])) {
                    throw new HodnoceniTurnajeException('Soubor se nepodařilo nahrát, zkus to znovu.');
                }
                if ($soubor['size'] > ParserSablonyHodnoceni::MAX_DELKA_TEXTU) {
                    throw new HodnoceniTurnajeException('Soubor je moc velký, kritéria jsou krátký text.');
                }
                $text = (string)file_get_contents($soubor['tmp_name']);
                $odeslanyText = ParserSablonyHodnoceni::normalizuj($text);
            }
            if (trim($text) === '') {
                throw new HodnoceniTurnajeException('Vyber soubor s kritérii, nebo je vlož do pole.');
            }
            $smazano = $hodnoceniTurnaje->nahrajSablonu($text, $idUzivatele, post('smazat_hodnoceni') === '1');
            oznameni($smazano > 0
                ? "Kritéria jsou nahraná, {$smazano} hodnocení se smazalo."
                : 'Kritéria jsou nahraná.');
        }
        if ($akce === 'smazat-hodnoceni') {
            $hodnoceniTurnaje->smazHodnoceni((int)post('id_hodnoceni'));
            oznameni('Hodnocení je smazané.');
        }
        if ($akce === 'uzavrit' || $akce === 'otevrit') {
            $hodnoceniTurnaje->setUzavreno($akce === 'uzavrit');
            oznameni($akce === 'uzavrit'
                ? 'Hodnocení je uzavřené, vypravěči ho už nezmění.'
                : 'Hodnocení je zase otevřené.');
        }
        throw new HodnoceniTurnajeException('Neznámá akce.');
    } catch (ChybaSablonyHodnoceni $chybaSablony) {
        // zprávy obsahují text ze souboru, chyba() vypisuje HTML
        chyba(
            'Kritéria nejde nahrát:<br>' . implode('<br>', array_map('htmlspecialchars', $chybaSablony->getChyby())),
            false,
        );
    } catch (HodnoceniTurnajeException $hodnoceniTurnajeException) {
        chyba(htmlspecialchars($hodnoceniTurnajeException->getMessage()), false);
    }
}

$t = new XTemplate(__DIR__ . '/hodnoceni-turnaje.xtpl');
$t->assign([
    'cssVerze' => (new VerzeSouboru(__DIR__ . '/../../files/design', 'css'))->getVerze('hodnoceni-turnaje'),
    'rok'      => $hodnoceniTurnaje->getRok(),
]);

$sablona = $hodnoceniTurnaje->getSablona();
if ($sablona === null && ! $jeOrgTurnaje) {
    $t->parse('hodnoceni.bezSablony');
}
if ($sablona?->isUzavrena()) {
    $t->parse('hodnoceni.uzavreno');
}

$formatujCas = static fn (DateTimeInterface $cas, string $format): string => DateTimeCz::createFromInterface($cas)
    ->format($format);

$kola = $hodnoceniTurnaje->getKola($jeOrgTurnaje
    ? null
    : $idUzivatele);
$idAkce = (int)(post('aktivita') ?? get('aktivita') ?? 0);
$idTymu = (int)(post('tym') ?? get('tym') ?? 0);
$vybraneKolo = null;
foreach ($kola as $kolo) {
    if ($kolo['idAkce'] === $idAkce) {
        $vybraneKolo = $kolo;
    }
}
if ($vybraneKolo === null && count($kola) === 1) {
    $vybraneKolo = $kola[0];
}
$vybranyTym = null;
foreach ($vybraneKolo['tymy'] ?? [] as $tym) {
    if ($tym['id'] === $idTymu) {
        $vybranyTym = $tym;
    }
}
if ($vybranyTym === null && count($vybraneKolo['tymy'] ?? []) === 1) {
    $vybranyTym = $vybraneKolo['tymy'][0];
}

if ($kola === []) {
    // org turnaje vidí i bez kol výsledky a kritéria, hlášku nepotřebuje
    if (! $jeOrgTurnaje) {
        $t->parse('hodnoceni.bezKol');
    }
} elseif ($sablona !== null) {
    foreach ($kola as $kolo) {
        $t->assign([
            'idAkce'    => $kolo['idAkce'],
            'vybrano'   => $kolo === $vybraneKolo
                ? 'selected'
                : '',
            'popisKola' => htmlspecialchars(implode(' · ', array_filter([
                "{$kolo['kolo']}. kolo",
                $kolo['nazevTurnaje'],
                $kolo['zacatek'] !== null
                    ? $formatujCas($kolo['zacatek'], 'l G:i')
                    : null,
            ]))),
        ]);
        $t->parse('hodnoceni.vyber.kolo');
    }
    foreach ($vybraneKolo['tymy'] ?? [] as $tym) {
        $t->assign([
            'idTymu'    => $tym['id'],
            'nazevTymu' => htmlspecialchars($tym['nazev']),
            'vybrano'   => $tym === $vybranyTym
                ? 'selected'
                : '',
        ]);
        $t->parse('hodnoceni.vyber.tym');
    }
    $t->assign([
        'zakazatTym'  => $vybraneKolo === null || $vybraneKolo['tymy'] === []
            ? 'disabled'
            : '',
        'textBezTymu' => match (true) {
            $vybraneKolo === null       => 'nejdřív vyber kolo',
            $vybraneKolo['tymy'] === [] => 'v kole nehrála žádná skupina',
            default                     => 'vyber skupinu',
        },
    ]);

    if ($vybraneKolo !== null && $vybranyTym !== null) {
        $hodnoceni = $hodnoceniTurnaje->getHodnoceni($vybranyTym['id'], $vybraneKolo['kolo']);
        $duvodZakazu = $hodnoceniTurnaje->getDuvodZakazu(
            $vybraneKolo['idAkce'],
            $vybranyTym['id'],
            $idUzivatele,
            $jeOrgTurnaje,
        );
        $hodnoty = $odeslaneHodnoty ?? $hodnoceni['hodnoty'] ?? [];
        $t->assign([
            'idAkce'      => $vybraneKolo['idAkce'],
            'idTymu'      => $vybranyTym['id'],
            'urlFormulare' => htmlspecialchars('hodnoceni-turnaje?' . http_build_query([
                'aktivita' => $vybraneKolo['idAkce'],
                'tym'      => $vybranyTym['id'],
            ])),
            'stav'        => htmlspecialchars($hodnoceni === null
                ? 'Skupina v tomhle kole ještě hodnocení nemá.'
                : sprintf(
                    'Hodnotil%s %s, naposledy %s.',
                    $hodnoceni['hodnotitel'] === null
                        ? ''
                        : '(a)',
                    $hodnoceni['hodnotitel'] ?? 'někdo, kdo už nemá účet',
                    $formatujCas($hodnoceni['upraveno'], 'j. n. G:i'),
                )),
            'zakazano'    => $duvodZakazu === null
                ? ''
                : 'disabled',
            'textUlozit'  => $hodnoceni === null
                ? 'Uložit hodnocení'
                : 'Uložit změny',
        ]);
        if ($duvodZakazu !== null) {
            $t->assign('duvodZakazu', htmlspecialchars($duvodZakazu));
            $t->parse('hodnoceni.vyber.formular.duvodZakazu');
        }
        foreach ($sablona->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                $vybranaHodnota = $hodnoty[$kriterium->id] ?? null;
                foreach ($sablona->skala as $hodnota) {
                    $t->assign([
                        'hodnota' => $hodnota,
                        'vybrano' => $vybranaHodnota !== null && (string)$vybranaHodnota === (string)$hodnota
                            ? 'selected'
                            : '',
                    ]);
                    $t->parse('hodnoceni.vyber.formular.kategorie.kriterium.hodnota');
                }
                $t->assign([
                    'idKriteria'    => $kriterium->id,
                    'nazevKriteria' => htmlspecialchars($kriterium->nazev),
                ]);
                $t->parse('hodnoceni.vyber.formular.kategorie.kriterium');
            }
            $t->assign('nazevKategorie', htmlspecialchars($kategorie->nazev));
            $t->parse('hodnoceni.vyber.formular.kategorie');
        }
        if ($duvodZakazu === null) {
            $t->parse('hodnoceni.vyber.formular.ulozit');
        }
        $t->parse('hodnoceni.vyber.formular');
    }
    $t->parse('hodnoceni.vyber');
}

if ($jeOrgTurnaje) {
    $vysledky = $hodnoceniTurnaje->getVysledky();
    if ($vysledky === []) {
        $t->assign('textBezVysledku', $sablona === null
            ? 'Výsledky budou, až nahraješ kritéria hodnocení (dole na stránce).'
            : 'Zatím tu nejsou žádné skupiny turnaje.');
        $t->parse('hodnoceni.org.bezVysledku');
    } else {
        foreach ($sablona->kategorie ?? [] as $kategorie) {
            $t->assign('nazevKategorie', htmlspecialchars($kategorie->nazev));
            $t->parse('hodnoceni.org.vysledky.hlavickaKategorie');
        }
        foreach ($vysledky as $vysledek) {
            foreach ($vysledek['souctyKategorii'] as $soucet) {
                $t->assign('soucet', $soucet);
                $t->parse('hodnoceni.org.vysledky.radek.soucet');
            }
            $t->assign([
                'poradi'       => HodnoceniTurnaje::getPopisPoradi($vysledek['poradiOd'], $vysledek['poradiDo']),
                'nazevTymu'    => htmlspecialchars($vysledek['nazevTymu']),
                'nazevTurnaje' => htmlspecialchars($vysledek['nazevTurnaje']),
                'celkem'       => $vysledek['celkem'],
                'hodnocenoKol' => $vysledek['hodnocenoKol'],
                'kol'          => $vysledek['kol'],
                'neuplna'      => $vysledek['uplna']
                    ? ''
                    : ' ⚠️',
                'titulekKol'   => $vysledek['uplna']
                    ? 'Ohodnocená všechna kola'
                    : 'Chybí hodnocení některého kola',
                'tridaRadku'   => $vysledek['uplna']
                    ? ''
                    : 'hodnoceni-neuplna',
            ]);
            $t->parse('hodnoceni.org.vysledky.radek');
        }
        $t->parse('hodnoceni.org.vysledky');
    }

    $vsechnaHodnoceni = $hodnoceniTurnaje->getVsechnaHodnoceni();
    if ($vsechnaHodnoceni === []) {
        $t->parse('hodnoceni.org.bezHodnoceni');
    } else {
        foreach ($vsechnaHodnoceni as $hodnoceni) {
            $t->assign([
                'idHodnoceni'      => $hodnoceni['id'],
                'nazevTurnaje'     => htmlspecialchars($hodnoceni['nazevTurnaje']),
                'nazevTymu'        => htmlspecialchars($hodnoceni['nazevTymu']),
                'kolo'             => $hodnoceni['kolo'],
                'hodnotitel'       => htmlspecialchars($hodnoceni['hodnotitel'] ?? '–'),
                'upraveno'         => $formatujCas($hodnoceni['upraveno'], 'j. n. G:i'),
                'celkem'           => $hodnoceni['celkem'],
                'urlUpravit'       => htmlspecialchars('hodnoceni-turnaje?' . http_build_query([
                    'aktivita' => $hodnoceni['idAkce'],
                    'tym'      => $hodnoceni['idTymu'],
                ])),
                'potvrzeniSmazani' => htmlspecialchars(
                    "Smazat hodnocení skupiny „{$hodnoceni['nazevTymu']}“ v {$hodnoceni['kolo']}. kole?",
                ),
            ]);
            $t->parse('hodnoceni.org.hodnoceniSeznam.radek');
        }
        $t->parse('hodnoceni.org.hodnoceniSeznam');
    }
    if ($sablona !== null) {
        $t->assign($sablona->isUzavrena()
            ? [
                'akceUzavreni'  => 'otevrit',
                'textUzavreni'  => 'Otevřít hodnocení',
                'popisUzavreni' => 'Vypravěči pak zase můžou hodnotit a měnit svá hodnocení.',
            ]
            : [
                'akceUzavreni'  => 'uzavrit',
                'textUzavreni'  => 'Uzavřít hodnocení',
                'popisUzavreni' => 'Vypravěči pak už nic nezmění, org turnaje ano.',
            ]);
        $t->parse('hodnoceni.org.uzavreni');

        $t->assign('skala', htmlspecialchars(implode(' ', $sablona->skala)));
        foreach ($sablona->kategorie as $kategorie) {
            foreach ($kategorie->kriteria as $kriterium) {
                $t->assign('nazevKriteria', htmlspecialchars($kriterium->nazev));
                $t->parse('hodnoceni.org.nahled.kategorie.kriterium');
            }
            $t->assign('nazevKategorie', htmlspecialchars($kategorie->nazev));
            $t->parse('hodnoceni.org.nahled.kategorie');
        }
        $t->parse('hodnoceni.org.nahled');
    }
    $pocetHodnoceni = $hodnoceniTurnaje->getPocetHodnoceni();
    if ($pocetHodnoceni > 0) {
        $t->assign('pocetHodnoceni', $pocetHodnoceni);
        $t->parse('hodnoceni.org.smazatHodnoceni');
    }
    $t->assign([
        'zdroj'  => htmlspecialchars($odeslanyText ?? $sablona->zdroj ?? ''),
        'ukazka' => htmlspecialchars(ParserSablonyHodnoceni::UKAZKA),
    ]);
    $t->parse('hodnoceni.org');
}

$t->parse('hodnoceni');
$t->out('hodnoceni');
