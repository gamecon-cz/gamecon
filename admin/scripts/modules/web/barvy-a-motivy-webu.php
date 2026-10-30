<?php

declare(strict_types=1);

use Gamecon\Admin\PosledniZmenaHtml;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Web\DatumACasZFormulare;
use Gamecon\Web\Motiv\BarvaWebuEnum;
use Gamecon\Web\Motiv\BarvyWebu;
use Gamecon\Web\Motiv\BarvyWebuRepository;
use Gamecon\Web\Motiv\KontrastBarevWebu;
use Gamecon\Web\Motiv\MotivWebuEnum;
use Gamecon\Web\Motiv\NahledMotivuWebu;
use Gamecon\Web\Motiv\NaplanovaneMotivyWebuRepository;
use Gamecon\Web\Motiv\StavBarevWebuEnum;
use Gamecon\Web\StavZobrazeniEnum;
use Gamecon\XTemplate\XTemplate;

/**
 * Barvy moderního vzhledu webu pro ročník (nastavuje grafik) a speciální motivy jako apríl
 *
 * nazev: Barvy a motivy webu
 * ikona: swatchbook
 * pravo: 105
 */

/**
 * @var Uzivatel                                      $u
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */
$urlModulu = 'web/barvy-a-motivy-webu';
$urlStranky = URL_ADMIN . '/' . $urlModulu;
$barvyRepo = new BarvyWebuRepository();
$motivyRepo = new NaplanovaneMotivyWebuRepository();
$ted = $systemoveNastaveni->ted();

// Colours with a mistake are shown again as typed, a redirect would lose them
$chybaBarev = null;
$odeslaneBarvy = null;

$akce = (string) post('akce');

if ($akce === 'ulozit-navrh' || $akce === 'ulozit-a-prohlednout') {
    $odeslaneBarvy = array_map('strval', (array) post('barvy'));
    $neplatne = [];
    foreach (BarvaWebuEnum::cases() as $barva) {
        if (BarvyWebu::normalizujHodnotu($odeslaneBarvy[$barva->value] ?? '') === null) {
            $neplatne[] = $barva->getNazev();
        }
    }
    if ($neplatne !== []) {
        $chybaBarev = 'Tohle nejsou barvy ve tvaru #rrggbb: ' . implode(', ', $neplatne) . '. Návrh se neuložil.';
    } else {
        $barvyRepo->ulozNavrh(BarvyWebu::zPole($odeslaneBarvy), $u->id());
        if ($akce === 'ulozit-a-prohlednout') {
            // the button opens a new tab, the admin stays in the old one, which loads again once the designer comes back (see the .xtpl)
            back(NahledMotivuWebu::getUrlNahledu(URL_WEBU, NahledMotivuWebu::NAVRH_BAREV));
        }
        oznameniPresmeruj('Návrh barev je uložený. Na webu ho zatím vidíš jen ty v náhledu.', $urlStranky);
    }
} elseif ($akce === 'vychozi-do-navrhu') {
    $barvyRepo->ulozNavrh(BarvyWebu::vychozi(), $u->id());
    oznameniPresmeruj('V návrhu jsou výchozí barvy. Zveřejni ho, ať je vidí i návštěvníci.', $urlStranky);
} elseif ($akce === 'zverejnit') {
    if ($barvyRepo->zverejniNavrh($u->id())) {
        oznameniPresmeruj('Barvy jsou zveřejněné, návštěvníci je vidí.', $urlStranky);
    }
    oznameniPresmeruj('Není co zveřejnit, žádný návrh není.', $urlStranky, Chyba::CHYBA);
} elseif ($akce === 'zahodit-navrh') {
    $barvyRepo->zahodNavrh();
    oznameniPresmeruj('Návrh barev je zahozený.', $urlStranky);
} elseif ($akce === 'naplanovat-motiv') {
    $motiv = MotivWebuEnum::tryFrom((string) post('motiv'));
    try {
        $zobrazitOd = DatumACasZFormulare::zVstupu(post('zobrazit_od'));
        $zobrazitDo = DatumACasZFormulare::zVstupu(post('zobrazit_do'));
    } catch (InvalidArgumentException $vyjimka) {
        chyba($vyjimka->getMessage());
    }
    if ($motiv === null || $zobrazitOd === null || $zobrazitDo === null) {
        chyba('Vyber motiv a vyplň, odkdy dokdy platí.');
    }
    if ($zobrazitDo <= $zobrazitOd || $zobrazitDo <= $ted) {
        chyba('Konec motivu musí být po jeho začátku a v budoucnosti.');
    }
    $motivyRepo->naplanuj($motiv, $zobrazitOd, $zobrazitDo, $u->id());
    oznameniPresmeruj("Motiv {$motiv->getNazev()} je naplánovaný.", $urlStranky);
} elseif ($akce === 'ukoncit-motiv') {
    $motivyRepo->ukonci((int) post('id'), $ted, $u->id());
    oznameniPresmeruj('Motiv zmizel z webu.', $urlStranky);
} elseif ($akce === 'smazat-motiv') {
    $motivyRepo->smaz((int) post('id'));
    oznameniPresmeruj('Naplánovaný motiv je smazaný.', $urlStranky);
}

$t = new XTemplate(__DIR__ . '/barvy-a-motivy-webu.xtpl');
$t->assign('urlModulu', $urlModulu);

$posledniZmena = static fn (?DateTimeInterface $kdy, ?int $kdo): string => $kdy !== null
    ? PosledniZmenaHtml::bunka($kdy, Uzivatel::zId($kdo))
    : '';

// --- Colours of the year

$zverejnene = $barvyRepo->dej(StavBarevWebuEnum::ZVEREJNENO);
$navrh = $barvyRepo->dej(StavBarevWebuEnum::NAVRH);
$zverejneneBarvy = $zverejnene?->barvy ?? BarvyWebu::vychozi();

$t->assign('stavZverejnenych', $zverejneneBarvy->jsouVychozi()
    ? 'výchozí barvy'
    : 'vlastní barvy');
if ($zverejnene !== null) {
    $t->assign('zmenaZverejnenych', $posledniZmena($zverejnene->zmenenoKdy, $zverejnene->zmenil));
    $t->parse('barvyAMotivy.zmenaZverejnenych');
}
if ($navrh !== null) {
    $t->assign([
        'stavNavrhu' => $navrh->barvy->jeShodnaS($zverejneneBarvy)
            ? 'stejný jako zveřejněné barvy'
            : 'liší se od zveřejněných barev',
        'zmenaNavrhu'      => $posledniZmena($navrh->zmenenoKdy, $navrh->zmenil),
        'urlNahleduNavrhu' => htmlspecialchars(NahledMotivuWebu::getUrlNahledu(URL_WEBU, NahledMotivuWebu::NAVRH_BAREV)),
    ]);
    $t->parse('barvyAMotivy.navrh');
    $t->parse('barvyAMotivy.akceNavrhu');
} else {
    $t->parse('barvyAMotivy.bezNavrhu');
}

// The form holds what was sent with a mistake, else the draft, else what visitors see
$barvyFormulare = $navrh?->barvy ?? $zverejneneBarvy;
foreach (BarvaWebuEnum::cases() as $barva) {
    $hodnota = $odeslaneBarvy[$barva->value] ?? $barvyFormulare->getHodnota($barva);
    $t->assign([
        'barvaKlic'    => $barva->value,
        'barvaNazev'   => $barva->getNazev(),
        'barvaPouziti' => $barva->getPouziti(),
        'barvaHodnota' => htmlspecialchars($hodnota),
        // the colour picker takes only #rrggbb, a mistyped value leaves it on the default
        'barvaPicker'     => BarvyWebu::normalizujHodnotu($hodnota) ?? $barva->getVychoziHodnota(),
        'barvaVychozi'    => $barva->getVychoziHodnota(),
        'barvaZverejnena' => $zverejneneBarvy->getHodnota($barva),
    ]);
    if ($navrh !== null && $navrh->barvy->getHodnota($barva) !== $zverejneneBarvy->getHodnota($barva)) {
        $t->parse('barvyAMotivy.barva.zmenaOprotiZverejnene');
    }
    $t->parse('barvyAMotivy.barva');
}
if ($chybaBarev !== null) {
    $t->assign('chybaBarev', htmlspecialchars($chybaBarev));
    $t->parse('barvyAMotivy.chybaBarev');
}
$varovaniKontrastu = KontrastBarevWebu::varovani($barvyFormulare);
foreach ($varovaniKontrastu as $varovani) {
    $t->assign('varovani', htmlspecialchars($varovani));
    $t->parse('barvyAMotivy.kontrast.varovani');
}
if ($varovaniKontrastu !== []) {
    $t->parse('barvyAMotivy.kontrast');
}

// --- Special themes

foreach (MotivWebuEnum::cases() as $motiv) {
    $t->assign([
        'motivHodnota'     => $motiv->value,
        'motivNazev'       => $motiv->getNazev(),
        'motivPopis'       => $motiv->getPopis(),
        'urlNahleduMotivu' => htmlspecialchars(NahledMotivuWebu::getUrlNahledu(URL_WEBU, $motiv->value)),
    ]);
    $t->parse('barvyAMotivy.motiv');
    $t->parse('barvyAMotivy.volbaMotivu');
}

$formatujCas = static fn (DateTimeInterface $cas): string => DateTimeCz::createFromInterface($cas)->formatCasNaMinutyStandard();
$naplanovane = $motivyRepo->vsechny();
foreach ($naplanovane as $naplanovany) {
    $stav = $naplanovany->getStav($ted);
    $t->assign([
        'naplanovanyId'        => $naplanovany->id,
        'naplanovanyStav'      => $stav->value,
        'naplanovanyStavNazev' => $stav->getNazev(),
        'naplanovanyNazev'     => $naplanovany->motiv->getNazev(),
        'naplanovanyOd'        => $formatujCas($naplanovany->zobrazitOd),
        'naplanovanyDo'        => $formatujCas($naplanovany->zobrazitDo),
        'naplanovanyZmena'     => $posledniZmena($naplanovany->zmenenoKdy, $naplanovany->zmenil),
    ]);
    if ($stav === StavZobrazeniEnum::ZOBRAZUJE_SE) {
        $t->parse('barvyAMotivy.naplanovane.naplanovany.ukoncit');
    }
    $t->parse('barvyAMotivy.naplanovane.naplanovany');
}
if ($naplanovane !== []) {
    $t->parse('barvyAMotivy.naplanovane');
}

$t->parse('barvyAMotivy');
$t->out('barvyAMotivy');
