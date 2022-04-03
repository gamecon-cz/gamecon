<?php
/**
 * Jen redirect na report s neprihlasenymi a neubytovanymi Vypraveci
 *
 * nazev: Program po místnostech
 * pravo: 105
 * submenu_group: 4
 * submenu_order: 3
 * submenu_link_open_in_blank: 1
 */

header('Location: ' . URL_ADMIN . '/program-mistnosti', true, 301);
exit();
