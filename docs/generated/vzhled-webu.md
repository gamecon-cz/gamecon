# Vzhled veřejného webu: klasický a moderní

TL;DR: veřejný web má vedle klasického (blackarrow) moderní vzhled jako ukázku (titulka a stránky linií, třeba RPG); volí se v prohlížeči přes `?vzhled=moderni` / `?vzhled=klasicky` a pamatuje v cookie, kdo nevolil, má klasický. Vzor je moderní vzhled adminu z větve `admin-novy-vzhled`.

## Vstupní body

- `web/index.php` zpracuje `?vzhled=…` (nastaví cookie a přesměruje na stejnou adresu bez parametru), vybere kostru `sablony/<adresář vzhledu>/index.xtpl` a předá vzhled modulu (`Modul::setVzhled()`) i menu (`MenuWebu::html($u, $vzhled)`).
- `model/Web/VzhledWebu.php` (cookie `vzhledWebu`, URL přepínače), `model/Web/VzhledWebuEnum.php` (`klasicky` → `sablony/blackarrow`, `moderni` → `sablony/moderni`).
- `web/tridy/modul.php` `Modul::sablona()`: v moderním vzhledu vezme `sablony/moderni/<modul>.xtpl`, když existuje (`maModerniSablonu()`), jinak klasickou šablonu.
- `web/sablony/moderni/` (`index`, `menu`, `titulka`, `aktivity`), `web/soubory/moderni/moderni.css` a `moderni.js`.
- Test: `tests/Web/VzhledWebuTest.php`.

## Pravidla

- (záměr) Zatím jen ukázka na titulce a RPG (stránka linie = modul `aktivity`, takže moderní jsou všechny linie i stránka vypravěče). Další stránky se převádějí po jedné přidáním `sablony/moderni/<modul>.xtpl`, stejně jako kdysi blackarrow.
- (záměr) Klasický vzhled se nemá měnit. Ověřeno porovnáním HTML titulky, RPG, praktických informací a programu: liší se jen odkazem „nový vzhled webu“ v patičce.
- Volba patří prohlížeči, ne účtu: většina návštěvníků není přihlášená. Neznámá hodnota v cookie i v `?vzhled=` znamená klasický vzhled.
- Moderní kostra obaluje i stránky bez moderní šablony (praktické informace, program, přihláška…), ty ukazují klasický obsah. Proto moderní kostra načítá i klasické styly (`perfectcache` z blackarrow) a `moderni.css` až za nimi; moderní třídy mají prefix `mw-`, s klasickými se nepotkají. `<body>` nese `mw-moderni-obsah` nebo `mw-klasicky-obsah` a `mw-modul-<modul>`.
- Stránky obsluhované Symfony (zapomenuté heslo, obnova hesla, `ObnovaHeslaController::strankaResponse()`) si kostru skládají samy z klasické šablony a zůstávají klasické; odkaz „nový vzhled webu“ v jejich patičce přepne a vrátí na titulku.
- Moduly (`web/moduly/titulka.php`, `aktivity.php`) jsou pro oba vzhledy společné. Proměnné jen pro moderní šablonu se přidávají do `assign()` (klasická šablona je ignoruje): `ikonaUrl` u linií, `kontaktLinieAtributy` u vypravěče, `uInicala` v menu.

## Gotchas

- Každý blok, který modul nebo `web/index.php` parsuje, musí existovat v **obou** šablonách (titulka: `linie`, `odpocetPrihlasit`, `karty`, `cisla`, `fotky`, `sponzor`, `partner`; aktivity: `aktivita`, `termin`, `vypravec`, `stitek`, `nahled`, `hlavickaVypravec`, `stranka`, `strankaNavod`; kostra: `extraCss`, `extraJs`, `paticka`, `qrka`, `ribbon`; menu: `typAktivit`, `prihlasen`, `admin`, `mujPrehled`, `neprihlasen`).
- Obrázek předaný šabloně do CSS proměnné (`style="--mw-obrazek: url('…')"`) musí mít absolutní URL: relativní cestu prohlížeč spočítá od `soubory/moderni/moderni.css`, ne od stránky. Náhledy (`Nahled`) absolutní jsou, ikony linií ne, proto `ikonaUrl`.
- `{…}` v inline JS/CSS šablony XTemplate nahradí. Skripty proto žijí v `moderni.js`, v šablonách jsou jen volání bez složených závorek.
- Přihlašovátko (`Aktivita::prihlasovatko()`) je odkaz ve formuláři; tlačítko z něj dělá až `moderni.css` (`.mw-termin-akce form a`, „přihlásit“ červeně podle `input[name=prihlasit]`).
- Detail hry je `<dialog>` (místo checkboxu v klasickém vzhledu), hledání a filtr štítků na stránce linie skládá `moderni.js` z vykreslených karet, bez JS jsou vidět všechny hry.
- Lokálně chybí obrázky linií a aktivit (`web/soubory/systemove/*` je gitignorované); pro QA je lze stáhnout z veřejných adres ostré (`/soubory/systemove/linie/<id>.jpg`, `linie-ikony/<id>.png`, `aktivity/<url_akce>.jpg`).
