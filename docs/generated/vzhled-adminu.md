# Vzhled administrace: klasický a moderní

TL;DR: admin má dvě kostry stránky (`admin/templates/main.xtpl` a `admin/templates/moderni/main.xtpl`) nad stejným obsahem modulů, online prezence (Moje aktivity) má moderní vzhled jako vrstvu stylů nad svou šablonou; volbu drží tabulka `uzivatele_vzhled_adminu`, kdo ji nemá, dostane klasický vzhled a dialog s výběrem. Moderní vzhled má tmavé barvy (volba v prohlížeči), panel, který se na výšku nevejde, zhustí, a schovaný panel nechá s myší jako úzký s ikonami; kolik kdo který vzhled používá, zapisuje `uzivatele_vzhled_adminu_pouziti` pro dva reporty.

## Vstupní body

- `admin/index.php` — zpracuje POST `vzhledAdminu`, vybere šablonu (`VzhledAdminuEnum::getSablona()`), nastaví `$vzhled` a `$vzhledAdminuHtml`, které používají i moduly (online prezence).
- `model/Admin/VzhledAdminu.php` — čtení a zápis volby (načtená se pamatuje jen v rámci požadavku), odložení výběru v session (`isNabidnoutVyber()`, `zpracujVolbu()`).
- `model/Admin/VzhledAdminuEnum.php` — `klasicky` / `moderni`, název a šablona.
- `model/Admin/VzhledAdminuHtml.php` — vykreslí `admin/templates/vyber-vzhledu.xtpl` (dialog při první návštěvě, `getVyberHtml()`) a `admin/templates/prepinac-vzhledu.xtpl` (přepínač v patičce, `getPrepinacHtml()`) pro oba vzhledy adminu i pro online prezenci; styly v `admin/files/design/vzhled_adminu.css`.
- `admin/templates/moderni/*.xtpl`, `admin/files/design/moderni.css`, `admin/files/moderni.js` — moderní vzhled; `admin/files/barvy_adminu.js` — světlé a tmavé barvy.
- `VzhledAdminu::zaznamenejZobrazeniStranky()` (volá `admin/index.php`), reporty `admin/scripts/zvlastni/reporty/vzhled-adminu-uzivatele.php` a `vzhled-adminu-dny.php` — jak se vzhledy ujaly.
- `model/Aktivita/OnlinePrezence/OnlinePrezenceHtml.php` (parametry `vzhled`, `vzhledAdminuHtml`, `nazevStranky`), `admin/files/design/online-prezence-moderni.css` — online prezence.
- Testy: `tests/Admin/VzhledAdminuTest.php` (i záznam používání), `tests/Admin/VzhledAdminuHtmlTest.php`, `tests/Aktivity/OnlinePrezenceHtmlVzhledTest.php`, ikony hlavního menu v `tests/Admin/AdminMenuIkonyTest.php`.

## Pravidla

- (záměr) Oba vzhledy zůstávají, uživatel si vybírá; klasický se nemá měnit. Ověřeno porovnáním screenshotů: kromě přepínače v patičce je pixelově stejný.
- Volba patří účtu, ne zařízení ani relaci. „Rozhodnu se později“ (i Esc v dialogu) jen nastaví příznak v session, po dalším přihlášení se dialog ukáže znovu. Příznak platí pro celou session, ne pro jednoho uživatele: odkládá ten, kdo sedí u zařízení, a po „Přihlásit se jako“ (session zůstane) je to pořád on. Dialog by ho jinak nutil zvolit vzhled za druhého uživatele a volba by se uložila do jeho účtu.
- Dialog výběru a přepínač dostane jen ten, kdo smí otevřenou stránku (i jen díky právu na podstránku, to položku hlavního menu nezobrazí) nebo aspoň jednu položku hlavního menu. Účastník přihlášený na webu, který otevře odkaz do adminu a dostane 403, je nevidí.
- Neznámá hodnota v DB (třeba z novější verze) se bere jako nezvolená.
- Tabulka má cizí klíč na `uzivatele_hodnoty`, takže `UzivatelSlucovani` volbu převede (hledá odkazující tabulky podle FK) a smazání účtu ji smaže.
- Moderní hlavní menu kreslí ikonu z hlavičky `ikona:` modulu, klasické dál své PNG. Viz [admin-menu-moduly](admin-menu-moduly.md).
- Přihlašovací stránka a stránky z `admin/scripts/zvlastni/` (program, tabule) i moduly s `$BEZ_DEKORACE` kostru adminu nemají, vzhled se jich netýká. Výjimka je online prezence (Moje aktivity, její detail `?id=` a `zvlastni/online-prezence.php`).

## Online prezence (Moje aktivity)

- Nemá rám adminu a pro vypravěče je často jedinou stránkou adminu, proto si přepínač a dialog výběru vykresluje sama (`OnlinePrezenceHtml` s `vzhledAdminuHtml`).
- Moderní vzhled je jen třída `vzhled-moderni` na `<body>`, horní lišta (blok `moderniLista`) a `online-prezence-moderni.css` načtený za ostatními styly; značky, ID a třídy pro skripty online prezence zůstávají stejné jako v klasickém. Opakované tlačítko „do Adminu“ v kartách aktivit moderní vzhled schová, je v liště.
- Hlášky (`chyba()`, `oznameni()`) si vypisuje sama (`Chyba::vyzvedniHtml()` v `OnlinePrezenceHtml`). Jejich styly (klasické, moderní i tmavé) jsou v `admin/files/design/hlasky.css`, který načítají obě kostry adminu i online prezence; `styl.css` ani `moderni.css` online prezence nenačítá. Vlastní má každá stránka jen umístění: online prezence `z-index` nad okny Bootstrapu (`online-prezence.css`) a posun pod lištu (`online-prezence-moderni.css`), moderní admin posun pod lištu v `moderni.css`. Dřív je nevypisovala a nevyzvednutá hláška pak vyskočila až na jiné stránce adminu.
- Nadpis horní lišty je v Moje aktivity název modulu z jeho hlavičky (`$nazevSekce` z `admin/index.php`), stejně jako v liště moderního adminu. Online prezence z Prezence (`admin/scripts/zvlastni/online-prezence.php`) je zvláštní stránka bez modulu, `admin/index.php` ji spustí dřív, než `$nazevSekce` zná, a má výchozí nadpis „Online prezence“.
- Lokální QA: testovací režim `moje-aktivity?test=1` jde jen s `TESTING` a `TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN` (např. v gitignorovaném `nastaveni/nastaveni-local.php`). Po zaškrtnutí „dorazil“ se řádek u zamčené aktivity zamkne, test proto hledá řádek podle ID, ne „první povolené zaškrtávátko“.

## Jak moderni.css přebarvuje obsah

- Načítá se za `styl.css` a všechna pravidla začínají `:where(.vzhled-moderni)`: mají stejnou specificitu jako pravidlo ze `styl.css`, které mění, a vyhrávají jen pořadím. Proto má pravidlo v `moderni.css` stejný selektor jako to přebíjené (např. `table form input[type="submit"]`, `#submenu .adm_submenu_item a`).
- Díky tomu dál vyhrávají vlastní `<style>` modulů, vložené do stránky později (stejná specificita, pozdější pořadí). Moderní kostra proto nese stejné třídy jako klasická (`.obal`, `.sloupL`, `.sloupR`, `.main`, `#submenu`, `.uzivatel`, `.operator`): moduly je používají, např. detail aktivity v Moje aktivity skrývá `.sloupL`.
- Obsah (`.sloupR`) je široký nejvýš `--m-sirka-obsahu` (1600 px, tolik má na Full HD), na větším monitoru se vystředí vedle panelu. Klasický vzhled má celou stránku nejvýš 1200 px.
- Pod 1024 px je `.sloupL` vysouvací panel (z-index 1000) a nahoře je lišta; lišta při posunu dolů ustoupí, aby nezakrývala přilepené hlavičky tabulek (`th { position: sticky; top: 0 }` na přehledu aktivit).
- (záměr) Na širší obrazovce si panel uživatel schová tlačítkem v jeho hlavičce (kvůli notebooku); připínáček ho vrátí. Volba patří prohlížeči (localStorage `adminSchovaneMenu`), ne účtu, ať na velkém monitoru menu zůstane. Třídu `moderni-menu-schovane` na `<html>` nastaví skript v hlavičce `moderni/main.xtpl` ještě před vykreslením (jinak by menu probliklo) a JS i CSS se ptají „je menu vysouvací“, ne jen na šířku. Pravidla vysunutého panelu jsou proto v `moderni.css` mimo `@media` a podmíněné je jen schování panelu.
- (záměr) S myší (`(min-width: 1024px) and (hover: hover) and (pointer: fine)`) schované menu nezmizí: zůstane úzký panel s ikonami záložek, který se najetím myší rozbalí přes obsah (přání pořadatele). Vysouvací je menu jen pod 1024 px a schované na dotyku (iPad na infopultu), tam najetí myší není. Podmínku má `moderni.js` (`jePanelUzky()`, `jeMenuVysouvaci()`) i `moderni.css`: pravidla vysouvacího schovaného menu jsou v `@media not all and (…)` s touž podmínkou obráceně, úzký panel proto staví na připnutém panelu a nic z vysouvacího nepřebíjí.
- Tabulky širší než obsah obalí `moderni.js` do posuvného bloku se stínem u okraje (ne ty, které už jsou v `.responzivni-tabulka` nebo v jiné tabulce). Při tisku blok nic neořezává, tabulka se vytiskne celá.

## Úzký panel (schované menu s myší)

- Je to celý panel oříznutý (`clip-path`) na šířku `--m-sirka-uzkeho-panelu` (66 px, ikony menu jsou uprostřed). Rozbalení mění jen ořez: nic se nepřeskládá ani nepřepočítává a ikony zůstanou na místě pod myší. Proto nejde část panelu ve sbaleném stavu zmenšit nebo schovat (`display: none`), posunuly by se ikony pod ní. Ořez schová i posuvník panelu (je u pravého okraje) a omezí na viditelný proužek i najetí myší.
- Rozbalený panel překryje obsah, který se neposune: v řádku flexu zabere jen šířku úzkého panelu (záporný `margin-right`). Díky tomu, že panel zůstává v toku, nevznikne mezera, když ho modul schová (detail aktivity v Moje aktivity skrývá `.sloupL`).
- Rozbalí ho najetí myší (po 0,2 s, ať ho neotevře myš, která jen přejede) nebo fokus z klávesnice (`:has(:focus-visible)`, ne `:focus-within`: odkaz otevřený myší do nové karty nebo po návratu zpět by panel držel rozbalený). Prohlížeč bez `:has()` dostane `:focus-within`. Každý spouštěč má vlastní pravidlo, prohlížeč, který nezná `:has()`, by jinak zahodil i najetí myší. Alt+U (omnibox.js) i hlášky „pole vlevo“ dají fokus do pole hledání, panel se tím rozbalí sám.
- Esc rozbalený panel sbalí: fokus z něj přejde na obsah (`#mainContent`; ve sbaleném panelu by pole nebo tlačítko s fokusem nebylo vidět) a myš ho rozbalí, až z něj odjede a vrátí se. Posluchači změny `matchMedia` (ne `resize`) zavřou vysunuté menu i po připojení myši k tabletu, kdy se velikost okna nezmění.
- Stav řídí jediná proměnná `--m-panel-rozbaleny` (0/1), kterou nastaví panel a jeho části zdědí; z ní se počítá `--m-panel-sbaleny`, zpoždění, ořezy (`clip-path`) i průhlednost, a ty se animují spolu s panelem. Trvání animace je `--m-panel-trvani`, při omezení pohybu ho panel nastaví na 0 a části ho zdědí (přepsat `transition-duration` u všech částí by prohrálo se specifičtějšími pravidly). Karta pracovního uživatele se sbalí do dlaždice v levém dolním rohu (hned nad ikonami menu) s fotkou nebo lupou, přihlášený uživatel do ikony; ty ikony jsou `.moderni-ikona-uzkeho-panelu` v šablonách `moderni/uzivatel.xtpl` a `moderni/operator.xtpl`, mimo úzký panel skryté.
- Skryté části karty a přihlášeného uživatele jsou jen průhledné (musí jít fokusem z klávesnice), klik na dlaždici by tak padl na Zrušit výběr, předchozího uživatele nebo Odhlásit pod ní. Sbalené je proto zakryje štít (`::after`), který zmizí, až jsou po rozbalení vidět celé, a při sbalení se vrátí hned.
- Animovat se smí jen to, co se mezi zhuštěným a plným panelem nemění (průhlednost, ořez v procentech nebo podle šířky panelu): zhuštění přepíná skript při načtení a po změně velikosti okna, a co se mění s ním, by se při každém načtení stránky animovalo.
- Po kliknutí na Schovat je myš ještě nad panelem a ten by se hned rozbalil zpátky. `moderni.js` mu proto dá třídu `moderni-panel-nerozbalovat`, dokud z něj myš neodjede. Z klávesnice (Enter, `event.detail === 0`) přejde fokus na připínáček a panel zůstane rozbalený, dokud z něj fokus neodejde.
- Vyskakovací hlášky jsou tam, kde u připnutého panelu (`hlasky.css`), horní lišta tu není.
- Připnutý panel ani vysouvací menu (telefon, úzké okno, schované na dotyku) se úzkým panelem nezměnily, ověřeno pixelovým porovnáním s `main`. Při porovnání posílej obě verze CSS stejnou cestou (např. obě přes `page.route`): soubor z disku proti podstrčenému dá drobné rozdíly v antialiasingu zaoblených rohů, které nejsou regrese.

## Tmavé barvy

- Jen moderní vzhled (admin i online prezence); přepínač **Barvy** (Světlé / Tmavé / Podle zařízení) je blok `barvy` šablony `prepinac-vzhledu.xtpl`, který `VzhledAdminuHtml` parsuje jen u moderního. Tlačítka nejsou `submit`, formulář vzhledu neodesílají.
- (záměr) Volba patří prohlížeči (localStorage `adminBarvy`), ne účtu, stejně jako barvy zařízení: telefon může být tmavý a počítač světlý. Bez volby platí `prefers-color-scheme` a `barvy_adminu.js` sleduje jeho změnu i volbu z jiné záložky.
- `barvy_adminu.js` se načítá v hlavičce bez `defer` a dá na `<html>` třídu `moderni-tmave` ještě před vykreslením (jinak by stránka probliknula světle). CSS se ptá jen na tu třídu, ne na `@media (prefers-color-scheme)`, aby pravidla nebyla dvakrát.
- Tmavé barvy jsou v `@media screen`: vytištěná stránka zůstane světlá.
- Barvy jdou přes proměnné `--m-*` (světlé hodnoty jsou stejné jako dřív natvrdo, světlý vzhled se nezměnil, ověřeno pixelovým porovnáním). `--m-tmava`/`--m-na-tmave` jsou zvýraznění (aktivní položka podmenu, zvolený přepínač), v tmavých barvách se obrátí na světlé. Postranní panel je tmavý v obou.
- Co přes proměnné nejde, dobarvuje oddíl Tmavé barvy na konci `moderni.css` (a `online-prezence-moderni.css`, včetně Bootstrapu 5.1, který tmavé barvy nemá): barvy ze `styl.css`, které světlý moderní vzhled nemění, knihovny (jQuery UI, multiselect, jquery-modal) a vlastní `<style>` modulů. Na ty je selektor o `.moderni-tmave .vzhled-moderni` delší, protože jsou ve stránce až za `moderni.css`.
- Barvy natvrdo v atributu `style` (stav uživatele z `Uzivatel::statusHtml()`, ano/ne ve Slučování, text e-mailu v Upomínkách) přebíjí jen selektor podle `[style*=…]`. Světlé pozadí natvrdo zůstává světlé a dostane tmavé písmo i `--m-odkaz`, jinak by odkazy v něm byly světlé. Barvu písma natvrdo ve `style` (hláška u vlastního znění v Upomínkách) přebije jen `!important`. Nový modul se světlým pozadím bez barvy písma je v tmavých barvách nečitelný: dát mu barvy přes proměnné `--m-*`, nebo s pozadím i barvu písma.
- QR kód plateb zůstává na bílém (čtečka), grafy ve Statistikách taky.

## Kompaktní panel

- (záměr) Na malém monitoru se má celé menu vejít bez posouvání (přání pořadatele s malým monitorem). Nerozhoduje přepínač ani výška obrazovky, ale to, jestli panel přetéká: skript hned za `</aside>` v `moderni/main.xtpl` změří `scrollHeight > clientHeight` a dá na `<html>` třídu `moderni-panel-kompaktni`; po změně velikosti okna to `moderni.js` přeměří (plný panel, pak případně znovu zhustí, bez překreslení mezi tím).
- Pravidla jsou v `@media (min-width: 1024px) and (pointer: fine)`: na dotyk zůstávají položky velké a panel se posouvá.
- Zhuštěný panel: menší mezery a položky menu (28 px), Zrušit výběr jako křížek v rohu karty uživatele, Odhlásit jako ikona vedle jména. Na 1366×657 se panel s pracovním uživatelem a 13 položkami vejde (602 px místo 916).

## Záznam používání

- Tabulka `uzivatele_vzhled_adminu_pouziti`: řádek na uživatele, den a vzhled s počtem zobrazených stránek; `zvoleny` = vzhled si vybral (0 = klasický jen jako výchozí, ještě nevybíral). Kdo vzhled během dne přepne, má ten den dva řádky.
- Počítá `admin/index.php` po modulu, ke kterému má uživatel přístup, tedy i stránky bez rámu (Moje aktivity). Jen otevření stránky (`VzhledAdminu::isZobrazeniStranky()`: GET a `Sec-Fetch-Dest: document`), ne ajax, `fetch()` ani odeslání formuláře (to přesměruje na stránku, která se započítá sama). Přihlášení, 403, 404 a skripty reportů (`zvlastni/reporty`) se nepočítají.
- Barvy (tmavé/světlé) se nezaznamenávají, jsou jen v prohlížeči.
- Reporty v Reporty: **Vzhled administrace: kdo jaký používá** (na uživatele: zvolený vzhled, dny a stránky v kterém, první a poslední den) a **Vzhled administrace: po dnech** (kolik lidí který den v kterém vzhledu).
- Cizí klíč na `uzivatele_hodnoty` jako u volby; při slučování se řádky ze stejného dne nesečtou, zůstane ten uživatele, do kterého se slučuje.

## Gotchas

- Každý blok, který `admin/index.php` parsuje (`all.menuPolozka`, `all.uzivatel.vybrany`, `all.operator.prepnutiUzivatele`, …), musí existovat v **obou** šablonách. XTemplate na neexistující blok volá neexistující metodu a stránka spadne.
- `{FILE}` uvnitř vloženého souboru se nezpracuje (vkládání není rekurzivní) a cesta `./../x.xtpl` se rozbije (`convertIncludeFilePath` maže každé `./`). Proto obě šablony vkládají soubory cestou od kořene adminu (`templates/…`) a přepínač s výběrem se vkládají jako hotové HTML z `VzhledAdminuHtml`.
- `{proměnná}` v inline JS/CSS šablony XTemplate nahradí; píše se `function () {` s mezerou nebo novým řádkem za `{`.
- ID `omniboxUzivateleProPraci` a `zrusitUzivateleProPraci` musí zůstat v obou vzhledech: zkratky alt+U / alt+Z v `admin/files/omnibox.js` a potvrzení zrušení u dlužníka v `infopult.xtpl`.
- Stav uživatele (`Uzivatel::statusHtml()`) má barvy natvrdo pro světlé pozadí, proto je karta pracovního uživatele v tmavém panelu světlá. V tmavých barvách je karta tmavá a tmavší barvy stavu přebarví `moderni.css` podle `[style*=…]`.
- Odkaz na kotvu v kostře adminu (`#mainContent`) musí nést adresu stránky (`{urlStranky}#mainContent`): samotné `#kotva` se vůči `<base href>` otevře na úvodní stránce adminu. `urlStranky` je `REQUEST_URI` s jediným `/` na začátku (`Urls::getCestaNaTomtoWebu()`, případy v `tests/Model/Web/UrlsTest.php`): požadavek na `//jiny.web/x` by jinak dal odkaz na cizí web.
- Skripty v hlavičce (`barvy_adminu.js`) a `moderni.js` musí přežít i Safari do 13 (starší iPady na infopultu): `MediaQueryList` tam nemá `addEventListener`, jen `addListener`. `barvy_adminu.js` proto má záložní `addListener`, `moderni.js` místo změny `MediaQueryList` hlídá `resize`. Prohlížeč bez `<dialog>` dialog výběru neotevře a schová ho `vzhled_adminu.css`, zůstane jen přepínač v patičce.
- Zkratka alt+U v `moderni.js` (vysune menu s výběrem uživatele) jde přes jquery.hotkeys jako v `omnibox.js`: ta poznají klávesu podle kódu (na Macu a iPadu je alt+U mrtvá klávesa a `event.key` je `Dead`) a v polích pro psaní nic nedělají.
- Skripty adminu se načítají s `?version=` z `VerzeSouboru` (`{jsVersions.nazev}`), jinak prohlížeč může dál běžet se starou verzí z cache. Proto `omnibox.js` nemá verzi v názvu: XTemplate v `{jsVersions.…}` tečky ani pomlčky nedovolí.
