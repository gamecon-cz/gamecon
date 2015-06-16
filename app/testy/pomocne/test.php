<?php

abstract class Test {

  protected function assert($ok) {
    echo $ok ? '.' : 'F';
  }

  protected function assertNotException($func) {
    $ok = true;
    try {
      $func();
    } catch(Exception $e) {
      $ok = false;
    }
    echo $ok ? '.' : 'F';
  }

}
