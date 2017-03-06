<?php

$this->q("
  INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES
    (1016, 'Nerušit automaticky objednávky', 'Uživateli se při nezaplacení včas nebudou automaticky rušit objednávky');
");
