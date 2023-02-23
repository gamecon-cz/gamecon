<?php
/**
 * Jen redirect na report s neprihlasenymi a neubytovanymi Vypraveci
 *
 * nazev: Neubytovaní vypravěči
 * pravo: 105
 * submenu_group: 2
 * submenu_order: 3
 */

$adminUrl = URL_ADMIN;
header("Location: $adminUrl/reporty/neprihlaseni-vypraveci?format=html", true, 301);
exit();
