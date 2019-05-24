<?php

/**
 * Vhackovaný code snippet na zobrazení vybírátka času
 * @param DateTimeCz $zacatekDt do tohoto se přiřadí vybraný čas začátku aktivit
 * @param bool $pre jestli se má vybírat hodina od vybraného času, nebo hodina před vybraným časem
 * @return string html kód vybírátka
 */
function _casy(&$zacatekDt, $pre = false) {

  $t = new XTemplate(__DIR__ . '/_casy.xtpl');

  $ted = new DateTimeCz();
  //$ted = new DateTimeCz('2016-07-21 14:10'); // debug
  $t->assign('datum', $ted->format('j.n.'));
  $t->assign('casAktualni', $ted->format('H:i:s'));
  $gcZacatek = new DateTimeCz(DEN_PRVNI_DATE);
  $delta = $ted->getTimestamp() - $gcZacatek->getTimestamp(); //rozdíl sekund od začátku GC

  $vybrany = null;
  if (get('cas')) {
    // čas zvolený manuálně
    try {
      $vybrany = new DateTimeCz(get('cas'));
    } catch (Throwable $throwable) {
      $t->assign('chybnyCas', get('cas'));
      $t->parse('casy.chybaCasu');
    }
  } elseif (0 < $delta && $delta < 3600 * 24 * 4) {
    // nejspíš GC právě probíhá, čas předvolit automaticky
    $vybrany = clone $ted;
    $vybrany->zaokrouhlitNahoru('H');
    if ($pre) $vybrany->sub(new DateInterval('PT1H'));
    $t->parse('casy.casAuto');
  }

  for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
    for ($hodina = PROGRAM_ZACATEK; $hodina < PROGRAM_KONEC; $hodina++) {
      $t->assign('cas', $den->format('l') . ' ' . $hodina . ':00');
      $t->assign('val', $den->format('Y-m-d') . ' ' . $hodina . ':00');
      $t->assign('sel', $vybrany && $vybrany->stejnyDen($den) && $vybrany->format('H') == $hodina ? 'selected' : '');
      $t->parse('casy.cas');
    }
  }

  $zacatekDt = $vybrany ? clone $vybrany : null;

  $t->parse('casy');
  return $t->text('casy');

}
