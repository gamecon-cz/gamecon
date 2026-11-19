<?php

declare(strict_types=1);

use Gamecon\Cas\DateTimeCz;
use Gamecon\HodnoceniTurnaje\ChybaSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\FormularKriterii;
use Gamecon\HodnoceniTurnaje\HodnoceniTurnaje;
use Gamecon\HodnoceniTurnaje\HodnoceniTurnajeException;
use Gamecon\HodnoceniTurnaje\ParserSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\SouborSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\ZmenaKriteriiChcePotvrzeni;
use Gamecon\Pravo;
use Gamecon\Web\VerzeSouboru;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Clock\NativeClock;

/**
 * Hodnocení skupin turnaje D&D: vypravěč ohodnotí skupinu, kterou vedl v kole, podle kritérií, která zadal org
 * turnaje (v editoru na stránce, nebo je do něj načetl ze souboru); org turnaje vidí výsledky a stáhne je. Pravidla jsou v Gamecon\HodnoceniTurnaje\HodnoceniTurnaje.
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
if ($jeOrgTurnaje && in_array($export, ['kriteria-xlsx', 'kriteria-csv'], true)) {
    // bez uložených kritérií vzor, ať je vidět, jak tabulka vypadá
    $report = Report::zPoli(
        ParserSablonyHodnoceni::HLAVICKA_TABULKY,
        ($hodnoceniTurnaje->getSablona() ?? ParserSablonyHodnoceni::rozeber(ParserSablonyHodnoceni::UKAZKA))
            ->getRadkyTabulky(),
    );
    $nazevReportu = sprintf('Kritéria hodnocení turnaje D&D %d', $hodnoceniTurnaje->getRok());
    if ($export === 'kriteria-xlsx') {
        $report->tXlsx($nazevReportu);
    } else {
        $report->tCsv($nazevReportu);
    }
    exit;
}

// Po chybě se stránka vykreslí znovu s tím, co kdo odeslal: kritéria v editoru ani vybrané hodnoty se neztratí.
$odeslaneHodnoty = null;
/** editor ukáže tohle místo uložených kritérií: odeslaná, která nešla uložit, nebo načtená ze souboru */
$formularKriterii = null;
$nactenoZeSouboru = null;
$zmenaChticiPotvrzeni = null;
$akce = post('akce');
if ($akce !== null) {
    try {
        // oznameni() přesměruje a skončí
        if ($akce === 'ulozit-hodnoceni') {
            $odeslaneHodnoty = is_array(post('hodnoty'))
                ? post('hodnoty')
                : [];
            $zaVypravece = post('za_vypravece');
            $hodnoceniTurnaje->ulozHodnoceni(
                (int)post('aktivita'),
                (int)post('tym'),
                $odeslaneHodnoty,
                $idUzivatele,
                $jeOrgTurnaje,
                $jeOrgTurnaje && is_string($zaVypravece) && $zaVypravece !== ''
                    ? (int)$zaVypravece
                    : null,
            );
            oznameni('Hodnocení je uložené.');
        }
        if (! $jeOrgTurnaje) {
            throw new HodnoceniTurnajeException('Tohle smí jen org turnaje.');
        }
        if ($akce === 'ulozit-kriteria') {
            $formularKriterii = FormularKriterii::zPostu(post('skala'), post('kategorie'));
            $smazano = $hodnoceniTurnaje->ulozSablonu(
                $formularKriterii->getSablona(),
                $idUzivatele,
                is_string(post('potvrzeni'))
                    ? post('potvrzeni')
                    : null,
            );
            oznameni($smazano > 0
                ? "Kritéria jsou uložená, {$smazano} hodnocení se smazalo."
                : 'Kritéria jsou uložená.');
        } elseif ($akce === 'nacist-soubor') {
            $soubor = $_FILES['soubor'] ?? null;
            if (! is_array($soubor) || ($soubor['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new HodnoceniTurnajeException('Vyber soubor s kritérii.');
            }
            if ($soubor['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($soubor['tmp_name'])) {
                throw new HodnoceniTurnajeException('Soubor se nepodařilo nahrát, zkus to znovu.');
            }
            $zeSouboru = SouborSablonyHodnoceni::nacti(
                $soubor['tmp_name'],
                (string)$soubor['name'],
                $hodnoceniTurnaje->getSablona()?->skala ?? ParserSablonyHodnoceni::VYCHOZI_SKALA,
            );
            // neukládá se: editor ukáže, co je nové, a uloží org
            $formularKriterii = FormularKriterii::zeSablony($hodnoceniTurnaje->sparujPodleNazvu($zeSouboru));
            $nactenoZeSouboru = (string)$soubor['name'];
            oznameni('Kritéria ze souboru jsou v editoru dole. Ještě nejsou uložená: zkontroluj je a ulož.', false);
        } elseif ($akce === 'smazat-hodnoceni') {
            $hodnoceniTurnaje->smazHodnoceni((int)post('id_hodnoceni'));
            oznameni('Hodnocení je smazané.');
        } elseif ($akce === 'uzavrit' || $akce === 'otevrit') {
            $hodnoceniTurnaje->setUzavreno($akce === 'uzavrit');
            oznameni($akce === 'uzavrit'
                ? 'Hodnocení je uzavřené, vypravěči ho už nezmění.'
                : 'Hodnocení je zase otevřené.');
        } else {
            throw new HodnoceniTurnajeException('Neznámá akce.');
        }
    } catch (ZmenaKriteriiChcePotvrzeni $zmenaChticiPotvrzeni) {
        // co se smaže a potvrzení vykreslí editor
        chyba(htmlspecialchars($zmenaChticiPotvrzeni->getMessage()), false);
    } catch (ChybaSablonyHodnoceni $chybaSablony) {
        // zprávy obsahují text ze souboru nebo z editoru, chyba() vypisuje HTML
        chyba(
            ($akce === 'nacist-soubor'
                ? 'Soubor nejde načíst:<br>'
                : 'Kritéria nejde uložit:<br>')
            . implode('<br>', array_map('htmlspecialchars', $chybaSablony->getChyby())),
            false,
        );
    } catch (HodnoceniTurnajeException $hodnoceniTurnajeException) {
        // editor ukáže uložená kritéria (jiný org je mezitím změnil, soubor nešel nahrát)
        $formularKriterii = null;
        chyba(htmlspecialchars($hodnoceniTurnajeException->getMessage()), false);
    }
}

$t = new XTemplate(__DIR__ . '/hodnoceni-turnaje.xtpl');
$t->assign([
    'cssVerze' => (new VerzeSouboru(__DIR__ . '/../../files/design', 'css'))->getVerze('hodnoceni-turnaje'),
    'rok'      => $hodnoceniTurnaje->getRok(),
]);

$sablona = $hodnoceniTurnaje->getSablona();
$t->assign([
    'skala'         => htmlspecialchars(implode(' ', $sablona?->skala ?? [])),
    // dokud nejsou kritéria, org turnaje začíná podle návodu, pak už ho zná
    'otevrenyNavod' => $sablona === null
        ? 'open'
        : '',
]);
if ($jeOrgTurnaje) {
    $t->parse('hodnoceni.navodOrg');
}
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
        // nové hodnocení zadává org za vypravěče kola, hodnocení patří jemu
        $vypraveciKola = $jeOrgTurnaje && $hodnoceni === null
            ? $hodnoceniTurnaje->getVypraveciKola($vybraneKolo['idAkce'])
            : [];
        if ($duvodZakazu === null && $jeOrgTurnaje && $hodnoceni === null && $vypraveciKola === []) {
            $duvodZakazu = 'Kolo nemá žádného vypravěče, hodnocení nemá komu patřit.';
        }
        // cizí hodnocení vypravěč nevidí, jen kdo ho zadal
        $jeCiziHodnoceni = ! $jeOrgTurnaje && $hodnoceni !== null && $hodnoceni['idHodnotitele'] !== $idUzivatele;
        $hodnoty = $odeslaneHodnoty ?? $hodnoceni['hodnoty'] ?? [];
        $t->assign([
            'idAkce'       => $vybraneKolo['idAkce'],
            'idTymu'       => $vybranyTym['id'],
            'urlFormulare' => htmlspecialchars('hodnoceni-turnaje?' . http_build_query([
                'aktivita' => $vybraneKolo['idAkce'],
                'tym'      => $vybranyTym['id'],
            ])),
            'stav'         => htmlspecialchars($hodnoceni === null
                ? 'Skupina v tomhle kole ještě hodnocení nemá.'
                : sprintf(
                    'Hodnotil(a) %s, naposledy %s.',
                    HodnoceniTurnaje::getPopisHodnotitele(
                        $hodnoceni['idHodnotitele'],
                        $hodnoceni['hodnotitel'],
                        $hodnoceni['idZadal'],
                        $hodnoceni['zadal'],
                    ),
                    $formatujCas($hodnoceni['upraveno'], 'j. n. G:i'),
                )),
            'zakazano'     => $duvodZakazu === null
                ? ''
                : 'disabled',
            'textUlozit'   => $hodnoceni === null
                ? 'Uložit hodnocení'
                : 'Uložit změny',
        ]);
        if ($duvodZakazu !== null) {
            $t->assign('duvodZakazu', htmlspecialchars($duvodZakazu));
            $t->parse('hodnoceni.vyber.formular.duvodZakazu');
        }
        if ($vypraveciKola !== []) {
            $vybranyVypravec = (int)post('za_vypravece');
            foreach ($vypraveciKola as $vypravec) {
                $t->assign([
                    'idVypravece'    => $vypravec['id'],
                    'jmenoVypravece' => htmlspecialchars($vypravec['jmeno']),
                    'vybrano'        => $vypravec['id'] === $vybranyVypravec || count($vypraveciKola) === 1
                        ? 'selected'
                        : '',
                ]);
                $t->parse('hodnoceni.vyber.formular.zaVypravece.vypravec');
            }
            $t->assign('zakazano', $duvodZakazu === null
                ? ''
                : 'disabled');
            $t->parse('hodnoceni.vyber.formular.zaVypravece');
        }
        if (! $jeCiziHodnoceni) {
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
        }
        if ($duvodZakazu === null) {
            $t->parse('hodnoceni.vyber.formular.ulozit');
        }
        $t->parse('hodnoceni.vyber.formular');
    }
    // org turnaje má svůj návod nahoře
    if (! $jeOrgTurnaje) {
        $t->parse('hodnoceni.vyber.navodVypravece');
    }
    $t->parse('hodnoceni.vyber');
}

if ($jeOrgTurnaje) {
    $vysledky = $hodnoceniTurnaje->getVysledky();
    if ($vysledky === []) {
        $t->assign('textBezVysledku', $sablona === null
            ? 'Výsledky budou, až zadáš kritéria hodnocení (dole na stránce).'
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
                'hodnotitel'       => htmlspecialchars(HodnoceniTurnaje::getPopisHodnotitele(
                    $hodnoceni['idHodnotitele'],
                    $hodnoceni['hodnotitel'],
                    $hodnoceni['idZadal'],
                    $hodnoceni['zadal'],
                )),
                'tridaRadku'       => $hodnoceni['hrajeKolo']
                    ? ''
                    : 'hodnoceni-neuplna',
                'upraveno'         => $formatujCas($hodnoceni['upraveno'], 'j. n. G:i'),
                'celkem'           => $hodnoceni['celkem'],
                'urlUpravit'       => htmlspecialchars('hodnoceni-turnaje?' . http_build_query([
                    'aktivita' => $hodnoceni['idAkce'] ?? '',
                    'tym'      => $hodnoceni['idTymu'],
                ])),
                'potvrzeniSmazani' => htmlspecialchars(
                    "Smazat hodnocení skupiny „{$hodnoceni['nazevTymu']}“ v {$hodnoceni['kolo']}. kole?",
                ),
            ]);
            // hodnocení kola, které skupina už nehraje, upravit nejde (není aktivita) a do výsledků se nepočítá
            $t->parse($hodnoceni['hrajeKolo']
                ? 'hodnoceni.org.hodnoceniSeznam.radek.upravit'
                : 'hodnoceni.org.hodnoceniSeznam.radek.mimoKolo');
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
    }

    $editor = $formularKriterii ?? ($sablona === null
        ? FormularKriterii::prazdny()
        : FormularKriterii::zeSablony($sablona));
    // bez uložených kritérií je nové všechno, štítek by nic neříkal
    $stitekNove = $sablona === null
        ? ''
        : '<span class="hodnoceni-editor-nove" title="Ještě není uložené">nové</span>';
    foreach ($editor->kategorie as $kategorie) {
        foreach ($kategorie['kriteria'] as $kriterium) {
            $t->assign([
                'klicKategorie'  => htmlspecialchars($kategorie['klic']),
                'klicKriteria'   => htmlspecialchars($kriterium['klic']),
                'idKriteria'     => $kriterium['id'] ?? '',
                'nazevKriteria'  => htmlspecialchars($kriterium['nazev']),
                'stitekKriteria' => $kriterium['id'] === null
                    ? $stitekNove
                    : '',
            ]);
            $t->parse('hodnoceni.org.editor.kategorie.kriterium');
        }
        $t->assign([
            'klicKategorie'   => htmlspecialchars($kategorie['klic']),
            'idKategorie'     => $kategorie['id'] ?? '',
            'nazevKategorie'  => htmlspecialchars($kategorie['nazev']),
            'stitekKategorie' => $kategorie['id'] === null
                ? $stitekNove
                : '',
        ]);
        $t->parse('hodnoceni.org.editor.kategorie');
    }
    if ($nactenoZeSouboru !== null) {
        $t->assign('nazevSouboru', htmlspecialchars($nactenoZeSouboru));
        $t->parse('hodnoceni.org.editor.nacteno');
    }
    if ($zmenaChticiPotvrzeni !== null) {
        foreach ($zmenaChticiPotvrzeni->getPodrobnosti() as $podrobnost) {
            $t->assign('podrobnost', htmlspecialchars($podrobnost));
            $t->parse('hodnoceni.org.editor.potvrzeni.podrobnost');
        }
        $t->assign([
            'klicPotvrzeni' => htmlspecialchars($zmenaChticiPotvrzeni->getKlicPotvrzeni()),
            'textPotvrzeni' => $zmenaChticiPotvrzeni->isSmazaniVsech()
                ? sprintf('Ano, uložit a smazat všech %d hodnocení', $zmenaChticiPotvrzeni->getPocetHodnoceni())
                : 'Ano, uložit a smazat hodnoty odebraných kritérií',
        ]);
        $t->parse('hodnoceni.org.editor.potvrzeni');
    }
    $t->assign([
        'skalaEditoru' => htmlspecialchars($editor->skala),
        'stitekNove'   => $stitekNove,
        // načtené ze souboru nebo odeslané, ale neuložené: odchod ze stránky se zeptá
        'neulozeno'    => $formularKriterii === null
            ? ''
            : 'data-neulozeno',
        'jsVerze'      => (new VerzeSouboru(__DIR__ . '/../../files', 'js'))->getVerze('hodnoceni-turnaje'),
        'textStazeni'  => $sablona === null
            ? 'Stáhnout vzor'
            : 'Stáhnout kritéria',
        'pripony'      => implode(',', array_map(
            static fn (string $pripona): string => '.' . $pripona,
            SouborSablonyHodnoceni::PRIPONY,
        )),
    ]);
    $t->parse('hodnoceni.org.editor');
    $t->parse('hodnoceni.org');
}

$t->parse('hodnoceni');
$t->out('hodnoceni');
