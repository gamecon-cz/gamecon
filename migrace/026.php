<?php
// Přidá možnost přihlášení jako náhradník
$this->q("INSERT INTO akce_prihlaseni_stavy (id_stavu_prihlaseni, nazev, platba_procent) VALUES (5, 'náhradník (watchlist)', 0)");