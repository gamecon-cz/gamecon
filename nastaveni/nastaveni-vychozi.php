<?php

/**
 * Výchozí hodnoty konstant.
 *
 * Použijí se, pokud je nastavení konkrétního prostředí nenastaví jinak.
 * Použité hodnoty by měly být bezpečné pro produkci.
 */

@define('WWW',   __DIR__ . '/../web');
@define('ADMIN', __DIR__ . '/../admin');
@define('SPEC',  __DIR__ . '/../cache/private');
@define('CACHE', __DIR__ . '/../cache/public');

@define('AUTOMATICKE_MIGRACE', false);
@define('ZOBRAZIT_STACKTRACE_VYJIMKY', false);
@define('PROFILOVACI_LISTA', false);
@define('CACHE_SLOZKY_PRAVA', 0700);
