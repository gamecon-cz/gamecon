<?php
/**
 * Jen redirect na report s neprihlasenymi a neubytovanymi Vypraveci
 *
 * nazev: Neubytovaní vypravěči
 * pravo: 105
 * submenu_group: 2
 * submenu_group: 3
 */

header('Location: /reporty/neprihlaseni-vypraveci?format=html', true, 301);
exit();
