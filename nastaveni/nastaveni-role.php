<?php

use Gamecon\Role\Role;

// role - nepoužívat pro vyjádření atributů (slev, možnosti se přihlašovat, …), na to jsou práva

// ročníkové role (židle) účasti
if (!defined('ROLE_PRIHLASEN')) define('ROLE_PRIHLASEN', Role::PRIHLASEN_NA_LETOSNI_GC()); // přihlášen na GameCon
if (!defined('ROLE_PRITOMEN')) define('ROLE_PRITOMEN', Role::PRITOMEN_NA_LETOSNIM_GC());    // prošel infopulteP_ORG_AKm a je na GameConu
if (!defined('ROLE_ODJEL')) define('ROLE_ODJEL', Role::ODJEL_Z_LETOSNIHO_GC());              // prošel infopultem na odchodu a odjel z GC
if (!defined('ROLE_ZKONTROLOVANE_UDAJE')) define('ROLE_ZKONTROLOVANE_UDAJE', Role::ZKONTROLOVANE_UDAJE_PRO_LETOSNI_GC());              // má ověřenou správnost údajů s OP
if (!defined('ROLE_DOSTAL_BALICEK')) define('ROLE_DOSTAL_BALICEK', Role::DOSTAL_BALICEK_NA_LETOSNIM_GC());              // prošel infopultem a dostal balíček

// dočasné, ročníkové role
if (!defined('ROLE_VYPRAVEC')) define('ROLE_VYPRAVEC', Role::LETOSNI_VYPRAVEC());
if (!defined('ROLE_ZAZEMI')) define('ROLE_ZAZEMI', Role::LETOSNI_ZAZEMI());
if (!defined('ROLE_INFOPULT')) define('ROLE_INFOPULT', Role::LETOSNI_INFOPULT());
if (!defined('ROLE_PARTNER')) define('ROLE_PARTNER', Role::LETOSNI_PARTNER());
if (!defined('ROLE_DOBROVOLNIK_SENIOR')) define('ROLE_DOBROVOLNIK_SENIOR', Role::LETOSNI_DOBROVOLNIK_SENIOR());
if (!defined('ROLE_STREDECNI_NOC_ZDARMA')) define('ROLE_STREDECNI_NOC_ZDARMA', Role::LETOSNI_STREDECNI_NOC_ZDARMA());
if (!defined('ROLE_CTVRTECNI_NOC_ZDARMA')) define('ROLE_CTVRTECNI_NOC_ZDARMA', Role::LETOSNI_CTVRTECNI_NOC_ZDARMA());
if (!defined('ROLE_PATECNI_NOC_ZDARMA')) define('ROLE_PATECNI_NOC_ZDARMA', Role::LETOSNI_PATECNI_NOC_ZDARMA());
if (!defined('ROLE_SOBOTNI_NOC_ZDARMA')) define('ROLE_SOBOTNI_NOC_ZDARMA', Role::LETOSNI_SOBOTNI_NOC_ZDARMA());
if (!defined('ROLE_NEDELNI_NOC_ZDARMA')) define('ROLE_NEDELNI_NOC_ZDARMA', Role::LETOSNI_NEDELNI_NOC_ZDARMA());
if (!defined('ROLE_NEODHLASOVAT')) define('ROLE_NEODHLASOVAT', Role::LETOSNI_NEODHLASOVAT());
if (!defined('ROLE_HERMAN')) define('ROLE_HERMAN', Role::LETOSNI_HERMAN());
if (!defined('ROLE_BRIGADNIK')) define('ROLE_BRIGADNIK', Role::LETOSNI_BRIGADNIK());
