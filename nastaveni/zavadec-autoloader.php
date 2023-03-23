<?php

/**
 * Soubor, který zpřístupní definice pro gamecon (třídy, konstanty).
 */

// autoloader Gamecon webu (modelu)
spl_autoload_register(function ($trida) {
    $trida     = strtolower(preg_replace('@[A-Z]@', '-$0', lcfirst($trida)));
    $classFile = __DIR__ . '/../model/' . $trida . '.php';
    if (file_exists($classFile)) {
        include_once $classFile;
    }
});

// autoloader Composeru
require_once __DIR__ . '/../vendor/autoload.php';
