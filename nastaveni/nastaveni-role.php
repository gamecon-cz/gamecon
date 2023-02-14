<?php

use Gamecon\Role\Role;

// role - nepoužívat pro vyjádření atributů (slev, možnosti se přihlašovat, …), na to jsou práva

// trvalé role
if (!defined('ROLE_ORGANIZATOR')) define('ROLE_ORGANIZATOR', 2);
if (!defined('ROLE_ORGANIZATOR_S_BONUSY_1')) define('ROLE_PUL_ORG_BONUS_UBYTKO', 21);
if (!defined('ROLE_ORGANIZATOR_S_BONUSY_2')) define('ROLE_PUL_ORG_BONUS_TRICKA', 22);
if (!defined('ROLE_CESTNY_ORGANIZATOR')) define('ROLE_CESTNY_ORGANIZATOR', 15);
if (!defined('ROLE_SPRAVCE_FINANCI_GC')) define('ROLE_SPRAVCE_FINANCI_GC', 20);
if (!defined('ROLE_ADMIN')) define('ROLE_PREZENCNI_ADMIN', 16);
if (!defined('ROLE_VYPRAVECSKA_SKUPINA')) define('ROLE_VYPRAVECSKA_SKUPINA', 9);

// ročníkové role (židle) účasti
if (!defined('ROLE_PRIHLASEN')) define('ROLE_PRIHLASEN', Role::PRIHLASEN_NA_LETOSNI_GC()); // přihlášen na GameCon
if (!defined('ROLE_PRITOMEN')) define('ROLE_PRITOMEN', Role::PRITOMEN_NA_LETOSNIM_GC());    // prošel infopulteP_ORG_AKm a je na GameConu
if (!defined('ROLE_ODJEL')) define('ROLE_ODJEL', Role::ODJEL_Z_LETOSNIHO_GC());              // prošel infopultem na odchodu a odjel z GC

// dočasné, ročníkové role
if (!defined('ROLE_VYPRAVEC')) define('ROLE_VYPRAVEC', Role::LETOSNI_VYPRAVEC());
if (!defined('ROLE_ZAZEMI')) define('ROLE_ZAZEMI', Role::LETOSNI_ZAZEMI());
if (!defined('ROLE_INFOPULT')) define('ROLE_INFOPULT', Role::LETOSNI_INFOPULT());
if (!defined('ROLE_PARTNER')) define('ROLE_PARTNER', Role::LETOSNI_PARTNER());
if (!defined('ROLE_DOBROVOLNIK_SENIOR')) define('ROLE_DOBROVOLNIK_SENIOR', Role::LETOSNI_DOBROVOLNIK_SENIOR());
if (!defined('ROLE_STREDECNI_NOC_ZDARMA')) define('ROLE_STREDECNI_NOC_ZDARMA', Role::LETOSNI_STREDECNI_NOC_ZDARMA());
if (!defined('ROLE_NEDELNI_NOC_ZDARMA')) define('ROLE_NEDELNI_NOC_ZDARMA', Role::LETOSNI_NEDELNI_NOC_ZDARMA());
if (!defined('ROLE_NEODHLASOVAT')) define('ROLE_NEODHLASOVAT', Role::LETOSNI_NEODHLASOVAT());
if (!defined('ROLE_HERMAN')) define('ROLE_HERMAN', Role::LETOSNI_HERMAN());
if (!defined('ROLE_BRIGADNIK')) define('ROLE_BRIGADNIK', Role::LETOSNI_BRIGADNIK());
