<?php

declare(strict_types=1);

require __DIR__ . '/sdilene-hlavicky.php';

use Gamecon\Report\MladsiNezVekAktivityReport;

/**
 * Letošní přihlášení, kterým v den aktivity ještě nebude její minimální věk (štítek „Věk: 15+“), viz
 * MladsiNezVekAktivityReport.
 *
 * @var Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni
 */
$report = new MladsiNezVekAktivityReport($systemoveNastaveni);

Report::zPoli($report->getHlavicky(), $report->getRadky())->tFormat(get('format'));
