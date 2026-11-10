<?php

declare(strict_types=1);

use Gamecon\ArchivPrednasek\DoplneniZProgramu;

/**
 * Po skončení GameConu založí v archivu přednášek proběhlé přednášky ročníku z programu, každý ročník jednou,
 * viz docs/generated/archiv-prednasek.md. Pouští ho hodinový cron (admin/cron.php), ručně ?job=archiv_prednasek_po_gc.
 */

require_once __DIR__ . '/../_cron_zavadec.php';

/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */
global $systemoveNastaveni;

foreach (DoplneniZProgramu::zNastaveni($systemoveNastaveni)->doplnSkoncene() as $zprava) {
    logs($zprava);
}
