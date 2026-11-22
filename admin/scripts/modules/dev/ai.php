<?php

declare(strict_types=1);

use Gamecon\Ai\Exceptions\ChybaApiAi;
use Gamecon\Ai\Exceptions\PripojeniAiNelzeUlozit;
use Gamecon\Ai\PoskytovatelAi;
use Gamecon\Ai\PripojeniAi;
use Gamecon\Aktivita\Korektura\KorekturyTextuRepository;
use Gamecon\Aktivita\Korektura\OdeslanaDavkaAi;
use Gamecon\Cas\DateTimeCz;
use Gamecon\XTemplate\XTemplate;

/**
 * Připojení k API umělé inteligence (Claude, ChatGPT): token a model každého poskytovatele a který z nich admin
 * používá. Bez používaného jdou texty k AI ručně přes soubor (Aktivity → Korektury textů).
 *
 * nazev: Připojení AI
 * ikona: robot
 * pravo: 113
 * submenu_group: 1
 * submenu_order: 5
 */

/** @var Uzivatel $u */
$pripojeniAi = new PripojeniAi();

if (post('ulozit') || post('overit') || post('smazat') || post('zvolit')) {
    $poskytovatel = PoskytovatelAi::tryFrom((string) post('poskytovatel'));
    try {
        if (post('zvolit')) {
            // an empty choice is none: the texts go through a file only
            $zvoleny = PoskytovatelAi::tryFrom((string) post('pouzivat'));
            $pripojeniAi->pouzivej($zvoleny, $u->id());
            oznameni($zvoleny !== null
                ? "Korektury teď posílají texty přes API k {$zvoleny->nazev()}."
                : 'Připojení k AI vypnuto, texty jdou k AI jen ručně přes soubor.');
        }
        if ($poskytovatel === null) {
            chyba('Neznámý poskytovatel AI.');
        }
        if (post('ulozit')) {
            $pripojeniAi->uloz($poskytovatel, (string) post('token'), (string) post('model'), $u->id());
            oznameni("Připojení k {$poskytovatel->nazev()} uloženo. Používat se začne, až ho zvolíš nahoře.");
        }
        if (post('smazat')) {
            $pripojeniAi->smaz($poskytovatel);
            oznameni("Token pro {$poskytovatel->nazev()} smazán.");
        }
        if (post('overit')) {
            // what is in the form, saved or not: a pasted token, a model typed over the stored one
            $zadanyToken = trim((string) post('token'));
            $model = trim((string) post('model'));
            $model = $model !== ''
                ? $model
                : $poskytovatel->vychoziModel();
            $davky = $zadanyToken !== ''
                ? $poskytovatel->davky($zadanyToken, $model)
                : ($pripojeniAi->dejDavky($poskytovatel, $model)
                    ?? throw new PripojeniAiNelzeUlozit("Pro {$poskytovatel->nazev()} tu není uložený token, zadej ho."));
            try {
                $davky->overSpojeni();
            } catch (ChybaApiAi $chybaApiAi) {
                chyba(htmlspecialchars("{$poskytovatel->nazev()}: {$chybaApiAi->getMessage()}", ENT_QUOTES));
            }
            $ulozeno = $zadanyToken === ''
                && $model === $pripojeniAi->dejStavy()[$poskytovatel->value]->modelKPouziti();
            oznameni(htmlspecialchars(
                "{$poskytovatel->nazev()} přijalo token i model {$model}."
                . ($ulozeno
                    ? ' Spojení funguje.'
                    : ' Zadané hodnoty zatím nejsou uložené, ulož je tlačítkem Uložit.'),
                ENT_QUOTES,
            ));
        }
    } catch (PripojeniAiNelzeUlozit $pripojeniAiNelzeUlozit) {
        chyba(htmlspecialchars($pripojeniAiNelzeUlozit->getMessage(), ENT_QUOTES));
    }
}

$t = new XTemplate(__DIR__ . '/ai.xtpl');
$t->assign('totoProstredi', htmlspecialchars($pripojeniAi->dejProstredi(), ENT_QUOTES));
$stavy = $pripojeniAi->dejStavy();

// batches this database sent, rows a copy of another database brought along wait for nothing here
$davkyPodlePoskytovatele = array_count_values(array_map(
    static fn (OdeslanaDavkaAi $davka) => $davka->poskytovatel,
    array_filter(
        (new KorekturyTextuRepository())->dejDavkyAi(),
        static fn (OdeslanaDavkaAi $davka) => $davka->zTetoDatabaze,
    ),
));
foreach ($davkyPodlePoskytovatele as $poskytovatelDavek => $pocetDavek) {
    $t->assign([
        'pocetDavek'        => $pocetDavek,
        'poskytovatelDavek' => PoskytovatelAi::tryFrom((string) $poskytovatelDavek)?->nazev() ?? $poskytovatelDavek,
    ]);
    $t->parse('ai.cekajiciDavky.davky');
}
if ($davkyPodlePoskytovatele !== []) {
    $t->parse('ai.cekajiciDavky');
}

$nicNepouziva = true;
foreach ($stavy as $stav) {
    $pouziva = $stav->pouzivat && $stav->jePouzitelny();
    $nicNepouziva = $nicNepouziva && ! $pouziva;
    $t->assign([
        'poskytovatel' => $stav->poskytovatel->value,
        'nazev'        => $stav->poskytovatel->nazev(),
        'checked'      => $pouziva
            ? 'checked'
            : '',
        'disabled' => $stav->jePouzitelny()
            ? ''
            : 'disabled',
        'model' => htmlspecialchars($stav->modelKPouziti(), ENT_QUOTES),
    ]);
    if (! $stav->jePouzitelny()) {
        $t->parse('ai.volba.bezTokenu');
    }
    $t->parse('ai.volba');
}
$t->assign('nicChecked', $nicNepouziva
    ? 'checked'
    : '');

foreach ($stavy as $stav) {
    $poskytovatel = $stav->poskytovatel;
    $kdo = $stav->zmenil !== null
        ? Uzivatel::zId($stav->zmenil)?->jmenoNick()
        : null;
    $t->assign([
        'poskytovatel' => $poskytovatel->value,
        'nazev'        => $poskytovatel->nazev(),
        'urlTokenu'    => $poskytovatel->urlTokenu(),
        'vychoziModel' => htmlspecialchars($poskytovatel->vychoziModel(), ENT_QUOTES),
        'model'        => htmlspecialchars((string) $stav->model, ENT_QUOTES),
        'konecTokenu'  => htmlspecialchars((string) $stav->konecTokenu, ENT_QUOTES),
        'prostredi'    => htmlspecialchars((string) $stav->prostredi, ENT_QUOTES),
        'zmeneno'      => htmlspecialchars(trim(implode(', ', array_filter([
            $stav->zmenenoKdy !== null
                ? DateTimeCz::createFromInterface($stav->zmenenoKdy)->formatCasStandard()
                : null,
            $kdo,
        ]))), ENT_QUOTES),
    ]);
    if (! $stav->maToken) {
        $t->parse('ai.poskytovatel.bezTokenu');
    } elseif (! $stav->jeZTohotoProstredi) {
        $t->parse('ai.poskytovatel.jineProstredi');
    } elseif ($stav->konecTokenu === null) {
        $t->parse('ai.poskytovatel.necitelny');
    } else {
        $t->parse('ai.poskytovatel.token');
    }
    if ($stav->maToken) {
        $t->parse('ai.poskytovatel.smazat');
    }
    $t->parse('ai.poskytovatel');
}

$t->parse('ai');
$t->out('ai');
