<?php
[$filtr, $razeni] = include __DIR__ . '/_filtr-moznosti.php';

return Aktivita::zFiltru($filtr, $razeni);
