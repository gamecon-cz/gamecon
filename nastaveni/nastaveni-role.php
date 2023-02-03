<?php

use Gamecon\Role\Zidle;

// židle - nepoužívat pro vyjádření atributů (slev, možnosti se přihlašovat, …), na to jsou práva

// trvalé role
if (!defined('ZIDLE_ORGANIZATOR')) define('ZIDLE_ORGANIZATOR', 2);
if (!defined('ZIDLE_ORGANIZATOR_S_BONUSY_1')) define('ZIDLE_ORGANIZATOR_S_BONUSY_1', 21);
if (!defined('ZIDLE_ORGANIZATOR_S_BONUSY_2')) define('ZIDLE_ORGANIZATOR_S_BONUSY_2', 22);
if (!defined('ZIDLE_CESTNY_ORGANIZATOR')) define('ZIDLE_CESTNY_ORGANIZATOR', 15);
if (!defined('ZIDLE_SPRAVCE_FINANCI_GC')) define('ZIDLE_SPRAVCE_FINANCI_GC', 20);
if (!defined('ZIDLE_ADMIN')) define('ZIDLE_ADMIN', 16);
if (!defined('ZIDLE_VYPRAVECSKA_SKUPINA')) define('ZIDLE_VYPRAVECSKA_SKUPINA', 9);

// ročníkové role (židle) účasti
if (!defined('ZIDLE_PRIHLASEN')) define('ZIDLE_PRIHLASEN', Zidle::PRIHLASEN_NA_LETOSNI_GC()); // přihlášen na GameCon
if (!defined('ZIDLE_PRITOMEN')) define('ZIDLE_PRITOMEN', Zidle::PRITOMEN_NA_LETOSNIM_GC());    // prošel infopulteP_ORG_AKm a je na GameConu
if (!defined('ZIDLE_ODJEL')) define('ZIDLE_ODJEL', Zidle::ODJEL_Z_LETOSNIHO_GC());              // prošel infopultem na odchodu a odjel z GC

// dočasné, ročníkové role
if (!defined('ZIDLE_VYPRAVEC')) define('ZIDLE_VYPRAVEC', Zidle::LETOSNI_VYPRAVEC());
if (!defined('ZIDLE_ZAZEMI')) define('ZIDLE_ZAZEMI', Zidle::LETOSNI_ZAZEMI());
if (!defined('ZIDLE_INFOPULT')) define('ZIDLE_INFOPULT', Zidle::LETOSNI_INFOPULT());
if (!defined('ZIDLE_PARTNER')) define('ZIDLE_PARTNER', Zidle::LETOSNI_PARTNER());
if (!defined('ZIDLE_DOBROVOLNIK_SENIOR')) define('ZIDLE_DOBROVOLNIK_SENIOR', Zidle::LETOSNI_DOBROVOLNIK_SENIOR());
if (!defined('ZIDLE_STREDECNI_NOC_ZDARMA')) define('ZIDLE_STREDECNI_NOC_ZDARMA', Zidle::LETOSNI_STREDECNI_NOC_ZDARMA());
if (!defined('ZIDLE_NEDELNI_NOC_ZDARMA')) define('ZIDLE_NEDELNI_NOC_ZDARMA', Zidle::LETOSNI_NEDELNI_NOC_ZDARMA());
if (!defined('ZIDLE_NEODHLASOVAT')) define('ZIDLE_NEODHLASOVAT', Zidle::LETOSNI_NEODHLASOVAT());
if (!defined('ZIDLE_HERMAN')) define('ZIDLE_HERMAN', Zidle::LETOSNI_HERMAN());
if (!defined('ZIDLE_BRIGADNIK')) define('ZIDLE_BRIGADNIK', Zidle::LETOSNI_BRIGADNIK());
