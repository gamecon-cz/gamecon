<?php

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

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

@define('ROK', 2022); // aktuální rok -- při změně roku viz Překlápění ročníku na Gamecon Gdrive https://docs.google.com/document/d/1H_PM70WjNpQ1Xz65OYfr1BeSTdLrNQSkScMIZEtxWEc/edit

/**
 * https://trello.com/c/EgjfpfLZ/898-p%C5%99evod-ro%C4%8Dn%C3%ADku-na-2022#comment-61e83d40a3670882293b65c8
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
// 2022-07-14 07:00:00 čtvrtek ve třetím týdnu v červenci
@define('GC_BEZI_OD', DateTimeGamecon::zacatekGameconu(ROK)->formatDb()); // začátek GameConu (přepnutí stránek do režimu "úpravy na jen na infopultu")
// 2022-07-17 21:00:00
@define('GC_BEZI_DO', DateTimeGamecon::konecGameconu(ROK)->formatDb()); // konec GameCou (přepnutí stránek do režimu "gc skončil, úpravy nemožné")

///////////////////////////
// REGISTRACE NA GAMECON //
///////////////////////////
// 2022-05-12 20:22:00
@define('REG_GC_OD', DateTimeGamecon::zacatekRegistraciNavstevniku(ROK)->formatDb()); // spuštění možnosti registrace na GameCon
@define('REG_GC_DO', GC_BEZI_DO); // ukončení možnosti registrace na GameCon

////////////////////////////////////////////////////////
// REGISTRACE NA AKTIVITY (PRVNÍ, DRUHÁ A TŘETÍ VLNA) //
////////////////////////////////////////////////////////
// 2022-05-19 20:22:00
@define('REG_AKTIVIT_OD', DateTimeGamecon::zacatekPrvniVlnyOd(ROK)->formatDb()); // spuštění možnosti registrace na aktivity, pokud jsou aktivované 1. vlna
@define('REG_AKTIVIT_DO', GC_BEZI_DO); // ukončení možnosti registrace na aktivity
// 2022-06-30 23:59:00
@define('HROMADNE_ODHLASOVANI' /* a začátek třetí vlny */, DateTimeGamecon::prvniHromadneOdhlasovaniOd(ROK)->formatDb()); // datum hromadného odhlašování neplatičů
// 2022-07-17 23:59:00
@define('HROMADNE_ODHLASOVANI_2', DateTimeGamecon::druheHromadneOdhlasovaniOd(ROK)->formatDb()); // datum druhého hromadného odhlašování neplatičů

// 2022-07-13 00:00:00
@define('PROGRAM_OD', DateTimeGamecon::zacatekProgramu(ROK)->formatDb()); // první den programu
@define('PROGRAM_DO', GC_BEZI_DO); // poslední den programu
@define('PROGRAM_VIDITELNY', po(REG_GC_OD)); // jestli jsou viditelné linky na program
@define('CENY_VIDITELNE', PROGRAM_VIDITELNY && pred(GC_BEZI_DO)); // jestli jsou viditelné ceny aktivit
@define('FINANCE_VIDITELNE', po(REG_GC_OD)); // jestli jsou public viditelné finance

///////////////////
// Židle a práva //
///////////////////

error_reporting($puvodni); // zrušení maskování notice
unset($puvodni);
$pre = -(ROK - 2000) * 100; //předpona pro židle a práva vázaná na aktuální rok
// židle - nepoužívat pro vyjádření atributů (slev, možnosti se přihlašovat, …)
@define('ZIDLE_PRIHLASEN', $pre - 1);       //přihlášen na GameCon
@define('ZIDLE_PRITOMEN', $pre - 2);        //prošel infopulteP_ORG_AKm a je na GameConu
@define('ZIDLE_ODJEL', $pre - 3);           //prošel infopultem na odchodu a odjel z GC
// TODO byl přihlášen na GC a už není (kvůli počítání financí apod.)
@define('ZIDLE_ORG_AKTIVIT', 6);               // vypravěč (org akcí)
@define('ZIDLE_ORG_SKUPINA', 9);            //organizátorská skupina (Albi, Černobor, …)
@define('ZIDLE_PARTNER', 13);               //partner
@define('ZIDLE_INFO', 8);                   //operátor/ka infopultu
@define('ZIDLE_ZAZEMI', 7);                 //člen/ka zázemí
@define('ZIDLE_DOBROVOLNIK_S', 17);         //dobrovolník senior

// práva - konkrétní práva identifikující nějak vlastnost uživatele
@define('P_ORG_AKTIVIT', 4); //může organizovat aktivity
@define('P_KRYTI_AKCI', 5); //může být na víc aktivitách naráz (org skupiny typicky)
@define('P_PLNY_SERVIS', 7); //uživatele kompletně platí a zajišťuje GC
@define('P_ZMENA_HISTORIE', 8); // jestli smí měnit přihlášení zpětně
@define('P_TRICKO_ZA_SLEVU_MODRE', 1012); // modré tričko zdarma při slevě, jejíž hodnota je níže určená konstantou MODRE_TRICKO_ZDARMA_OD
@define('P_DVE_TRICKA_ZDARMA', 1020); // dvě jakákoli trička zdarma
@define('P_TRICKO_MODRA_BARVA', 1021); // může objednávat modrá trička
@define('P_TRICKO_CERVENA_BARVA', 1022); // může objednávat červená trička
@define('P_PLACKA_ZDARMA', 1002);
@define('P_KOSTKA_ZDARMA', 1003);
@define('P_JIDLO_SLEVA', 1004); //může si kupovat jídlo se slevou
@define('P_JIDLO_ZDARMA', 1005); //může si objednávat jídlo a má ho zdarma
@define('P_UBYTOVANI_ZDARMA', 1008); //má _všechno_ ubytování zdarma
@define('P_UBYTOVANI_STREDA_ZDARMA', 1015); // má středeční noc zdarma
@define('P_UBYTOVANI_NEDELE_ZDARMA', 1018); // má nedělní noc zdarma
@define('P_ADMIN_UVOD', 100); //přístup na titulku adminu
@define('P_ADMIN_MUJ_PREHLED', 109);
@define('P_NERUSIT_OBJEDNAVKY', 1016); // nebudou mu automaticky rušeny objednávky
@define('P_AKTIVITY_SLEVA', 1019); // má 40% slevu na aktivity
@define('P_AKTIVITY_ZDARMA', 1023); // má 100% slevu na aktivity
@define('P_STATISTIKY_UCAST', 1024); // židle se vypisuje se v tabulce účasti v statistikách
@define('P_REPORT_NEUBYTOVANI', 1025); // v reportu neubytovaných se vypisuje
@define('P_TITUL_ORG', 1026); // v různých výpisech se označuje jako organizátor
@define('P_UNIKATNI_ZIDLE', 1027); // uživatel může mít jen jednu židli s tímto právem
@define('P_NEMA_BONUS_ZA_AKTIVITY', 1028);// nedostává slevu za odvedené a tech. aktivity
unset($pre);

////////////////////////
// Finanční nastavení //
////////////////////////

@define('UCET_CZ', '2800035147/2010'); // číslo účtu pro platby v CZK - v statických stránkách není
@define('IBAN', 'CZ2820100000002800035147'); // mezinárodní číslo účtu
@define('BIC_SWIFT', 'FIOBCZPPXXX'); // mezinárodní ID (něco jako mezinárodní VS)
//@define('FIO_TOKEN', ''); // tajné - musí nastavit lokální soubor definic

/////////////////////////
// Řetězcové konstanty //
/////////////////////////

$GLOBALS['HLASKY'] = [
    'aktivaceOk' => 'Účet aktivován. Děkujeme.',
    'aktualizacePrihlasky' => 'Přihláška aktualizována.',
    'avatarChybaMazani' => 'Obrázek se nepodařilo smazat.',
    'avatarNahran' => 'Obrázek uživatele úspěšně uložen.',
    'avatarSmazan' => 'Obrázek uživatele byl odstraněn.',
    'avatarSpatnyFormat' => 'Obrázek není v požadovaném formátu .jpg.',
    'drdVypnuto' => 'Přihlašování na mistrovství v DrD není spuštěno.',
    'chybaPrihlaseni' => 'Špatné uživatelské jméno nebo heslo.',
    'jenPrihlaseni' => 'Na zobrazení této stránky musíte být přihlášeni.',
    'jenPrihlaseniGC' => 'Na přístup k této stránce musíte být přihlášeni na letošní GameCon.',
    'kolizeAktivit' => 'V daném čase už máš přihlášenu jinou aktivitu.',
    'maxJednou' => 'Na tuto aktivitu už jste jednou přihlášeni.',
    'nyniPrihlaska' => 'Nyní se vyplněním následujícího formuláře se přihlásíš na GameCon.',
    'plno' => 'Místa jsou už plná',
    'regOk' => 'Účet vytvořen. Informaci o spuštění přihlašování ti včas pošleme e-mailem.',
    'regOkNyniPrihlaska' => 'Údaje uloženy, vyplněním následujícího formuláře se přihlásíš na GameCon.',
    'upravaUzivatele' => 'Změny registračních údajů uloženy.',
    'uzPrihlasen' => 'Už jsi přihlášen na GameCon, zde můžeš upravit svou přihlášku.',
    'zamcena' => 'Aktivitu už někdo zabral',
];
$GLOBALS['HLASKY_SUBST'] = [
    'odhlasilPlatil' => 'Uživatel %1 (ID %2) se odhlásil z GameConu, ale v aktuálním roce (%3) si převedl nějaké peníze. Bude vhodné to prověřit popř. smazat platbu z připsaných a dát do zůstatku v seznamu uživatelů, aby mu peníze nepropadly',
    'uvolneneMisto' => 'Na aktivitě %1, která se koná v %2 se uvolnilo místo. Tento e-mail dostáváš, protože jsi se přihlásil k sledování uvedené aktivity. Přihlaš se na aktivitu přes <a href="https://gamecon.cz/program">program</a> (pokud nebudeš dost rychlý, je možné že místo sebere někdo jiný).',
    'chybaClenaTymu' => 'Nepodařilo se přihlásit tým. Při přihlášování uživatele %1 (id %2) se u něj objevila chyba: %3',
    'zapomenuteHeslo' =>
        'Ahoj,

nechal{a} sis vygenerovat nové heslo na Gamecon.cz. Tvoje přihlašovací jméno je stejné jako e-mail (%1), tvoje nové heslo je %2. Heslo si prosím po přihlášení změň.

S pozdravem Tým organizátorů GameConu',
    'odhlaseniZGc' => 'Odhlásil{a} ses z GameConu ' . ROK,
    'prihlaseniNaGc' => 'Přihlásil{a} ses na GameCon ' . ROK,
    'prihlaseniTeamMail' =>
        'Ahoj,

v rámci GameConu tě %1 přihlásil{a} na aktivitu %2, která se koná %3. Pokud s přihlášením nepočítáš nebo na aktivitu nemůžeš, dohodni se prosím s tím, kdo tě přihlásil a případně se můžeš odhlásit na <a href="https://gamecon.cz">webu gameconu</a>.

Pokud člověka, který tě přihlásil, neznáš, kontaktuj nás prosím na <a href="mailto:info@gamecon.cz">info@gamecon.cz</a>.',
    'kapacitaMaxUpo' => 'Z ubytovací kapacity typu %1 je naplněno %2 míst z maxima %3 míst.',
    'rychloregMail' =>
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
@define('AUTOMATICKY_VYBER_TRICKA', false);
@define('VYCHOZI_DOBROVOLNE_VSTUPNE', 0);
@define('VYZADOVANO_COVID_POTVRZENI', false);

//////////////////////////////////////////////
// Staré hodnoty a aliasy pro kompatibilitu //
//////////////////////////////////////////////

// odpočítané tvrdé údaje podle dat
@define('REG_GC', mezi(REG_GC_OD, REG_GC_DO));
@define('REG_AKTIVIT', mezi(REG_AKTIVIT_OD, REG_AKTIVIT_DO));
@define('GC_BEZI', mezi(GC_BEZI_OD, GC_BEZI_DO)); // jestli gamecon aktivně běží (zakázání online registrací ubytování aj.) - do budoucna se vyvarovat a používat speciální konstanty per vlastnost

@define('ARCHIV_OD', 2009);           //rok, od kterého se vedou (nabízejí) archivy (aktivit atp.)
@define('ID_PRAVO_PRIHLASEN', ZIDLE_PRIHLASEN); // fixme zůstává kvůli uložení práva v session
@define('ID_PRAVO_PRITOMEN', ZIDLE_PRITOMEN);  // fixme zůstává kvůli uložení práva v session

@define('ODHLASENI_POKUTA_KONTROLA', po(ROK . '-07-18 00:00:01')); // jestli se má kontrolovat pozdní odhlášní z aktivit
@define('ODHLASENI_POKUTA1_H', 24); // kolik hodin před aktivitou se začne uplatňovat pokuta 1

@define('DEN_PRVNI_DATE', date('Y-m-d', strtotime(PROGRAM_OD))); // první den v programu ve formátu YYYY-MM-DD
@define('DEN_PRVNI_UBYTOVANI', DEN_PRVNI_DATE); // datum, kterému odpovídá ubytovani_den (tabulka shop_predmety) v hodnotě 0
@define('PROGRAM_ZACATEK', 8); // první hodina programu
@define('PROGRAM_KONEC', 24); // konec programu (tuto hodinu už se nehraje)

@define('SUPERADMINI', [1682, 4032]);

@define('MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM', 20);
@define('MOJE_AKTIVITY_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM', 20);

@define('ADRESAR_WEBU_S_OBRAZKY', __DIR__ . '/../web');

@define('NAZEV_SPOLECNOSTI_GAMECON', 'GameCon z.s.');

$nastaveni = new SystemoveNastaveni();
$nastaveni->zaznamyDoKonstant();

if (defined('BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU')) { // není ještě načtena před SQL migracemi
    // OSTATNÍ FINANČNÍ NASTAVENÍ
    @define('MODRE_TRICKO_ZDARMA_OD', 3 * BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU); // hodnota slevy od které má subjekt nárok na modré tričko

    @define('BONUS_ZA_1H_AKTIVITU', SystemoveNastaveni::spocitejBonusVypravece('BONUS_ZA_1H_AKTIVITU'));
    @define('BONUS_ZA_2H_AKTIVITU', SystemoveNastaveni::spocitejBonusVypravece('BONUS_ZA_2H_AKTIVITU'));
    @define('BONUS_ZA_6H_AZ_7H_AKTIVITU', SystemoveNastaveni::spocitejBonusVypravece('BONUS_ZA_6H_AZ_7H_AKTIVITU'));
    @define('BONUS_ZA_8H_AZ_9H_AKTIVITU', SystemoveNastaveni::spocitejBonusVypravece('BONUS_ZA_8H_AZ_9H_AKTIVITU'));
    @define('BONUS_ZA_10H_AZ_11H_AKTIVITU', SystemoveNastaveni::spocitejBonusVypravece('BONUS_ZA_10H_AZ_11H_AKTIVITU'));
    @define('BONUS_ZA_12H_AZ_13H_AKTIVITU', SystemoveNastaveni::spocitejBonusVypravece('BONUS_ZA_12H_AZ_13H_AKTIVITU'));
}
