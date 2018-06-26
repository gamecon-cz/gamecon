<?php

if(!$u) {
  header('Location: program');
  die();
}

$this->param('osobni', true);

require __DIR__ . '/program.php';
