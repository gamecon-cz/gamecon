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

$puvodni = error_reporting();                                                                                     // vymaskování notice, aby bylo možné "přetížit" konstanty dříve includnutými
error_reporting($puvodni ^ E_NOTICE);

if (!defined('ROCNIK')) define('ROCNIK', (int)date('Y'));

require_once __DIR__ . '/nastaveni-izolovane.php';

////////////////////////
// Nastavení ovládatelné z adminu //
////////////////////////

global $systemoveNastaveni;
$systemoveNastaveni = SystemoveNastaveni::zGlobals();
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
if (!defined('GC_BEZI_OD')) define('GC_BEZI_OD', $systemoveNastaveni->gcBeziOd()->formatDb());                    // začátek GameConu (přepnutí stránek do režimu "úpravy na jen na infopultu")
// 2022-07-24 21:00:00
if (!defined('GC_BEZI_DO')) define('GC_BEZI_DO', $systemoveNastaveni->gcBeziDo()->formatDb());                    // konec GameCou (přepnutí stránek do režimu "gc skončil, úpravy nemožné")

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
if (!defined('PRVNI_VLNA_KDY')) define('PRVNI_VLNA_KDY', $systemoveNastaveni->prvniVlnaKdy(ROCNIK)->formatDb());  // spuštění možnosti registrace na aktivity, pokud jsou aktivované 1. vlna
if (!defined('DRUHA_VLNA_KDY')) define('DRUHA_VLNA_KDY', $systemoveNastaveni->druhaVlnaKdy(ROCNIK)->formatDb());
if (!defined('TRETI_VLNA_KDY')) define('TRETI_VLNA_KDY', $systemoveNastaveni->tretiVlnaKdy(ROCNIK)->formatDb());

// 2022-07-13 00:00:00
if (!defined('PROGRAM_OD')) define('PROGRAM_OD', DateTimeGamecon::zacatekProgramu(ROCNIK)->formatDb());           // první den programu
if (!defined('PROGRAM_DO')) define('PROGRAM_DO', GC_BEZI_DO);                                                     // poslední den programu
if (!defined('PROGRAM_VIDITELNY')) define('PROGRAM_VIDITELNY', po(REG_GC_OD));                                    // jestli jsou viditelné linky na program
if (!defined('CENY_VIDITELNE')) define('CENY_VIDITELNE', PROGRAM_VIDITELNY && pred(GC_BEZI_DO));                  // jestli jsou viditelné ceny aktivit
if (!defined('FINANCE_VIDITELNE')) define('FINANCE_VIDITELNE', true);                                             // jestli jsou public viditelné finance

///////////////////
// Role a práva //
///////////////////

error_reporting($puvodni); // zrušení maskování notice
unset($puvodni);

require_once __DIR__ . '/nastaveni-role.php';

////////////////////////
// Finanční nastavení //
////////////////////////

if (!defined('UCET_CZ')) define('UCET_CZ', '2800035147/2010');    // číslo účtu pro platby v CZK - v statických stránkách není
if (!defined('IBAN')) define('IBAN', 'CZ2820100000002800035147'); // mezinárodní číslo účtu
if (!defined('BIC_SWIFT')) define('BIC_SWIFT', 'FIOBCZPPXXX');    // mezinárodní ID (něco jako mezinárodní VS)
//if (!defined('FIO_TOKEN')) define('FIO_TOKEN', ''); // tajné - musí nastavit lokální soubor definic

/////////////////////////
// Řetězcové konstanty //
/////////////////////////

$GLOBALS['HLASKY']       = require __DIR__ . '/hlasky/nastaveni-hlasky.php';
$GLOBALS['HLASKY_SUBST'] = require __DIR__ . '/hlasky/nastaveni-hlasky-subst.php';

/////////////////////////
// Nastavení přihlášek //
/////////////////////////
if (!defined('VYCHOZI_DOBROVOLNE_VSTUPNE')) define('VYCHOZI_DOBROVOLNE_VSTUPNE', 0);
if (!defined('VYZADOVANO_COVID_POTVRZENI')) define('VYZADOVANO_COVID_POTVRZENI', false);

//////////////////////////////////////////////
// Staré hodnoty a aliasy pro kompatibilitu //
//////////////////////////////////////////////

// odpočítané tvrdé údaje podle dat

if (!defined('ARCHIV_OD')) define('ARCHIV_OD', 2009);           // rok, od kterého se vedou (nabízejí) archivy (aktivit atp.)

if (!defined('ODHLASENI_POKUTA_KONTROLA')) define('ODHLASENI_POKUTA_KONTROLA', true); // jestli se má kontrolovat pozdní odhlášní z aktivit
if (!defined('ODHLASENI_POKUTA1_H')) define('ODHLASENI_POKUTA1_H', 24);               // kolik hodin před aktivitou se začne uplatňovat pokuta 1

if (!defined('DEN_PRVNI_DATE')) define('DEN_PRVNI_DATE', date('Y-m-d', strtotime(PROGRAM_OD)));     // první den v programu ve formátu YYYY-MM-DD
if (!defined('DEN_PRVNI_UBYTOVANI')) define('DEN_PRVNI_UBYTOVANI', DEN_PRVNI_DATE);                 // datum, kterému odpovídá ubytovani_den (tabulka shop_predmety) v hodnotě 0
if (!defined('PROGRAM_ZACATEK')) define('PROGRAM_ZACATEK', 8);                                      // první hodina programu
if (!defined('PROGRAM_KONEC')) define('PROGRAM_KONEC', 6);                                          // konec programu (tuto hodinu už se nehraje). Používejte pouze hodnoty 1-24. Pokud je menší než PROGRAM_ZACATEK, znamená to, že končí následujícího dne.
if (PROGRAM_KONEC === 0) throw new Exception('Konstanta PROGRAM_KONEC bere pouze hodnoty 1 až 24'); // kontrola kdyby někdo zadal konec jako 0 (rozbíjí to vytváření aktivit, noční aktivity aj.)

if (!defined('MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM')) define('MOJE_AKTIVITY_EDITOVATELNE_X_MINUT_PRED_JEJICH_ZACATKEM', 20);
if (!defined('MOJE_AKTIVITY_PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM')) define('MOJE_AKTIVITY_PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_JEJICH_ZACATKEM', 10);

if (!defined('PRODEJ_JIDLA_POZASTAVEN')) define('PRODEJ_JIDLA_POZASTAVEN', false);

if (!defined('SUPERADMINI')) define('SUPERADMINI', [4032 /* Jaroslav "Kostřivec" Týc */,
                                                                 1112 /* Lenka "Cemi" Zavadilová */,
                                                                 5475 /* Michal "Gerete" Bezděk */,
                                                                 5222 /* Jindřich "adrijaned" Dítě */,
                                                                 4275 /* Roman "Sciator" Wehmhóner */]);

if (!defined('UBYTOVANI_POUZE_SPACAKY')) define('UBYTOVANI_POUZE_SPACAKY', false);

if (!defined('VAROVAT_O_ZASEKLE_SYNCHRONIZACI_PLATEB')) define('VAROVAT_O_ZASEKLE_SYNCHRONIZACI_PLATEB', true);

if (!defined('CACHOVAT_SQL_DOTAZY')) define('CACHOVAT_SQL_DOTAZY', true);
if (!defined('CACHOVAT_API_ODPOVEDI')) define('CACHOVAT_API_ODPOVEDI', true);
