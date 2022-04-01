<?php
/**
 * @var Aktivita|null $aktivita
 * @var Uzivatel $u
 * @var bool $testovani
 */

$problem = false;

$sablonaKProblemu = new XTemplate('_moje-aktivita-problem.xtpl');

if (!$aktivita) {
    $problem = true;
    $sablonaKProblemu->assign('id', get('id'));
    $sablonaKProblemu->parse('problem.neznama');
} elseif ($aktivita->konec()->format('Y') < ROK) {
    $problem = true;
    $sablonaKProblemu->assign('aktivita', $aktivita);
    $sablonaKProblemu->assign('rok', $aktivita->konec()->format('Y'));
    $sablonaKProblemu->parse('problem.historicka');
} elseif ($aktivita->konec()->pred(new DateTime()) && !$testovani) {
    $problem = true;
    $sablonaKProblemu->assign('aktivita', $aktivita);
    $sablonaKProblemu->assign('konecPred', $aktivita->konec()->relativni());
    $sablonaKProblemu->parse('problem.probehla');
} elseif (!in_array($u->id(), $aktivita->getOrganizatoriIds(), false) && !$testovani) {
    $problem = true;
    $sablonaKProblemu->assign('aktivita', $aktivita);
    $sablonaKProblemu->parse('problem.cizi');
}

if ($problem) {
    $sablonaKProblemu->assign('urlNaMojeAktivity', $_SERVER['HTTP_REFERER']);
    $sablonaKProblemu->parse('problem');
    $sablonaKProblemu->out('problem');
}

return $problem;
