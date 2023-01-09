<?php

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Pravo;

/**
 * Sada globálních proměnných a konstant sloužící jako konfigurace.
 *
 * Nepřidávat nesmysly co se dají zjistit jednoduše z DB nebo podobně…
 */

date_default_timezone_set('Europe/Prague');
mb_internal_encoding('UTF-8');

$puvodni = error_reporting(); // vymaskování notice, aby bylo možné "přetížit" konstanty dříve includnutými
error_reporting($puvodni ^ E_NOTICE);

if (!defined('DBM_NAME')) define('DBM_NAME', DB_NAME);
if (!defined('DBM_SERV')) define('DBM_SERV', DB_SERV);

if (!defined('ROK')) define('ROK', 2022); // aktuální rok -- při změně roku viz Překlápění ročníku na Gamecon Gdrive https://docs.google.com/document/d/1H_PM70WjNpQ1Xz65OYfr1BeSTdLrNQSkScMIZEtxWEc/edit

////////////////////////
// Nastavení ovládatelné z adminu //
////////////////////////

global $systemoveNastaveni;
$systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();
$systemoveNastaveni->zaznamyDoKonstant();

////////////////////////
// Základní nastavení //
////////////////////////

/**
 * https://trello.com/c/EgjfpfLZ/898-p%C5%99evod-ro%C4%8Dn%C3%ADku-na-2022#comment-61e83d40a3670882293b65c8
 * 2022
 * Registrace: 12. května (čtvrtek)
 * 1 vlna 19. května (čtvrtek)
 * 2 vlna 9. června (čtvrtek)
 * platby do: 30. června (čtvrtek)
 * 3 vlna + odhlašování: 1. července (pátek)
 * 2 odhlašování: 17. července (neděle)
 * GameCon: 21.-24. července
 * Rozhodný čas pro všechno relevantní: 20h:22m
 */

/////////////////////
// SAMOTNÝ GAMECON //
/////////////////////
// 2022-07-21 07:00:00 čtvrtek ve třetím týdnu v červenci
if (!defined('GC_BEZI_OD')) define('GC_BEZI_OD', $systemoveNastaveni->dejVychoziHodnotu('GC_BEZI_OD')); // začátek GameConu (přepnutí stránek do režimu "úpravy na jen na infopultu")
// 2022-07-24 21:00:00
if (!defined('GC_BEZI_DO')) define('GC_BEZI_DO', $systemoveNastaveni->dejVychoziHodnotu('GC_BEZI_DO')); // konec GameCou (přepnutí stránek do režimu "gc skončil, úpravy nemožné")

///////////////////////////
// REGISTRACE NA GAMECON //
///////////////////////////
// 2022-05-12 20:22:00
if (!defined('REG_GC_OD')) define('REG_GC_OD', DateTimeGamecon::zacatekRegistraciUcastniku(ROK)->formatDb()); // spuštění možnosti registrace na GameCon
if (!defined('REG_GC_DO')) define('REG_GC_DO', GC_BEZI_DO); // ukončení možnosti registrace na GameCon

////////////////////////////////////////////////////////
// REGISTRACE NA AKTIVITY (PRVNÍ, DRUHÁ A TŘETÍ VLNA) //
////////////////////////////////////////////////////////
// 2022-05-19 20:22:00
if (!defined('REG_AKTIVIT_OD')) define('REG_AKTIVIT_OD', DateTimeGamecon::zacatekPrvniVlnyOd(ROK)->formatDb()); // spuštění možnosti registrace na aktivity, pokud jsou aktivované 1. vlna
if (!defined('REG_AKTIVIT_DO')) define('REG_AKTIVIT_DO', GC_BEZI_DO); // ukončení možnosti registrace na aktivity
// 2022-06-30 23:59:00
if (!defined('HROMADNE_ODHLASOVANI')) define('HROMADNE_ODHLASOVANI' /* a začátek třetí vlny */, DateTimeGamecon::prvniHromadneOdhlasovaniOd(ROK)->formatDb()); // datum hromadného odhlašování neplatičů
// 2022-07-17 23:59:00
if (!defined('HROMADNE_ODHLASOVANI_2')) define('HROMADNE_ODHLASOVANI_2', DateTimeGamecon::druheHromadneOdhlasovaniOd(ROK)->formatDb()); // datum druhého hromadného odhlašování neplatičů

// 2022-07-13 00:00:00
if (!defined('PROGRAM_OD')) define('PROGRAM_OD', DateTimeGamecon::zacatekProgramu(ROK)->formatDb()); // první den programu
if (!defined('PROGRAM_DO')) define('PROGRAM_DO', GC_BEZI_DO); // poslední den programu
if (!defined('PROGRAM_VIDITELNY')) define('PROGRAM_VIDITELNY', po(REG_GC_OD)); // jestli jsou viditelné linky na program
if (!defined('CENY_VIDITELNE')) define('CENY_VIDITELNE', PROGRAM_VIDITELNY && pred(GC_BEZI_DO)); // jestli jsou viditelné ceny aktivit
if (!defined('FINANCE_VIDITELNE')) define('FINANCE_VIDITELNE', po(REG_GC_OD)); // jestli jsou public viditelné finance

///////////////////
// Židle a práva //
///////////////////

error_reporting($puvodni); // zrušení maskování notice
unset($puvodni);

// židle - nepoužívat pro vyjádření atributů (slev, možnosti se přihlašovat, …)
if (!defined('ZIDLE_PRIHLASEN')) define('ZIDLE_PRIHLASEN', \Gamecon\Zidle::prihlasenNaGcRoku(ROK)); // přihlášen na GameCon
if (!defined('ZIDLE_PRITOMEN')) define('ZIDLE_PRITOMEN', Gamecon\Zidle::pritomenNaGcRoku(ROK));    // prošel infopulteP_ORG_AKm a je na GameConu
if (!defined('ZIDLE_ODJEL')) define('ZIDLE_ODJEL', Gamecon\Zidle::odjelZGcRoku(ROK));           // prošel infopultem na odchodu a odjel z GC

// TODO byl přihlášen na GC a už není (kvůli počítání financí apod.)
if (!defined('ZIDLE_ORG_AKTIVIT')) define('ZIDLE_ORG_AKTIVIT', \Gamecon\Zidle::VYPRAVEC);               // vypravěč (org akcí)
if (!defined('ZIDLE_ORG_SKUPINA')) define('ZIDLE_ORG_SKUPINA', \Gamecon\Zidle::VYPRAVECSKA_SKUPINA);            //organizátorská skupina (Albi, Černobor, …)
if (!defined('ZIDLE_PARTNER')) define('ZIDLE_PARTNER', \Gamecon\Zidle::PARTNER);               //partner
if (!defined('ZIDLE_INFO')) define('ZIDLE_INFO', \Gamecon\Zidle::INFOPULT);                   //operátor/ka infopultu
if (!defined('ZIDLE_ZAZEMI')) define('ZIDLE_ZAZEMI', \Gamecon\Zidle::ZAZEMI);                 //člen/ka zázemí
if (!defined('ZIDLE_DOBROVOLNIK_S')) define('ZIDLE_DOBROVOLNIK_S', \Gamecon\Zidle::DOBROVOLNIK_SENIOR);         //dobrovolník senior

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
if (!defined('P_STATISTIKY_UCAST')) define('P_STATISTIKY_UCAST', Pravo::ZOBRAZOVAT_VE_STATISTIKACH_V_TABULCE_UCASTI); // židle se vypisuje se v tabulce účasti v statistikách
if (!defined('P_REPORT_NEUBYTOVANI')) define('P_REPORT_NEUBYTOVANI', Pravo::VYPISOVAT_V_REPORTU_NEUBYTOVANYCH); // v reportu neubytovaných se vypisuje
if (!defined('P_TITUL_ORG')) define('P_TITUL_ORG', Pravo::TITUL_ORGANIZATOR); // v různých výpisech se označuje jako organizátor
if (!defined('P_UNIKATNI_ZIDLE')) define('P_UNIKATNI_ZIDLE', Pravo::UNIKATNI_ZIDLE); // uživatel může mít jen jednu židli s tímto právem
if (!defined('P_NEMA_BONUS_ZA_AKTIVITY')) define('P_NEMA_BONUS_ZA_AKTIVITY', Pravo::BEZ_SLEVY_ZA_VEDENI_AKTIVIT); // nedostává slevu za odvedené a tech. aktivity

////////////////////////
// Finanční nastavení //
////////////////////////

if (!defined('UCET_CZ')) define('UCET_CZ', '2800035147/2010'); // číslo účtu pro platby v CZK - v statických stránkách není
if (!defined('IBAN')) define('IBAN', 'CZ2820100000002800035147'); // mezinárodní číslo účtu
if (!defined('BIC_SWIFT')) define('BIC_SWIFT', 'FIOBCZPPXXX'); // mezinárodní ID (něco jako mezinárodní VS)
//if (!defined('FIO_TOKEN')) define('FIO_TOKEN', ''); // tajné - musí nastavit lokální soubor definic

/////////////////////////
// Řetězcové konstanty //
/////////////////////////

$GLOBALS['HLASKY']       = [
    'aktivaceOk'           => 'Účet aktivován. Děkujeme.',
    'aktualizacePrihlasky' => 'Přihláška aktualizována.',
    'avatarChybaMazani'    => 'Obrázek se nepodařilo smazat.',
    'avatarNahran'         => 'Obrázek uživatele úspěšně uložen.',
    'avatarSmazan'         => 'Obrázek uživatele byl odstraněn.',
    'avatarSpatnyFormat'   => 'Obrázek není v požadovaném formátu .jpg.',
    'drdVypnuto'           => 'Přihlašování na mistrovství v DrD není spuštěno.',
    'chybaPrihlaseni'      => 'Špatné uživatelské jméno nebo heslo.',
    'jenPrihlaseni'        => 'Na zobrazení této stránky musíte být přihlášeni.',
    'jenPrihlaseniGC'      => 'Na přístup k této stránce musíte být přihlášeni na letošní GameCon.',
    'masKoliziAktivit'     => 'V daném čase už máš přihlášenu jinou aktivitu.',
    'maKoliziAktivit'      => 'V daném čase už má přihlášenu jinou aktivitu.',
    'nejsiPrihlasenNaGc'   => 'Nemáš aktivní přihlášku na GameCon.',
    'neniPrihlasenNaGc'    => 'Nemá aktivní přihlášku na GameCon.',
    'uzJsiPrihlasen'       => 'Na tuto aktivitu už jsi jednou přihlášen.',
    'uzJePrihlasen'        => 'Na tuto aktivitu už je jednou přihlášen.',
    'nyniPrihlaska'        => 'Nyní se vyplněním následujícího formuláře se přihlásíš na GameCon.',
    'plno'                 => 'Místa jsou už plná',
    'regOk'                => 'Účet vytvořen. Informaci o spuštění přihlašování ti včas pošleme e-mailem.',
    'regOkNyniPrihlaska'   => 'Údaje uloženy, vyplněním následujícího formuláře se přihlásíš na GameCon.',
    'upravaUzivatele'      => 'Změny registračních údajů uloženy.',
    'uzPrihlasen'          => 'Už jsi přihlášen na GameCon, zde můžeš upravit svou přihlášku.',
    'zamcena'              => 'Aktivitu už někdo zabral',
];
$GLOBALS['HLASKY_SUBST'] = [
    'odhlasilPlatil'              => 'Uživatel %1 (ID %2) se odhlásil z GameConu, ale v aktuálním roce (%3) si poslal %4 Kč. Bude vhodné to prověřit popř. smazat platby z připsaných a dát do zůstatku v seznamu uživatelů, aby mu peníze nepropadly',
    'odhlasilMelUbytovani'        => 'Uživatel %1 (ID %2) se odhlásil z GameConu a v aktuálním roce (%3) měl ubytování ve dnech %4. Uvolnilo se tak místo.',
    'uvolneneMisto'               => 'Na aktivitě %1, která se koná v %2 se uvolnilo místo. Tento e-mail dostáváš, protože jsi se přihlásil k sledování uvedené aktivity. Přihlaš se na aktivitu přes <a href="https://gamecon.cz/program">program</a> (pokud nebudeš dost rychlý, je možné že místo sebere někdo jiný).',
    'chybaClenaTymu'              => 'Nepodařilo se přihlásit tým. Při přihlášování uživatele %1 (id %2) se u něj objevila chyba: %3',
    'zapomenuteHeslo'             =>
        'Ahoj,

nechal{a} sis vygenerovat nové heslo na Gamecon.cz. Tvoje přihlašovací jméno je stejné jako e-mail (%1), tvoje nové heslo je %2. Heslo si prosím po přihlášení změň.

S pozdravem Tým organizátorů GameConu',
    'odhlaseniZGc'                => 'Odhlásil{a} ses z GameConu ' . ROK,
    'prihlaseniNaGc'              => 'Přihlásil{a} ses na GameCon ' . ROK,
    'prihlaseniTeamMail'          =>
        'Ahoj,

v rámci GameConu tě %1 přihlásil{a} na aktivitu %2, která se koná %3. Pokud s přihlášením nepočítáš nebo na aktivitu nemůžeš, dohodni se prosím s tím, kdo tě přihlásil a případně se můžeš odhlásit na <a href="https://gamecon.cz">webu gameconu</a>.

Pokud člověka, který tě přihlásil, neznáš, kontaktuj nás prosím na <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.',
    'kapacitaMaxUpo'              => 'Z ubytovací kapacity typu %1 je naplněno %2 míst z maxima %3 míst.',
    'rychloregMail'               =>
        'Ahoj,

děkujeme, že ses letos zúčastnil{a} GameConu. Kliknutím na odkaz níže potvrdíš registraci na web a můžeš si nastavit přezdívku a heslo, pokud chceš používat web a třeba přijet příští rok. (Pokud by ses registroval{a} na web později, musel{a} by sis nechat vygenerovat heslo znova)

<a href="https://gamecon.cz/potvrzeni-registrace/%2">https://gamecon.cz/potvrzeni-registrace/%2</a>',
    'nedostaveniSeNaAktivituMail' =>
        'Ahoj,

Zdá se, že jsi nedorazil{a} na přihlášenou aktivitu…

Chápeme, že se může stát spousta věcí, které změní situaci, a ty se nemůžeš/nechceš zúčastnit. Moc tě ale prosíme:

<div style="text-align: center; font-weight: bold">Vždy se z aktivity odhlaš.</div>

Vypravěč ani další účastníci na Tebe nemusí čekat a zjišťovat, jestli přijdeš. Zjednoduší se tím také hledání náhradníka. Když to navíc uděláš včas, dostaneš i nějaké peníze zpátky.

<div style="font-size: small">Pokud ses aktivity zúčastnil{a}, pak pravděpodobně nastala chyba v našem systému a my se za ni moc omlouváme. Můžeš tedy tento email ignorovat.</div>

Děkujeme za spolupráci,
Organizační tým GameConu',
];

/////////////////////////
// Nastavení přihlášek //
/////////////////////////
if (!defined('AUTOMATICKY_VYBER_TRICKA')) define('AUTOMATICKY_VYBER_TRICKA', false);
if (!defined('VYCHOZI_DOBROVOLNE_VSTUPNE')) define('VYCHOZI_DOBROVOLNE_VSTUPNE', 0);
if (!defined('VYZADOVANO_COVID_POTVRZENI')) define('VYZADOVANO_COVID_POTVRZENI', false);

//////////////////////////////////////////////
// Staré hodnoty a aliasy pro kompatibilitu //
//////////////////////////////////////////////

// odpočítané tvrdé údaje podle dat
if (!defined('REG_GC')) define('REG_GC', mezi(REG_GC_OD, REG_GC_DO));
if (!defined('REG_AKTIVIT')) define('REG_AKTIVIT', mezi(REG_AKTIVIT_OD, REG_AKTIVIT_DO));
if (!defined('GC_BEZI')) define('GC_BEZI', mezi(GC_BEZI_OD, GC_BEZI_DO)); // jestli gamecon aktivně běží (zakázání online registrací ubytování aj.) - do budoucna se vyvarovat a používat speciální konstanty per vlastnost

if (!defined('ARCHIV_OD')) define('ARCHIV_OD', 2009);           //rok, od kterého se vedou (nabízejí) archivy (aktivit atp.)
if (!defined('ID_PRAVO_PRIHLASEN')) define('ID_PRAVO_PRIHLASEN', ZIDLE_PRIHLASEN);
if (!defined('ID_PRAVO_PRITOMEN')) define('ID_PRAVO_PRITOMEN', ZIDLE_PRITOMEN);

if (!defined('ODHLASENI_POKUTA_KONTROLA')) define('ODHLASENI_POKUTA_KONTROLA', true); // jestli se má kontrolovat pozdní odhlášní z aktivit
if (!defined('ODHLASENI_POKUTA1_H')) define('ODHLASENI_POKUTA1_H', 24); // kolik hodin před aktivitou se začne uplatňovat pokuta 1

if (!defined('DEN_PRVNI_DATE')) define('DEN_PRVNI_DATE', date('Y-m-d', strtotime(PROGRAM_OD))); // první den v programu ve formátu YYYY-MM-DD
if (!defined('DEN_PRVNI_UBYTOVANI')) define('DEN_PRVNI_UBYTOVANI', DEN_PRVNI_DATE); // datum, kterému odpovídá ubytovani_den (tabulka shop_predmety) v hodnotě 0
if (!defined('PROGRAM_ZACATEK')) define('PROGRAM_ZACATEK', 8); // první hodina programu
if (!defined('PROGRAM_KONEC')) define('PROGRAM_KONEC', 24); // konec programu (tuto hodinu už se nehraje)

if (!defined('MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM')) define('MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM', 20);
if (!defined('MOJE_AKTIVITY_PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM')) define('MOJE_AKTIVITY_PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM', 10);

if (!defined('PRODEJ_JIDLA_POZASTAVEN')) define('PRODEJ_JIDLA_POZASTAVEN', false);

if (!defined('SUPERADMINI')) define('SUPERADMINI', [4032 /* Jaroslav "Kostřivec" Týc */, 1112 /* Lenka "Cemi" Zavadilová */]);

if (!defined('ADRESAR_WEBU_S_OBRAZKY')) define('ADRESAR_WEBU_S_OBRAZKY', __DIR__ . '/../web');

if (!defined('PROJECT_ROOT_DIR')) define('PROJECT_ROOT_DIR', __DIR__ . '/..');
if (!defined('WWW')) define('WWW', __DIR__ . '/../web');
if (!defined('ADMIN')) define('ADMIN', __DIR__ . '/../admin');
if (!defined('SPEC')) define('SPEC', __DIR__ . '/../cache/private');
if (!defined('CACHE')) define('CACHE', __DIR__ . '/../cache/public');
if (!defined('SQL_MIGRACE_DIR')) define('SQL_MIGRACE_DIR', __DIR__ . '/../migrace');
if (!defined('ZALOHA_DB_SLOZKA')) define('ZALOHA_DB_SLOZKA', __DIR__ . '/../backup/db'); // cesta pro zálohy databáze
if (!defined('ADMIN_STAMPS')) define('ADMIN_STAMPS', rtrim(ADMIN, '/') . '/stamps');
if (!defined('NAZEV_SPOLECNOSTI_GAMECON')) define('NAZEV_SPOLECNOSTI_GAMECON', 'GameCon z.s.');

if (!defined('AUTOMATICKE_MIGRACE')) define('AUTOMATICKE_MIGRACE', false);
if (!defined('AUTOMATICKA_TVORBA_DB')) define('AUTOMATICKA_TVORBA_DB', false);
if (!defined('ZOBRAZIT_STACKTRACE_VYJIMKY')) define('ZOBRAZIT_STACKTRACE_VYJIMKY', false);
if (!defined('PROFILOVACI_LISTA')) define('PROFILOVACI_LISTA', false);
if (!defined('CACHE_SLOZKY_PRAVA')) define('CACHE_SLOZKY_PRAVA', 0770);

if (!defined('PRIJEMCI_CHYB')) define('PRIJEMCI_CHYB', ['it@gamecon.cz']);
