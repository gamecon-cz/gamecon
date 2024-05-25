<?php

namespace Gamecon\Api;

class ApiUzivatel
{
  static function apiUzivatel(\Uzivatel|null $u)
  {
    $res = [];

    if ($u) {
      $res["prihlasen"] = true;
      $res["pohlavi"] = $u->pohlavi();
      $res["koncovkaDlePohlavi"] = $u->koncovkaDlePohlavi();

      if ($u->jeOrganizator()) {
        $res["organizator"] = true;
      }
      if ($u->jeBrigadnik()) {
        $res["brigadnik"] = true;
      }

      $res["gcStav"] = "nepřihlášen";

      if ($u->gcPrihlasen()) {
        $res["gcStav"] = "přihlášen";
      }
      if ($u->gcPritomen()) {
        $res["gcStav"] = "přítomen";
      }
      if ($u->gcOdjel()) {
        $res["gcStav"] = "odjel";
      }
    }
    return $res;
  }
}
