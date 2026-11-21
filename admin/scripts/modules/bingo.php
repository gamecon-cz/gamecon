<?php

declare(strict_types=1);

use Gamecon\Bingo\Bingo;
use Gamecon\Bingo\BingoException;
use Gamecon\Bingo\StavSouteze;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Web\VerzeSouboru;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Clock\NativeClock;

/**
 * Bingo z jednání rady: společný pool frází, náhodná karta 5×5 na každé jednání a odškrtávání toho, co zaznělo,
 * všem naráz. Pravidla jsou v Gamecon\Bingo\Bingo, stránka se každých pár sekund ptá na stav (admin/files/bingo.js).
 *
 * nazev: Bingo
 * ikona: table-cells
 * pravo: 116
 */

/**
 * @var Uzivatel $u
 * @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */

// skutečné hodiny, ne čas začátku požadavku: změna, která čeká na zámek jednání, rozhoduje podle času, kdy ho dostane
$bingo = new Bingo(new NativeClock());
$idJednani = (int)(post('jednani') ?? get('jednani') ?? 0);
$jednani = $idJednani > 0
    ? $bingo->getJednani($idJednani)
    : $bingo->getAktualniJednani();

/**
 * Stav pro JS. Pole indexovaná id nebo pozicí jsou objekty, prázdné by json_encode udělal polem. Kartu stránka
 * vykreslila sama, JS z ní potřebuje jen id: když se změní (nová karta z jiného zařízení), načte stránku znovu.
 *
 * @param array<string, mixed> $stav z Bingo::getStav()
 * @return array<string, mixed>
 */
$stavProJs = static fn (array $stav): array => array_diff_key([
    'zaznele'  => (object)$stav['zaznele'],
    'zrusene'  => (object)$stav['zrusene'],
    'vitezove' => array_map(
        static fn (array $vitez): array => ['pole' => (object)$vitez['pole']] + $vitez,
        $stav['vitezove'],
    ),
] + $stav, ['karta' => true]);

$odpovezJson = static function (array $data, int $kod = 200): never {
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
};

if (get('stav') !== null) {
    if ($jednani === null) {
        $odpovezJson(['chyba' => 'Jednání neexistuje.'], 404);
    }
    $odpovezJson($stavProJs($bingo->getStav($jednani, $u->id())));
}

$akce = post('akce');
if ($akce !== null) {
    // odškrtnutí posílá bingo.js a chce zpátky stav, bez JS se odesílá formulář
    $jeFetch = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    try {
        if ($jednani === null && in_array($akce, ['odskrtnout', 'vygenerovat-kartu', 'spustit', 'smazat-jednani'], true)) {
            throw new BingoException('Jednání neexistuje.');
        }
        if ($akce === 'odskrtnout') {
            if (preg_match('~^(?<idFraze>\d+)-(?<zaznelo>[01])$~D', (string)post('zmena'), $zmena) !== 1) {
                throw new BingoException('Nevím, co odškrtnout.');
            }
            $bingo->odskrtni($jednani, (int)$zmena['idFraze'], $u->id(), $zmena['zaznelo'] === '1');
            if ($jeFetch) {
                $odpovezJson($stavProJs($bingo->getStav($jednani, $u->id())));
            }
            back();
        }
        // oznameni() i oznameniPresmeruj() přesměrují a skončí
        if ($akce === 'smazat-jednani') {
            $bingo->smazJednani($jednani);
            oznameniPresmeruj('Termín jednání je smazaný.', URL_ADMIN . '/bingo');
        }
        if ($akce === 'spustit') {
            $bingo->spust($jednani);
            oznameni('Soutěž běží. Hodně štěstí!');
        }
        if ($akce === 'vygenerovat-kartu') {
            $bingo->vygenerujKartu($jednani, $u->id());
            oznameni('Karta je vygenerovaná a uložená.');
        }
        if ($akce === 'pridat-frazi') {
            $bingo->pridejFrazi((string)post('text'), $u->id());
            oznameni('Fráze je v poolu.');
        }
        if ($akce === 'smazat-frazi') {
            $bingo->smazFrazi((int)post('id_fraze'));
            oznameni('Fráze je z poolu pryč, na kartách, které ji už mají, zůstane.');
        }
        if ($akce === 'pridat-jednani') {
            $bingo->pridejJednani((string)post('datum'));
            oznameni('Termín jednání je přidaný.');
        }
        throw new BingoException('Neznámá akce.');
    } catch (BingoException $bingoException) {
        if ($jeFetch) {
            $odpovezJson(
                ['chyba' => $bingoException->getMessage()]
                + ($jednani !== null
                    ? $stavProJs($bingo->getStav($jednani, $u->id()))
                    : []),
                422,
            );
        }
        // zpráva je text, i s tím, co kdo napsal do fráze; chyba() ji vypíše jako HTML
        chyba(htmlspecialchars($bingoException->getMessage()));
    }
}

$t = new XTemplate(__DIR__ . '/bingo.xtpl');
$t->assign([
    'cssVerze' => (new VerzeSouboru(__DIR__ . '/../../files/design', 'css'))->getVerze('bingo'),
    'jsVerze'  => (new VerzeSouboru(__DIR__ . '/../../files', 'js'))->getVerze('bingo'),
]);

$formatujDatum = static fn (DateTimeInterface $datum): string => DateTimeCz::createFromInterface($datum)
    ->format('l j. n. Y');
$skryt = static fn (bool $skryt): string => $skryt
    ? 'hidden'
    : '';
/**
 * Titulek pole, stejný jako popisZazneni() v bingo.js.
 *
 * @param array{kdo: string|null, kdy: string, zrusil?: string|null, zruseno?: string}|null $zazneni
 */
$popisZazneni = static function (?array $zazneni): string {
    if ($zazneni === null) {
        return '';
    }
    $popis = 'Zaznělo v ' . $zazneni['kdy'] . ($zazneni['kdo'] !== null
            ? ' · ' . $zazneni['kdo']
            : '');
    if (isset($zazneni['zruseno'])) {
        $popis .= '. Po bingu zrušeno v ' . $zazneni['zruseno'] . ($zazneni['zrusil'] !== null
                ? ' · ' . $zazneni['zrusil']
                : '') . ', vrátit jde i s původním časem.';
    }

    return $popis;
};

$aktualniJednani = $bingo->getAktualniJednani();
$fraze = $bingo->getFraze();

if ($jednani === null) {
    $t->parse($idJednani > 0
        ? 'bingo.neexistujiciJednani'
        : 'bingo.bezJednani');
} else {
    $stav = $bingo->getStav($jednani, $u->id());
    $stavSouteze = StavSouteze::from($stav['stav']);
    $karta = $stav['karta'];
    $t->assign([
        'idJednani'   => $jednani->id,
        'datum'       => $formatujDatum($jednani->datum),
        'popis'       => htmlspecialchars($stav['popis']),
        'idUzivatele' => $u->id(),
        'idKarty'     => $stav['idKarty'] ?? '',
        'urlStavu'    => 'bingo?stav=1&jednani=' . $jednani->id,
        'urlAkci'     => 'bingo?jednani=' . $jednani->id,
        'volnePole'   => Bingo::VOLNE_POLE,
        'rady'        => htmlspecialchars(json_encode(Bingo::getRady(), JSON_THROW_ON_ERROR)),
        'stav'        => htmlspecialchars(json_encode($stavProJs($stav), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
    ]);

    if ($aktualniJednani?->id !== $jednani->id) {
        $t->parse('bingo.jednani.odkazNaAktualni');
    }

    if ($stavSouteze === StavSouteze::PRIPRAVA) {
        $t->assign('skrytSpusteni', $skryt(! $stav['lzeSpustit']));
        $t->parse('bingo.jednani.spusteni');
    }

    if ($karta !== null) {
        for ($pozice = 0; $pozice < Bingo::VELIKOST_KARTY * Bingo::VELIKOST_KARTY; $pozice++) {
            $t->assign('pozice', $pozice);
            if ($pozice === Bingo::VOLNE_POLE) {
                $t->parse('bingo.jednani.karta.pole.volne');
            } else {
                // stejně jako nastavTlacitko() v bingo.js, tohle je první vykreslení a stránka bez JS
                $idFraze = $karta[$pozice]['id'];
                $zazneni = $stav['zaznele'][$idFraze] ?? null;
                $zruseni = $stav['zrusene'][$idFraze] ?? null;
                $t->assign([
                    'idFraze'   => $idFraze,
                    'text'      => htmlspecialchars($karta[$pozice]['text']),
                    'novyStav'  => $zazneni === null
                        ? '1'
                        : '0',
                    'tridaPole' => match (true) {
                        $zazneni !== null => ' bingo-pole--zaznelo',
                        $zruseni !== null => ' bingo-pole--zruseno',
                        default           => '',
                    },
                    'titulek'   => htmlspecialchars($popisZazneni($zazneni ?? $zruseni)),
                    'disabled'  => match (true) {
                        $zazneni !== null => in_array($idFraze, $stav['lzeZrusit'], true),
                        $zruseni !== null => $stav['lzeVratit'],
                        default           => $stav['lzeOdskrtnout'],
                    }
                        ? ''
                        : 'disabled',
                ]);
                $t->parse('bingo.jednani.karta.pole.fraze');
            }
            $t->parse('bingo.jednani.karta.pole');
        }
        $t->parse('bingo.jednani.karta');

        // fráze z karet ostatních, za běhu soutěže; vykreslí je bingo.js podle stavu
        $t->assign('skrytOstatni', $skryt(true));
        $t->parse('bingo.jednani.ostatni');
    } elseif ($bingo->isProbehle($jednani)) {
        $t->assign('a', $u->koncovkaDlePohlavi());
        $t->parse('bingo.jednani.bezKarty');
    }

    if ($stavSouteze === StavSouteze::PRIPRAVA || $stavSouteze === StavSouteze::BEZI) {
        if (count($fraze) < Bingo::FRAZI_NA_KARTE && $karta === null) {
            $t->assign(['potreba' => Bingo::FRAZI_NA_KARTE, 'pocet' => count($fraze)]);
            $t->parse('bingo.jednani.maloFrazi');
        } else {
            $t->assign([
                'skrytGenerovani'     => $skryt(! $stav['lzeGenerovat']),
                'textGenerovani'      => match (true) {
                    $karta !== null                         => 'Vygenerovat jinou kartu',
                    $stavSouteze === StavSouteze::PRIPRAVA => 'Vygenerovat kartu',
                    default                                 => 'Zapojit se: vygenerovat kartu',
                },
                'potvrzeniGenerovani' => htmlspecialchars(match (true) {
                    $karta !== null                         => 'Stávající kartu nahradí nová. Pokračovat?',
                    $stavSouteze === StavSouteze::PRIPRAVA => '',
                    default                                 => 'Soutěž už běží: kartu dostaneš jen jednu a vyměnit ji nepůjde. Pokračovat?',
                }),
            ]);
            $t->parse('bingo.jednani.generovani');
        }
    }
    $t->parse('bingo.jednani');
}

foreach ($fraze as $jednaFraze) {
    $t->assign([
        'idFraze' => $jednaFraze['id'],
        'text'    => htmlspecialchars($jednaFraze['text']),
        'autor'   => htmlspecialchars($jednaFraze['autor'] ?? ''),
    ]);
    $t->parse('bingo.fraze');
}
$t->assign([
    'pocetFrazi'    => count($fraze),
    'maxDelkaFraze' => Bingo::MAX_DELKA_FRAZE,
    'dnes'          => $systemoveNastaveni->ted()->format('Y-m-d'),
]);

foreach ($bingo->getPrehledJednani() as $prehled) {
    $vitezove = implode(', ', array_map(
        static fn (array $vitez): string => $vitez['jmeno'] . ' (' . $vitez['kdy'] . ')',
        $prehled['vitezove'],
    ));
    $t->assign([
        'idTerminu'    => $prehled['jednani']->id,
        'datumTerminu' => $formatujDatum($prehled['jednani']->datum),
        'karet'        => $prehled['karet'],
        'vysledek'     => htmlspecialchars(match ($prehled['stav']) {
            StavSouteze::PRIPRAVA   => 'Začne v ' . $bingo->getZacatek($prehled['jednani'])->format('G:i'),
            StavSouteze::BEZI       => 'Probíhá',
            StavSouteze::VYHRANO    => '🏆 ' . $vitezove,
            StavSouteze::BEZ_VITEZE => 'Bez vítěze',
        }),
        'tridaTerminu' => match (true) {
            $prehled['jednani']->id === $jednani?->id => 'bingo-termin--zobrazeny',
            $prehled['probehle']                      => 'bingo-termin--probehly',
            default                                   => '',
        },
    ]);
    if ($prehled['odskrtnuto'] === 0) {
        $t->parse('bingo.termin.smazat');
    }
    $t->parse('bingo.termin');
}

$t->parse('bingo');
$t->out('bingo');
