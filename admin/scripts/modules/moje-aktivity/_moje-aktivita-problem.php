<?php

use Gamecon\XTemplate\XTemplate;

/**
 * @var \Gamecon\Aktivita\Aktivita|null $aktivita
 * @var Uzivatel $u
 * @var bool $testujeme
 */

$problem = false;

$sablonaKProblemu = new XTemplate(__DIR__ . '/_moje-aktivita-problem.xtpl');

if (!$aktivita) {
    $problem = true;
    $sablonaKProblemu->assign('id', get('id'));
    $sablonaKProblemu->parse('problem.neznama');
} elseif ($aktivita->konec()->format('Y') < ROCNIK) {
    $problem = true;
    $sablonaKProblemu->assign('aktivita', $aktivita);
    $sablonaKProblemu->assign('rok', $aktivita->konec()->format('Y'));
    $sablonaKProblemu->parse('problem.historicka');
} elseif ($aktivita->konec()->pred(new DateTime()) && !$testujeme) {
    $problem = true;
    $sablonaKProblemu->assign('aktivita', $aktivita);
    $sablonaKProblemu->assign('konecPred', $aktivita->konec()->relativni());
    $sablonaKProblemu->parse('problem.probehla');
} elseif (!$aktivita->maOrganizatora($u) && !$testujeme) {
    $problem = true;
    $sablonaKProblemu->assign('aktivita', $aktivita);
    $sablonaKProblemu->parse('problem.cizi');
}

if ($problem) {
    $sablonaKProblemu->assign('urlNaMojeAktivity', getBackUrl());
    $sablonaKProblemu->parse('problem');
    $sablonaKProblemu->out('problem');
}

return $problem;
