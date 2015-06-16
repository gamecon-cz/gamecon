#!/usr/bin/php
<?php

// použití:
// testy.php - otestuje všechno
// testy.php aktivita - otestuje aktivita-test.php

// TODO nějaký dump testovacích dat který bude v gitu (?)

// automatické izolace testů:
// db begin a rollback (co s vnořováním transakcí?)
// vyčištění POST
// PROBLEM: např. týmový form ale typicky bude využívat vnitřně transakci. Co s tím?
// FIX: mělo by jít pomocí savepoints

require __DIR__.'/pomocne/zavadec.php';

$soubory = glob(__DIR__.'/*-test.php');

foreach($soubory as $s) {
  $c =
    str_replace(' ', '',
      ucwords(
        str_replace('-', ' ',
          preg_filter('@^.*/(.*)\.php$@', '$1', $s)
        )
      )
    );
  include $s;
  $test = new $c();
  $r = new ReflectionClass($test);
  foreach($r->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
    $m = $m->getName();
    if(!preg_match('@^test.+$@', $m)) continue;
    dbBegin();
    $test->$m();
    dbRollback();
  }
}

echo "\n";
