TL;DR: Veřejný web běží česky na `/…` a anglicky na `/en/…`. Jazyk požadavku určuje `JazykPozadavku`, texty rozhraní jsou v katalozích `symfony/translations/messages+intl-icu.{cs,en}.yaml` a vykresluje je `t()` v PHP, `{t:klic}` v šablonách a `t()` v Preactu. Admin je jen česky.

Plán celé dvojjazyčnosti a co ještě chybí: [docs/dvojjazycny-web.md](../dvojjazycny-web.md).

## Vstupní body

- `model/Jazyk/Jazyk.php`: enum jazyků (`cs` výchozí bez prefixu, `en` s prefixem `en/`).
- `model/Jazyk/JazykPozadavku.php::zGlobals()`: volá ho `web/index.php` hned po `Uzivatel::zSession()`. Odtrhne prefix z `$_GET['req']`, takže `Url`, `Modul::zUrl`, `Stranka::zUrl` i Symfony cesty v `web/_symfony.php` pracují s cestou bez jazyka.
- `JazykPozadavku::zpracuj()`: cookie `gc_jazyk`, `uzivatele_hodnoty.jazyk`, přesměrování.
- `model/Jazyk/AktualniJazyk.php`: jazyk právě vykreslovaného požadavku. Mimo web (admin, cron, CLI) není nastavený, platí čeština.
- `model/Jazyk/Preklad.php` a globální `t()` / `urlWebu()` v `model/funkce/web-funkce.php`.
- `model/XTemplate/XTemplate.php::convertVariables()`: `{t:menu.program}` se zkompiluje na `<?=t('menu.program')?>`, šablona v cache je tedy jedna pro všechny jazyky.
- Preact: `ui/src/i18n/` (`t()`, slovníky `cs.ts` a `en.ts`), jazyk z `GAMECON_KONSTANTY.JAZYK` (`Program::gameconKonstanty()`).

## Pravidla

- Jazyk se pozná jen z prefixu URL. Podle jazyka prohlížeče se nepřesměrovává `(záměr)`.
- Volba se ukládá jen výslovně: návštěvou adresy s prefixem nebo přepínačem `?jazyk=cs|en`. Česká adresa bez přepínače volbu nemění, mohla přijít z natvrdo psaného odkazu.
- Kdo má zvolenou angličtinu (cookie, jinak uložená u přihlášeného) a přijde na českou adresu, dostane 302 na anglickou. Jen u GET, ne u AJAXu a `api/…`.
- Přepínač jazyka vede vždy přes `?jazyk=`. Bez parametru by návrat do češtiny přebila cookie s angličtinou.
- `uzivatele_hodnoty.jazyk` je jazyk e-mailů, které jdou mimo request uživatele. Nový účet dostane jazyk, ve kterém se registroval (`registrujUprav()` ho doplňuje až po validaci, protože validace pouští jen povolená pole; `rychloregistrace()` taky).
- Chybějící anglický text spadne na český (`setFallbackLocales`), chybějící klíč vrátí sám sebe.
- Klíč překladu má vždy aspoň jednu tečku (`menu.program`). `{t:x}` bez tečky zůstane v šabloně doslova, kvůli objektům v inline JS.

## Odkazy a soubory pod `/en/`

- `<base href>` je `urlWebu()`, takže relativní odkazy (`href="program"`, i v obsahu stránek z CMS) zůstávají v jazyce stránky.
- Absolutní odkazy skládej přes `urlWebu('cesta')`, ne `URL_WEBU . '/cesta'`. Výjimka jsou odkazy, které se tisknou nebo sdílí jako kanonické (QR kódy, `Novinka::urlNaWebu()`); ty zůstávají české a návštěvníka s volbou angličtiny přesměruje cookie.
- Relativní odkazy na soubory (`soubory/…`) vedou pod `/en/`. Obsluhuje je pravidlo ve `web/.htaccess`: adresář `en/` neexistuje, Apache proto dá do `REQUEST_FILENAME` jen `…/web/en` (zbytek cesty je PATH_INFO) a pravidlo skládá cestu ze zachycené části URL.
- Preact volá API v jazyce stránky (`BASE_PATH_API` = `…/en/api/`), aby texty z API seděly k rozhraní.

## Katalogy

- PHP: `symfony/translations/messages+intl-icu.{cs,en}.yaml`, formát zpráv ICU (`{pocet, plural, one {…} few {…} other {…}}`, `{pohlavi, select, f {…} other {…}}`). Legacy si skládá vlastní `Translator`, aby nebootoval Symfony kernel (veřejný web ho jinak bootuje jen pro pár cest). Čte tytéž soubory jako Symfony `framework.translator` z PR #1058.
- `tests/Model/Jazyk/PrekladyKatalogTest.php` hlídá: stejné klíče ve všech jazycích, každý `t('…')` a `{t:…}` v kódu je v katalogu, v katalogu nic nepoužitého, stejné parametry a platné ICU. Klíč skládaný za běhu (`t('menu.' . $x)`) test nenajde, proto ho nepiš.
- Preact: `ui/src/i18n/en.ts` je typovaný `Slovník`, takže chybějící klíč zastaví `tsc` (a tedy build). Parametry `{nazev}`.

## Data, typografie a klíče

- `DateTimeCz::format()` vrací dny česky vždy, i na anglické stránce. Jeho výstup je i klíč (slugy dnů programu v `web/moduly/program.php::nazevStranky()`, párování dnů v importu aktivit), proto se nepřepíná. Text pro člověka skládá `model/Cas/DatumVJazyce.php` přes `IntlDateFormatter` (`denVTydnu()`, `denAMesic()`, `denAMesicSCasem()`); na něm stojí i `datum3()` a `datum4()`.
- `relativni()` a `relativniVBudoucnu()` zůstávají česky, používá je jen admin a cron.
- `markdown($text, $jazyk)`: `$jazyk` je jazyk **textu**, ne stránky (česká záloha na anglické stránce dostane české uvozovky). Podle něj `Smartyp` volí typografii a cache markdownu má pro angličtinu vlastní skupinu (`kvs('markdown-en', …)`), protože výstup stejného textu se liší.
- Preact: klíče skupin v tabulce programu i slugy dnů v URL zůstávají česky (`formátujDenVTýdnu`). Zobrazuje se přes `ui/src/i18n/datum.ts` (`popisekSkupiny()`, `názevDneVTýdnu()`, `formátujDatumProČtenáře()`).

## Gotchas

- Slugy dnů programu (`/program/patek`) jsou klíče a musí zůstat česky i v angličtině. `nazevStranky()` je skládá z `format('l')`, viz výše.
- Logika legacy e-shopu stojí na českých názvech předmětů (`Shop::bezDne()`, `'Spacák'`, `LIKE 'Snídaně%'`). Nevadí to, dokud se názvy jen zobrazují; anglické názvy předmětů patří až do nového e-shopu z PR #1058.
- Chybová stránka `model/Vyjimkovac/vyjimkovac-omluva.xtpl` má `lang="cs"` natvrdo; přepnout až spolu s překladem jejího textu.
- Testy webu přes HTTP (`tests/web/AnglickyWebTest.php`, `StrankyWebuTest` projde všechny moduly i pod `/en/`) běží proti Apachi v kontejneru `web` s testovací DB přes cookie.
