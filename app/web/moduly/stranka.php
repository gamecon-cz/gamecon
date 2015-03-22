<?php

echo $stranka->html();

$this->info()
  ->obrazek($stranka->obrazek())
  ->nazev($stranka->nadpis());
