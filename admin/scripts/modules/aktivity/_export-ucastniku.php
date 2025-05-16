<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Report\ExportUcastnikuNaAktivitach;

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
/** @var DateTimeInterface $now */
/** @var Uzivatel $u */
/** @var \Gamecon\XTemplate\XTemplate $template */

require_once __DIR__ . '/_filtr-moznosti.php';

$filtrMoznosti = FiltrMoznosti::vytvorZGlobals(FiltrMoznosti::FILTROVAT_PODLE_ROKU);

$filtrMoznosti->zobraz();

[$filtr, $razeni] = $filtrMoznosti->dejFiltr(true);
$aktivity = Aktivita::zFiltru(
    systemoveNastaveni: $systemoveNastaveni,
    filtr: $filtr,
    razeni: $razeni,
);

$activityTypeIdsFromFilter = array_unique(
    array_map(
        static fn(
            Aktivita $aktivita,
        ) => $aktivita->typId(),
        $aktivity,
    ),
);

if (count($activityTypeIdsFromFilter) > 1) {
    $template->parse('export.neniVybranTyp');
} elseif (count($activityTypeIdsFromFilter) === 0) {
    $template->parse('export.zadneAktivity');
} elseif (count($activityTypeIdsFromFilter) === 1) {
    $activityTypeIdFromFilter = reset($activityTypeIdsFromFilter);
    $typAktivity              = TypAktivity::zId($activityTypeIdFromFilter, true);
    assert($typAktivity !== null);

    if (!empty($_POST['export_activity_type_id']) && (int)$_POST['export_activity_type_id'] === (int)$activityTypeIdFromFilter) {
        (new ExportUcastnikuNaAktivitach())->exportuj(
            'Účastníci ' . $typAktivity->nazev(),
            $aktivity,
        );
        exit;
    }

    $template->assign('activityTypeId', $activityTypeIdFromFilter);
    $template->assign(
        'nazevTypu',
        mb_ucfirst($typAktivity->nazev())
        . (($filtr['rok'] ?? $systemoveNastaveni->rocnik()) != $systemoveNastaveni->rocnik()
            ? (' ' . $filtr['rok'])
            : ''),
    );
    $pocetAktivit      = count($aktivity);
    $pocetAktivitSlovo = 'aktivit';
    if ($pocetAktivit === 1) {
        $pocetAktivitSlovo = 'aktivitu';
    } elseif ($pocetAktivit > 1 && $pocetAktivit < 5) {
        $pocetAktivitSlovo = 'aktivity';
    }
    $template->assign('pocetAktivit', $pocetAktivit);
    $template->assign('pocetAktivitSlovo', $pocetAktivitSlovo);

    $template->parse('export.exportovat');
}

$template->parse('export');
$template->out('export');
