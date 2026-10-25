# Vzhled administrace: klasický a moderní

TL;DR: admin má dvě kostry stránky (`admin/templates/main.xtpl` a `admin/templates/moderni/main.xtpl`) nad stejným obsahem modulů; volbu drží tabulka `uzivatele_vzhled_adminu`, kdo ji nemá, dostane klasický vzhled a dialog s výběrem.

## Vstupní body

- `admin/index.php` — zpracuje POST `vzhledAdminu`, vybere šablonu (`VzhledAdminuEnum::getSablona()`), na konci parsuje `all.prepinacVzhledu` a `all.vyberVzhledu`.
- `model/Admin/VzhledAdminu.php` — čtení a zápis volby, odložení výběru v session (`isNabidnoutVyber()`, `zpracujVolbu()`).
- `model/Admin/VzhledAdminuEnum.php` — `klasicky` / `moderni`, název a šablona.
- `admin/templates/vyber-vzhledu.xtpl` (dialog při první návštěvě), `admin/templates/prepinac-vzhledu.xtpl` (přepínač v patičce) — sdílené oběma vzhledy, styly v `admin/files/design/vzhled_adminu.css`.
- `admin/templates/moderni/*.xtpl`, `admin/files/design/moderni.css`, `admin/files/moderni.js` — moderní vzhled.
- Testy: `tests/Admin/VzhledAdminuTest.php`, ikony hlavního menu v `tests/Admin/AdminMenuIkonyTest.php`.

## Pravidla

- (záměr) Oba vzhledy zůstávají, uživatel si vybírá; klasický se nemá měnit. Ověřeno porovnáním screenshotů: kromě přepínače v patičce je pixelově stejný.
- Volba patří účtu, ne zařízení ani relaci. „Rozhodnu se později“ (i Esc v dialogu) jen nastaví příznak v session, po dalším přihlášení se dialog ukáže znovu.
- Neznámá hodnota v DB (třeba z novější verze) se bere jako nezvolená.
- Tabulka má cizí klíč na `uzivatele_hodnoty`, takže `UzivatelSlucovani` volbu převede (hledá odkazující tabulky podle FK) a smazání účtu ji smaže.
- Moderní hlavní menu kreslí ikonu z hlavičky `ikona:` modulu, klasické dál své PNG. Viz [admin-menu-moduly](admin-menu-moduly.md).
- Přihlašovací stránka a stránky z `admin/scripts/zvlastni/` (prezence, program, tabule) i moduly s `$BEZ_DEKORACE` kostru adminu nemají, vzhled se jich netýká.

## Jak moderni.css přebarvuje obsah

- Načítá se za `styl.css` a všechna pravidla začínají `:where(.vzhled-moderni)`: mají stejnou specificitu jako pravidlo ze `styl.css`, které mění, a vyhrávají jen pořadím. Proto má pravidlo v `moderni.css` stejný selektor jako to přebíjené (např. `table form input[type="submit"]`, `#submenu .adm_submenu_item a`).
- Díky tomu dál vyhrávají vlastní `<style>` modulů, vložené do stránky později (stejná specificita, pozdější pořadí). Moderní kostra proto nese stejné třídy jako klasická (`.obal`, `.sloupL`, `.sloupR`, `.main`, `#submenu`, `.uzivatel`, `.operator`): moduly je používají, např. detail aktivity v Moje aktivity skrývá `.sloupL`.
- Pod 1024 px je `.sloupL` vysouvací panel (z-index 1000) a nahoře je lišta; lišta při posunu dolů ustoupí, aby nezakrývala přilepené hlavičky tabulek (`th { position: sticky; top: 0 }` na přehledu aktivit).
- Tabulky širší než obsah obalí `moderni.js` do posuvného bloku se stínem u okraje (ne ty, které už jsou v `.responzivni-tabulka` nebo v jiné tabulce).

## Gotchas

- Každý blok, který `admin/index.php` parsuje (`all.menuPolozka`, `all.uzivatel.vybrany`, `all.operator.prepnutiUzivatele`, …), musí existovat v **obou** šablonách. XTemplate na neexistující blok volá neexistující metodu a stránka spadne.
- `{FILE}` uvnitř vloženého souboru se nezpracuje (vkládání není rekurzivní) a cesta `./../x.xtpl` se rozbije (`convertIncludeFilePath` maže každé `./`). Proto obě šablony vkládají soubory cestou od kořene adminu (`templates/…`).
- `{proměnná}` v inline JS/CSS šablony XTemplate nahradí; píše se `function () {` s mezerou nebo novým řádkem za `{`.
- ID `omniboxUzivateleProPraci` a `zrusitUzivateleProPraci` musí zůstat v obou vzhledech: zkratky alt+U / alt+Z v `admin/files/omnibox-1.1.5.js` a potvrzení zrušení u dlužníka v `infopult.xtpl`.
- Stav uživatele (`Uzivatel::statusHtml()`) má barvy natvrdo pro světlé pozadí, proto je karta pracovního uživatele v tmavém panelu světlá.
