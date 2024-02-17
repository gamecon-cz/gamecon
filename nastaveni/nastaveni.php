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

// aktuální rok -- při změně roku viz Překlápění ročníku na Gamecon Gdrive https://docs.google.com/document/d/1H_PM70WjNpQ1Xz65OYfr1BeSTdLrNQSkScMIZEtxWEc/edit
if (!defined('ROCNIK')) define(
    'ROCNIK',
    defined('ROK')
        ? constant('ROK')
        : 2024,
);

require_once __DIR__ . '/nastaveni-izolovane.php';

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
if (!defined('GC_BEZI_OD')) define('GC_BEZI_OD', $systemoveNastaveni->gcBeziOd()->formatDb()); // začátek GameConu (přepnutí stránek do režimu "úpravy na jen na infopultu")
// 2022-07-24 21:00:00
if (!defined('GC_BEZI_DO')) define('GC_BEZI_DO', $systemoveNastaveni->gcBeziDo()->formatDb()); // konec GameCou (přepnutí stránek do režimu "gc skončil, úpravy nemožné")

///////////////////////////
// REGISTRACE NA GAMECON //
///////////////////////////
// 2022-05-12 20:22:00
if (!defined('REG_GC_OD')) define('REG_GC_OD', $systemoveNastaveni->prihlasovaniUcastnikuOd(ROCNIK)->formatDb()); // spuštění možnosti registrace na GameCon
if (!defined('REG_GC_DO')) define('REG_GC_DO', $systemoveNastaveni->prihlasovaniUcastnikuDo(ROCNIK)->formatDb()); // ukončení možnosti registrace na GameCon

////////////////////////////////////////////////////////
// REGISTRACE NA AKTIVITY (PRVNÍ, DRUHÁ A TŘETÍ VLNA) //
////////////////////////////////////////////////////////
// 2022-05-19 20:22:00
if (!defined('PRVNI_VLNA_KDY')) define('PRVNI_VLNA_KDY', $systemoveNastaveni->prvniVlnaKdy(ROCNIK)->formatDb()); // spuštění možnosti registrace na aktivity, pokud jsou aktivované 1. vlna
if (!defined('DRUHA_VLNA_KDY')) define('DRUHA_VLNA_KDY', $systemoveNastaveni->druhaVlnaKdy(ROCNIK)->formatDb());
if (!defined('TRETI_VLNA_KDY')) define('TRETI_VLNA_KDY', $systemoveNastaveni->tretiVlnaKdy(ROCNIK)->formatDb());

// 2022-07-13 00:00:00
if (!defined('PROGRAM_OD')) define('PROGRAM_OD', DateTimeGamecon::zacatekProgramu(ROCNIK)->formatDb()); // první den programu
if (!defined('PROGRAM_DO')) define('PROGRAM_DO', GC_BEZI_DO); // poslední den programu
if (!defined('PROGRAM_VIDITELNY')) define('PROGRAM_VIDITELNY', po(REG_GC_OD)); // jestli jsou viditelné linky na program
if (!defined('CENY_VIDITELNE')) define('CENY_VIDITELNE', PROGRAM_VIDITELNY && pred(GC_BEZI_DO)); // jestli jsou viditelné ceny aktivit
if (!defined('FINANCE_VIDITELNE')) define('FINANCE_VIDITELNE', true); // jestli jsou public viditelné finance

///////////////////
// Role a práva //
///////////////////

error_reporting($puvodni); // zrušení maskování notice
unset($puvodni);

require_once __DIR__ . '/nastaveni-role.php';

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
    'odhlasilPlatil'              => 'Uživatel %1 (ID %2) %3 z GameConu, ale v aktuálním roce (%4) si poslal %5 Kč. Bude vhodné to prověřit popř. smazat platby z připsaných a dát do zůstatku v seznamu uživatelů, aby mu peníze nepropadly',
    'odhlasilMelUbytovani'        => 'Uživatel %1 (ID %2) %3 z GameConu a v aktuálním roce (%4) měl ubytování ve dnech %5. Uvolnilo se tak místo.',
    'uvolneneMisto'               => 'Na aktivitě %1, která se koná v %2 se uvolnilo místo. Tento e-mail dostáváš, protože jsi se přihlásil k sledování uvedené aktivity. Přihlaš se na aktivitu přes <a href="https://gamecon.cz/program">program</a> (pokud nebudeš dost rychlý, je možné že místo sebere někdo jiný).',
    'chybaClenaTymu'              => 'Nepodařilo se přihlásit tým. Při přihlášování uživatele %1 (id %2) se u něj objevila chyba: %3',
    'zapomenuteHeslo'             =>
        'Ahoj,

nechal{a} sis vygenerovat nové heslo na Gamecon.cz. Tvoje přihlašovací jméno je stejné jako e-mail (%1), tvoje nové heslo je %2. Heslo si prosím po přihlášení změň.

S pozdravem Tým organizátorů GameConu',
    'odhlaseniZGc'                => 'Odhlásil{a} ses z GameConu ' . ROCNIK,
    'prihlaseniNaGc'              => 'Přihlásil{a} ses na GameCon ' . ROCNIK,
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

Zdá se, že jsi nedorazil{a} na přihlášenou aktivitu %1

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

if (!defined('ARCHIV_OD')) define('ARCHIV_OD', 2009);           // rok, od kterého se vedou (nabízejí) archivy (aktivit atp.)

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
