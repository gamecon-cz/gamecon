<?php

class Medailonek extends DbObject {

  protected static $tabulka = 'medailonky';

  function drd() {
    return markdownNoCache($this->r['drd']);
  }

  function oSobe() {
    return markdownNoCache($this->r['o_sobe']);
  }

}
