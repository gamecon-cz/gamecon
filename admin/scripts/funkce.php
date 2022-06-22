<?php

require_once __DIR__ . "/konstanty.php";

/**
 * @param string $cislo
 */
function formatujTelCislo($cislo)
{
    $bezmezer = str_replace(" ", "", $cislo);
    if ($bezmezer == "")
        return "";
    $predvolbaKonec = max(strlen($bezmezer) - 9, 0);
    $formatovane = substr($bezmezer, 0, $predvolbaKonec) . " " . substr($bezmezer, $predvolbaKonec, 3) . " " . substr($bezmezer, $predvolbaKonec + 3, 3) . " " . substr($bezmezer, $predvolbaKonec + 6, 3);
    return $formatovane;
}

/**
 * @param \XTemplate $x
 * @param \Uzivatel|null $u
 */
function xtemplateAssignZakladniPromenne($x, $u = null) {
  global $ok, $err;
  $x->assign([
    'ok' => $ok,
    'err' => $err,
    'rok' => ROK,
  ]);

  if ($u) {
    $x->assign([
      'a' => $u->koncovkaDlePohlavi(),
      'ka' => $u->koncovkaDlePohlavi() ? 'ka' : '',
  ]);
  }
}

