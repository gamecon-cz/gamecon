<?php

use Gamecon\Pravo;

// práva - konkrétní práva identifikující nějak vlastnost uživatele
if (!defined('P_ORG_AKTIVIT')) define('P_ORG_AKTIVIT', Pravo::PORADANI_AKTIVIT); //může organizovat aktivity
if (!defined('P_KRYTI_AKCI')) define('P_KRYTI_AKCI', Pravo::PREKRYVANI_AKTIVIT); //může být na víc aktivitách naráz (org skupiny typicky)
if (!defined('P_PLNY_SERVIS')) define('P_PLNY_SERVIS', Pravo::PLNY_SERVIS); // uživatele kompletně platí a zajišťuje GC
if (!defined('P_ZMENA_HISTORIE')) define('P_ZMENA_HISTORIE', Pravo::ZMENA_HISTORIE_AKTIVIT); // jestli smí měnit přihlášení zpětně

if (!defined('P_ADMIN_INFOPULT')) define('P_ADMIN_INFOPULT', Pravo::ADMINISTRACE_INFOPULT); // přístup na titulku adminu
if (!defined('P_ADMIN_MUJ_PREHLED')) define('P_ADMIN_MUJ_PREHLED', Pravo::ADMINISTRACE_MOJE_AKTIVITY);

if (!defined('P_TRICKO_ZA_SLEVU_MODRE')) define('P_TRICKO_ZA_SLEVU_MODRE', Pravo::MODRE_TRICKO_ZDARMA); // modré tričko zdarma při slevě, jejíž hodnota je níže určená konstantou MODRE_TRICKO_ZDARMA_OD
if (!defined('P_DVE_TRICKA_ZDARMA')) define('P_DVE_TRICKA_ZDARMA', Pravo::DVE_JAKAKOLI_TRICKA_ZDARMA); // dvě jakákoli trička zdarma
if (!defined('P_TRICKO_MODRA_BARVA')) define('P_TRICKO_MODRA_BARVA', Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA); // může objednávat modrá trička
if (!defined('P_TRICKO_CERVENA_BARVA')) define('P_TRICKO_CERVENA_BARVA', Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA); // může objednávat červená trička
if (!defined('P_PLACKA_ZDARMA')) define('P_PLACKA_ZDARMA', Pravo::PLACKA_ZDARMA);
if (!defined('P_KOSTKA_ZDARMA')) define('P_KOSTKA_ZDARMA', Pravo::KOSTKA_ZDARMA);
if (!defined('P_JIDLO_SLEVA')) define('P_JIDLO_SLEVA', Pravo::JIDLO_SE_SLEVOU); // může si kupovat jídlo se slevou
if (!defined('P_JIDLO_ZDARMA')) define('P_JIDLO_ZDARMA', Pravo::JIDLO_ZDARMA); //může si objednávat jídlo a má ho zdarma
if (!defined('P_UBYTOVANI_ZDARMA')) define('P_UBYTOVANI_ZDARMA', Pravo::UBYTOVANI_ZDARMA); // má _všechno_ ubytování zdarma
if (!defined('P_UBYTOVANI_STREDA_ZDARMA')) define('P_UBYTOVANI_STREDA_ZDARMA', Pravo::STREDECNI_NOC_ZDARMA); // má středeční noc zdarma
if (!defined('P_UBYTOVANI_NEDELE_ZDARMA')) define('P_UBYTOVANI_NEDELE_ZDARMA', Pravo::NEDELNI_NOC_ZDARMA); // má nedělní noc zdarma
if (!defined('P_NERUSIT_OBJEDNAVKY')) define('P_NERUSIT_OBJEDNAVKY', Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY); // nebudou mu automaticky rušeny objednávky
if (!defined('P_AKTIVITY_SLEVA')) define('P_AKTIVITY_SLEVA', Pravo::CASTECNA_SLEVA_NA_AKTIVITY); // má 40% slevu na aktivity
if (!defined('P_AKTIVITY_ZDARMA')) define('P_AKTIVITY_ZDARMA', Pravo::AKTIVITY_ZDARMA); // má 100% slevu na aktivity
if (!defined('P_STATISTIKY_UCAST')) define('P_STATISTIKY_UCAST', Pravo::ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI); // role se vypisuje se v tabulce účasti v statistikách
if (!defined('P_REPORT_NEUBYTOVANI')) define('P_REPORT_NEUBYTOVANI', Pravo::VYPISOVAT_V_REPORTU_NEUBYTOVANYCH); // v reportu neubytovaných se vypisuje
if (!defined('P_TITUL_ORG')) define('P_TITUL_ORG', Pravo::TITUL_ORGANIZATOR); // v různých výpisech se označuje jako organizátor
if (!defined('P_UNIKATNI_ROLE')) define('P_UNIKATNI_ROLE', Pravo::UNIKATNI_ROLE); // uživatel může mít jen jednu roli s tímto právem
if (!defined('P_NEMA_BONUS_ZA_AKTIVITY')) define('P_NEMA_BONUS_ZA_AKTIVITY', Pravo::BEZ_SLEVY_ZA_VEDENI_AKTIVIT); // nedostává slevu za odvedené a tech. aktivity
