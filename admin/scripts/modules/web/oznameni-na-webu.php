<?php

declare(strict_types=1);

use Gamecon\Admin\PosledniZmenaHtml;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Web\DatumACasZFormulare;
use Gamecon\Web\OznameniNaWebu\OznameniNaWebu;
use Gamecon\Web\OznameniNaWebu\OznameniNaWebuRepository;
use Gamecon\Web\OznameniNaWebu\TypOznameniNaWebuEnum;
use Gamecon\Web\StavZobrazeniEnum;
use Gamecon\XTemplate\XTemplate;

/**
 * Lišta s oznámením pod menu na všech stránkách webu, hlavně když se během festivalu něco změní
 *
 * nazev: Oznámení na webu
 * ikona: bullhorn
 * pravo: 105
 */

/**
 * @var Uzivatel                                      $u
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */
$urlModulu = 'web/oznameni-na-webu';
$repository = new OznameniNaWebuRepository();
$ted = $systemoveNastaveni->ted();

// A form with a mistake is shown again with what was typed, a redirect would lose the text
$chybaFormulare = null;
$odeslanyFormular = null;

$akce = (string) post('akce');
$id = post('id') !== null && post('id') !== ''
    ? (int) post('id')
    : null;

if ($akce === 'ulozit') {
    $odeslanyFormular = [
        'id'          => $id,
        'text'        => (string) post('text'),
        'typ'         => TypOznameniNaWebuEnum::tryFrom((string) post('typ')),
        'zobrazit_od' => (string) post('zobrazit_od'),
        'zobrazit_do' => (string) post('zobrazit_do'),
    ];
    try {
        $zobrazitOd = DatumACasZFormulare::zVstupu($odeslanyFormular['zobrazit_od']);
        $zobrazitDo = DatumACasZFormulare::zVstupu($odeslanyFormular['zobrazit_do']);
        if ($odeslanyFormular['typ'] === null) {
            throw new InvalidArgumentException('Vyber typ oznámení.');
        }
        if ($zobrazitDo !== null && $zobrazitDo <= ($zobrazitOd ?? $ted)) {
            throw new InvalidArgumentException($zobrazitOd !== null ? 'Konec musí být až po začátku.' : 'Konec musí být v budoucnosti, jinak by se oznámení vůbec neukázalo.');
        }
        $repository->uloz($id, $odeslanyFormular['text'], $odeslanyFormular['typ'], $zobrazitOd, $zobrazitDo, $u->id());
        oznameniPresmeruj('Oznámení je uložené.', URL_ADMIN . '/' . $urlModulu);
    } catch (InvalidArgumentException $vyjimka) {
        $chybaFormulare = $vyjimka->getMessage();
    }
} elseif ($akce === 'ukoncit' && $id !== null) {
    $repository->ukonci($id, $ted, $u->id());
    oznameniPresmeruj('Oznámení zmizelo z webu.', URL_ADMIN . '/' . $urlModulu);
} elseif ($akce === 'smazat' && $id !== null) {
    $repository->smaz($id);
    oznameniPresmeruj('Oznámení je smazané.', URL_ADMIN . '/' . $urlModulu);
}

$upravovane = null;
if ($odeslanyFormular === null && get('id') !== null) {
    $upravovane = $repository->dej((int) get('id'));
    if ($upravovane === null) {
        oznameniPresmeruj('Takové oznámení neexistuje.', URL_ADMIN . '/' . $urlModulu, Chyba::CHYBA);
    }
}

$formatujCas = static fn (?DateTimeInterface $cas): string => $cas !== null
    ? DateTimeCz::createFromInterface($cas)->formatCasNaMinutyStandard()
    : '';

$t = new XTemplate(__DIR__ . '/oznameni-na-webu.xtpl');
$t->assign('urlModulu', $urlModulu);

// Form: what was sent with a mistake, the announcement being edited, or a new one
$formular = $odeslanyFormular ?? [
    'id'          => $upravovane?->id,
    'text'        => $upravovane?->text ?? '',
    'typ'         => $upravovane?->typ ?? TypOznameniNaWebuEnum::INFO,
    'zobrazit_od' => DatumACasZFormulare::doVstupu($upravovane?->zobrazitOd),
    'zobrazit_do' => DatumACasZFormulare::doVstupu($upravovane?->zobrazitDo),
];
$t->assign([
    'nadpisFormulare' => $formular['id'] !== null
        ? 'Upravit oznámení'
        : 'Nové oznámení',
    'id'         => $formular['id'] ?? '',
    'text'       => htmlspecialchars($formular['text']),
    'zobrazitOd' => htmlspecialchars($formular['zobrazit_od']),
    'zobrazitDo' => htmlspecialchars($formular['zobrazit_do']),
]);
foreach (TypOznameniNaWebuEnum::cases() as $typ) {
    $t->assign([
        'typHodnota' => $typ->value,
        'typNazev'   => $typ->getNazev(),
        'typPopis'   => $typ->getPopis(),
        'zaskrtnuto' => $typ === $formular['typ']
            ? 'checked'
            : '',
    ]);
    $t->parse('oznameniNaWebu.typ');
}
if ($chybaFormulare !== null) {
    $t->assign('chybaFormulare', htmlspecialchars($chybaFormulare));
    $t->parse('oznameniNaWebu.chybaFormulare');
}
if ($formular['id'] !== null) {
    $t->parse('oznameniNaWebu.zrusitUpravu');
}

// The list: showing now first, then planned, then the ones over, the newest first within each
$poradiStavu = [
    StavZobrazeniEnum::ZOBRAZUJE_SE->value => 1,
    StavZobrazeniEnum::NAPLANOVANO->value  => 2,
    StavZobrazeniEnum::SKONCILO->value     => 3,
];
$vsechna = $repository->vsechna();
usort(
    $vsechna,
    static fn (OznameniNaWebu $a, OznameniNaWebu $b): int => $poradiStavu[$a->getStav($ted)->value] <=> $poradiStavu[$b->getStav($ted)->value],
);
foreach ($vsechna as $oznameni) {
    $stav = $oznameni->getStav($ted);
    $t->assign([
        'oznameniId' => $oznameni->id,
        'stav'       => $stav->value,
        'stavNazev'  => $stav->getNazev(),
        'typ'        => $oznameni->typ->value,
        'typNazev'   => $oznameni->typ->getNazev(),
        'textHtml'   => markdown($oznameni->text),
        'od'         => $oznameni->zobrazitOd !== null
            ? $formatujCas($oznameni->zobrazitOd)
            : 'hned',
        'do' => $oznameni->zobrazitDo !== null
            ? $formatujCas($oznameni->zobrazitDo)
            : 'dokud ho neukončíš',
        'zmeneno' => $oznameni->zmenenoKdy !== null
            ? PosledniZmenaHtml::bunka($oznameni->zmenenoKdy, Uzivatel::zId($oznameni->zmenil))
            : '',
    ]);
    if ($stav === StavZobrazeniEnum::ZOBRAZUJE_SE) {
        $t->parse('oznameniNaWebu.seznam.radek.ukoncit');
    }
    $t->parse('oznameniNaWebu.seznam.radek');
}
if ($vsechna !== []) {
    $t->parse('oznameniNaWebu.seznam');
} else {
    $t->parse('oznameniNaWebu.zadne');
}

$t->parse('oznameniNaWebu');
$t->out('oznameniNaWebu');
