<?php

/**
 * Spustí testy.
 *
 * Pokud není k dispozici phpunit, stáhne aktuální phar verzi do cache a pustí
 * odtamtud.
 */

require_once __DIR__ . '/_pomocne.php';

$cache = __DIR__ . '/../cache/private';
if(!is_writable($cache)) {
  @mkdir($cache, 0777);
  @chmod($cache, 0777);
  if(!is_writable($cache))
    throw new Exception('Nelze zapisovat do cache/private. Zkontrolujte, že existuje a je zapisovatelná.');
}
/* Docasna deaktivace testu, dokud se to nerozojede 
$phpunit = $cache . '/phpunit';

if(@filemtime($phpunit) < time() - 3600 * 24 * 7) {
  copy('https://phar.phpunit.de/phpunit-7.phar', $phpunit);
}

chdir(__DIR__ . '/../');
call_check(['php', $phpunit]); // TODO doladit barvy

*/