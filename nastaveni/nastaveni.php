<?php

/**
 * Sada globálních proměnných a konstant sloužící jako konfigurace.
 *
 * Nepřidávat nesmysly co se dají zjistit jednoduše z DB nebo podobně…
 */

date_default_timezone_set('Europe/Prague');
mb_internal_encoding('UTF-8');

$puvodni = error_reporting(); // vymaskování notice, aby bylo možné "přetížit" konstanty dříve includnutými
error_reporting($puvodni ^ E_NOTICE);


////////////////////////
// Základní nastavení //
////////////////////////

define('ROK', 2018);                                  // aktuální rok -- při změně roku viz http://bit.ly/2l5olnb
define('GC_BEZI_OD',        ROK.'-07-19 07:00:00');   // začátek GameConu (přepnutí stránek do režimu "úpravy na jen na infopultu")
define('GC_BEZI_DO',        ROK.'-07-22 20:00:00');   // konec GameCou (přepnutí stránek do režimu "gc skončil, úpravy nemožné")
define('REG_GC_OD',         ROK.'-05-02 20:17:00');   // spuštění možnosti registrace na GameCon
define('REG_GC_DO',         GC_BEZI_DO);              // ukončení možnosti registrace na GameCon
define('REG_AKTIVIT_OD',    ROK.'-05-09 20:17:00');   // spuštění možnosti registrace na aktivity, pokud jsou aktivované
define('REG_AKTIVIT_DO',    GC_BEZI_DO);              // ukončení možnosti registrace na aktivity
define('SLEVA_DO',          ROK.'-06-30 23:59:59');   // do kdy se oficiálně počítá platba včas
define('PROGRAM_OD',        ROK.'-07-19');            // první den programu
define('PROGRAM_DO',        GC_BEZI_DO);              // poslední den programu
define('PROGRAM_VIDITELNY', po(REG_GC_OD));           // jestli jsou viditelné linky na program
define('CENY_VIDITELNE',    PROGRAM_VIDITELNY && pred(GC_BEZI_DO)); // jestli jsou viditelné ceny aktivit
define('FINANCE_VIDITELNE', po(REG_GC_OD));           // jestli jsou public viditelné finance


///////////////////
// Židle a práva //
///////////////////

error_reporting($puvodni); // zrušení maskování notice
unset($puvodni);
$pre=-(ROK-2000)*100; //předpona pro židle a práva vázaná na aktuální rok
// židle - nepoužívat pro vyjádření atributů (slev, možnosti se přihlašovat, …)
define('Z_PRIHLASEN', $pre-1);  //přihlášen na GameCon
define('Z_PRITOMEN',  $pre-2);  //prošel infopultem a je na GameConu
define('Z_ODJEL',     $pre-3);  //prošel infopultem na odchodu a odjel z GC
// TODO byl přihlášen na GC a už není (kvůli počítání financí apod.)
define('Z_ORG', 2);             //organizátor z core org teamu
define('Z_ORG_AKCI',6);         //vypravěč (org akcí)
define('Z_ORG_SKUPINA',9);      //organizátorská skupina (Albi, Černobor, …)
define('Z_STUDENT',10);         //student (uvedl o sobě)
define('Z_VCAS',11);            //zaplatil včas
define('Z_PARTNER',13);         //partner
define('Z_INFO',8);             //operátor/ka infopultu
define('Z_ZAZEMI',7);           //člen/ka zázemí
define('Z_DOBROVOLNIK_S', 17);  //dobrovolník senior
// práva - konkrétní práva identifikující nějak vlastnost uživatele
define('P_ORG_AKCI',4);         //může organizovat aktivity
define('P_KRYTI_AKCI',5);       //může být na víc aktivitách naráz (org skupiny typicky)
define('P_SPOULUPRACOVNIK',6);  //zobrazení v reportu spolupracovníci
define('P_PLNY_SERVIS',7);      //uživatele kompletně platí a zajišťuje GC
define('P_ZMENA_HISTORIE', 8);
define('P_TRIKO_ZDARMA',1000);  //(organizátorské) tričko zdarma
define('P_TRIKO_ZA_SLEVU', 1011);
define('P_TRIKO_ZA_SLEVU_MODRE', 1012);
define('P_TRIKO_SLEVA', 1013);
define('P_TRIKO_SLEVA_MODRE', 1014);
define('P_PLACKA_ZDARMA',1002);
define('P_KOSTKA_ZDARMA',1003);
define('P_JIDLO_SLEVA',1004);   //může si kupovat jídlo se slevou
define('P_JIDLO_ZDARMA',1005);  //může si objednávat jídlo a má ho zdarma
define('P_JIDLO_SNIDANE',1010); //může si objednat snídani
define('P_SLEVA_STUDENT',1007); //má studentskou slevu 20%
define('P_SLEVA_VCAS',1006);    //má slevu 20% za platbu včas
define('P_SLEVA_AKTIVITY',1009); //za pořádané aktivity se mu vypočítává sleva
define('P_UBYTOVANI_ZDARMA',1008); //má _všechno_ ubytování zdarma
define('P_UBYTOVANI_STREDA_ZDARMA', 1015); // má středeční noc zdarma
define('P_UBYTOVANI_NEDELE_ZDARMA', 1018); // má nedělní noc zdarma
define('P_ADMIN_UVOD', 100);    //přístup na titulku adminu
define('P_ADMIN_MUJ_PREHLED', 109);
define('P_NERUSIT_OBJEDNAVKY', 1016); // nebudou mu automaticky rušeny objednávky
define('P_PARCON_ZDARMA', 1017);
unset($pre);


////////////////////////
// Finanční nastavení //
////////////////////////

define('KURZ_EURO', 25.50);                 // kurz kč:euro
define('UCET_CZ', '2800035147/2010');       // číslo účtu pro platby v CZK - v statických stránkách není
define('IBAN', 'CZ2820100000002800035147'); // mezinárodní číslo účtu
define('BIC_SWIFT', 'FIOBCZPPXXX');         // mezinárodní ID (něco jako mezinárodní VS)
//define('FIO_TOKEN', ''); // tajné - musí nastavit lokální soubor definic


/////////////////////////
// Řetězcové konstanty //
/////////////////////////

$GLOBALS['HLASKY']=[
  'aktivaceOk'=>'Účet aktivován. Děkujeme.',
  'aktualizacePrihlasky'=>'Přihláška aktualizována.',
  'avatarChybaMazani'=>'Obrázek se nepodařilo smazat.',
  'avatarNahran'=>'Obrázek uživatele úspěšně uložen.',
  'avatarSmazan'=>'Obrázek uživatele byl odstraněn.',
  'avatarSpatnyFormat'=>'Obrázek není v požadovaném formátu .jpg.',
  'drdVypnuto'=>'Přihlašování na mistrovství v DrD není spuštěno.',
  'chybaPrihlaseni'=>'Špatné uživatelské jméno nebo heslo.',
  'jenPrihlaseni'=>'Na zobrazení této stránky musíte být přihlášeni.',
  'jenPrihlaseniGC'=>'Na přístup k této stránce musíte být přihlášeni na letošní GameCon.',
  'kolizeAktivit'=>'V daném čase už máte přihlášenu jinou aktivitu.',
  'maxJednou'=>'Na tuto aktivitu už jste jednou přihlášeni.',
  'nyniPrihlaska'=>'Nyní se vyplněním následujícího formuláře se přihlásíš na GameCon.',
  'plno'=>'Místa jsou už plná',
  'prihlaseniJenInfo'=>'Registrace přes internet jsou ukončeny, <strong>registrovat se můžete přímo na místě na infopultu</strong>. Upravit si program a vybrat aktivity můžete tamtéž.',
  'prihlaseniVypnuto'=>'Přihlašování na GameCon není spuštěno.',
  'regOk'=>'Uživatel zaregistrován. Pokračujte podle instrukcí, které vám dojdou e-mailem.',
  'regOkNyniPrihlaska'=>'Údaje uloženy, vyplněním následujícího formuláře se přihlásíš na GameCon.',
  'upravaUzivatele'=>'Změny registračních údajů uloženy.',
  'uzPrihlasen'=>'Už jsi přihlášen na GameCon, zde můžeš upravit svou přihlášku.',
  'zamcena'=>'Aktivitu už někdo zabral',
];
$GLOBALS['HLASKY_SUBST']=[
  'zapomenuteHeslo'=>
'Ahoj,

nechal{a} sis vygenerovat nové heslo na Gamecon.cz. Tvoje přihlašovací jméno je stejné jako e-mail (%1), tvoje nové heslo je %2. Heslo si prosím po přihlášení změň.

S pozdravem Tým organizátorů GameConu',
  'odhlaseniZGc'=>'Odhlásil{a} ses z GameConu '.ROK,
  'prihlaseniNaGc'=>'Přihlásil{a} ses na GameCon '.ROK,
  'prihlaseniTeamMail'=>
'Ahoj,

v rámci GameConu tě %1 přihlásil{a} na aktivitu %2, která se koná %3. Pokud s přihlášením nepočítáš nebo na aktivitu nemůžeš, dohodni se prosím s tím, kdo tě přihlásil a případně se můžeš odhlásit na <a href="http://gamecon.cz">webu gameconu</a>.

Pokud člověka, který tě přihlásil, neznáš, kontaktuj nás prosím na <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.',
  'kapacitaMaxUpo'=>'Z ubytovací kapacity typu %1 je naplněno %2 míst z maxima %3 míst.',
  'rychloregMail'=>
'Ahoj,

děkujeme, že ses letos zúčastnil{a} GameConu. Kliknutím na odkaz níže potvrdíš registraci na web a můžeš si nastavit přezdívku a heslo, pokud chceš používat web a třeba přijet příští rok. (Pokud by ses registroval{a} na web později, musel{a} by sis nechat vygenerovat heslo znova)

<a href="http://gamecon.cz/potvrzeni-registrace/%2">http://gamecon.cz/potvrzeni-registrace/%2</a>',
];


//////////////////////////////////////////////
// Staré hodnoty a aliasy pro kompatibilitu //
//////////////////////////////////////////////

// odpočítané tvrdé údaje podle dat
define('REG_GC', mezi(REG_GC_OD, REG_GC_DO));
define('REG_AKTIVIT', mezi(REG_AKTIVIT_OD, REG_AKTIVIT_DO));
define('GC_BEZI', mezi(GC_BEZI_OD, GC_BEZI_DO)); // jestli gamecon aktivně běží (zakázání online registrací ubytování aj.) - do budoucna se vyvarovat a používat speciální konstanty per vlastnost

define('ROK_AKTUALNI',$GLOBALS['ROK_AKTUALNI']=ROK);
define('ARCHIV_OD',2009);           //rok, od kterého se vedou (nabízejí) archivy (aktivit atp.)
define('REGISTRACE_AKTIVNI',$GLOBALS['REGISTRACE_AKTIVNI']=REG_GC);
define('GAMECON_BEZI', GC_BEZI);
define('REG_DRD',false); // DEPRECATED jestli se smí registrovat na mistrovství v DrD
define('ID_PRAVO_PRIHLASEN',$GLOBALS['ID_PRAVO_PRIHLASEN']= Z_PRIHLASEN); // fixme
define('ID_PRAVO_PRITOMEN',$GLOBALS['ID_PRAVO_PRITOMEN']=   Z_PRITOMEN);
define('ID_ZIDLE_PRIHLASEN',$GLOBALS['ID_ZIDLE_PRIHLASEN']= Z_PRIHLASEN);
define('ID_ZIDLE_PRITOMEN',$GLOBALS['ID_ZIDLE_PRITOMEN']=   Z_PRITOMEN);

define('ID_PRAVO_TAB_PROGRAM_REPORTY',$GLOBALS['ID_PRAVO_TAB_PROGRAM_REPORTY']=200);  //právo vidět reporty v tabulce programu
define('ID_PRAVO_ORG_SEKCE',$GLOBALS['ID_PRAVO_ORG_SEKCE']=2);     //viditelnost org sekce ve fóru a org sekcí na různých stránkách
define('ID_PRAVO_TESTER',$GLOBALS['ID_PRAVO_TESTER']=999);      //právo pro testery (zobrazení testovacích věcí)
define('ID_PRAVO_ORG_AKCI',$GLOBALS['ID_PRAVO_ORG_AKCI']=P_ORG_AKCI);  //právo pořádat akce z něhož vyplývá viditelnost v nabídce vypravěčů v tvorbě aktivit

define('P_ORG',2); // DEPRECATED právo organizátor čistě idetifikující organizátora !bez další sémantiky! (v podstatě čistě židle organizátor). Použití max. v adminu pro "uživatel je organizátor" apod.
define('SLEVA_AKTIVNI',pred(SLEVA_DO));  //jestli se počítá sleva za včasnou platbu
define('BONUS_ZAPORNY',$GLOBALS['BONUS_ZAPORNY']=SLEVA_AKTIVNI);   //jestli smí být bonus při odečítání věcí záporný
define('ODHLASENI_POKUTA_KONTROLA_DATE',ROK.'-07-18'); //den, od kterého (00:00) začíná systém kontrolovat, jestli jsou aktivity odhlašovány včas (mělo by být víc než 24h před první aktivitou)
define('ODHLASENI_POKUTA_KONTROLA',time()-strtotime(ODHLASENI_POKUTA_KONTROLA_DATE)>0);  //jestli se má kontrolovat pozdní odhlášní z aktivit
define('ODHLASENI_POKUTA1_H',24);      //kolik hodin před aktivitou se začne uplatňovat pokuta 1
//ceny věcí
define('DEN_PRVNI_DATE', date('Y-m-d', strtotime(PROGRAM_OD))); // první den v programu ve formátu YYYY-MM-DD
define('DEN_PRVNI_UBYTOVANI', DEN_PRVNI_DATE);                  // datum, kterému odpovídá ubytovani_den (tabulka shop_predmety) v hodnotě 0
define('PROGRAM_ZACATEK', 8);   // první hodina programu
define('PROGRAM_KONEC', 24);    // konec programu (tuto hodinu už se nehraje)
