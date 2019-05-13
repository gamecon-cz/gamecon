<?php

// smazání hodnoty poznámka u všech uživatelů
$this->q("
UPDATE `uzivatele_hodnoty` SET `poznamka`= '' 
");

