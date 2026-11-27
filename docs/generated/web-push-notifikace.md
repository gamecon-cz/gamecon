# web-push-notifikace

TL;DR: Web push notifikace bez mobilní aplikace (Trello 1019, proof of concept): přihlášený uživatel si je v Nastavení zapne na svém zařízení, organizátor s právem mu z adminu pošle notifikaci, která dorazí i na zamčený telefon. Subscription patří k trvalému přihlášení zařízení a zaniká s ním. Nasazení a ruční testování: [docs/push-notifikace.md](../push-notifikace.md).

## Vstupní body v kódu
- Fasáda a zapnutí: `model/PushNotifikace/PushNotifikace.php` (`isZapnuto()`, `zNastaveni()`, `vytvorHttpKlienta()`).
- Úložiště: `PushSubscriptions` (tabulka `uzivatele_push_subscriptions`), migrace `migrace/2026-11-27-183902_push-notifikace.php`.
- Odeslání: `PushOdesilac::posliUzivateli()` (o adminu neví, zavolá ho i cron), zpráva `ZpravaNotifikace`, druhy `TypNotifikace`, výsledek `VysledekOdeslani` / `StavOdeslani`.
- Zařízení uživatele: `SpravaZarizeni` + `KontextZarizeni`, API `web/moduly/api/pushSubscription.php` (`POST /api/pushSubscription`, akce `stav` / `zapnout` / `vypnout` / `zmena`).
- Validace: `OvereniSubscription` (endpoint, klíče), `OdkazNotifikace` (odkaz v notifikaci).
- Prohlížeč: service worker `web/sw.js`, skript `web/soubory/push-notifikace.js`, manifest `web/manifest.webmanifest`, ikony `web/soubory/push/`. HTML do hlavičky a do Nastavení dává `PushNaWebu` (volá `web/index.php` a `Registrace::zobrazHtml()`).
- Admin: `admin/scripts/modules/web/push-notifikace.php` a `push-notifikace-test.php`, logika `StrankaAdminu` + `OdeslaniZAdminu`, šablona `model/PushNotifikace/templates/admin.xtpl`, testovací sada `TestovaciNotifikace`.
- Záznam a limit: `ZaznamOdeslani` (tabulka `push_notifikace_odeslani`, soubor `LOGY/push-notifikace-RRRR-MM.log`).
- VAPID klíče: `VapidKlice`, konstanty `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY`, příkaz `app:push:generate-vapid-keys`.
- Testy: `tests/PushNotifikace/` (falešná push služba `FalesnyHttpKlient`), přes Apache `tests/web/PushNotifikaceTest.php`, v prohlížeči `ui/src/pushNotifikace/*.test.ts`.

## Pravidla
- (záměr) **Zapnout si notifikace smí každý přihlášený uživatel**, žádné právo k tomu není. Právo `Pravo::ADMINISTRACE_PUSH_NOTIFIKACE` (119) hlídá jen odesílání z adminu; migrace ho dala roli Dev.
- (záměr) **Přepínač** `PUSH_NOTIFIKACE_ZAPNUTE` v Nastavení (skupina Notifikace), výchozí vypnuto. Vypnuto = web o funkci navenek neví (žádné ovládání, manifest ani skript, API odpoví 404) a nic se neodešle. Zapnuto = vidí ji každý přihlášený. Režim „jen pro pár lidí“ na ostré není, zkouší se na preview a betě.
- Bez VAPID klíčů prostředí je funkce vypnutá, ať je přepínač jakkoli (`PushNotifikace::isZapnuto()`). Archivy klíče nemají.
- (záměr) **Subscription patří k trvalému přihlášení zařízení** (`id_trvaleho_prihlaseni`, `ON DELETE CASCADE`). Co maže řádek `uzivatele_trvala_prihlaseni`, ukončí i notifikace, bez JavaScriptu na zařízení: odhlášení, „Odhlásit ostatní zařízení“, změna a obnova hesla, sloučení účtů (`TrvalePrihlaseni::zrusVsude()` před převodem tabulek), vypršení po roce (i dřív, než řádek smaže úklid), zneužitá cookie. Viz [prihlaseni-na-vice-zarizenich](prihlaseni-na-vice-zarizenich.md).
- Bez „Trvale přihlásit“ notifikace zapnout nejdou (`StavZarizeni::BEZ_TRVALEHO_PRIHLASENI`): jiný záznam o přihlášení zařízení než řádek trvalého přihlášení server nemá.
- **Sloučení účtů subscriptions nepřevádí, maže je**, stejně jako trvalá přihlášení: zařízení slučovaného účtu by jinak dostávala notifikace výsledného účtu bez jeho hesla.
- **Jiný uživatel na stejném zařízení:** skript na každé stránce přihlášeného pošle serveru endpoint, který prohlížeč nese. Když ho server pro tohoto uživatele a toto přihlášení nezná, řádek smaže (ať je čí chce) a prohlížeč subscription zruší. Nový uživatel si notifikace zapíná sám kliknutím. Mimo Nastavení se ptá nejvýš jednou za 10 minut, zvlášť pro každého uživatele.
- **Přepnutí na uživatele** ([prepnuti-na-uzivatele](prepnuti-na-uzivatele.md)): přepnutý admin nezapne ani nezruší nic. Dvě nezávislé kontroly: `PrepnutiNaUzivatele::isPrepnuto()` a cookie trvalého přihlášení, která po přepnutí patří adminovi, ne účtu (`TrvalePrihlaseni::getIdNaTomtoZarizeni()`). Kontrola na stránce mu jeho vlastní subscription nechá.
- **Pojistka prostředí:** řádek nese `vapid_verejny_klic` a `origin` (URL_WEBU), kde vznikl, a `getAktivniUzivatele()` vydá jen řádky tohoto prostředí. Beta a preview běží nad kopií ostré databáze a subscriptions z ostré ani nezkusí. Každé prostředí má navíc vlastní pár klíčů, takže by je push služba stejně odmítla.
- **Identita jen ze session**, nikdy z těla požadavku. CSRF: `omezCsrf()` (hostitel v Refereru u každého POSTu webu), cookie session se SameSite=Lax, a API bere jen `Content-Type: application/json`, který cizí formulář neodešle. Admin `omezCsrf()` nevolá, formulář odeslání si proto původ ověřuje sám (`OdeslaniZAdminu::isPozadavekZAdminu()`).
- **Endpoint píše uživatel a server na něj posílá požadavky**, proto jen `https`, port 443, bez přihlašovacích údajů v adrese a jen hostitel ze seznamu `OvereniSubscription::PUSH_SLUZBY` (FCM, Mozilla, Apple, Windows). Seznam místo zákazu IP adres: jméno mimo seznam nikdy neuvidí DNS serveru. Klient nenásleduje přesměrování a má časový limit. Prohlížeč s jinou push službou dostane „nepodporováno“, odmítnutý hostitel je v logu.
- **Odkaz v notifikaci** je jen cesta na webu (`program/muj`), kontroluje ji server (`OdkazNotifikace`) i service worker při kliknutí (`bezpecnaAdresa()`); cokoli jiného otevře úvodní stránku.
- **Log nenese endpoint, klíče ani obsah**: z endpointu jen jméno push služby, důvod chyby je vlastní text, ne zpráva výjimky klienta (ta nese celou adresu).
- **Limit odesílání z adminu:** 30 notifikací na odesílatele a 10 na příjemce za 10 minut (`ZaznamOdeslani`).
- **Vypínač hlídá i odesílač:** `PushNotifikace::getOdesilac()` při vypnutých notifikacích nic nevydá, takže platí i pro cron a frontu, ne jen pro formulář v adminu.
- (záměr) **Testovací notifikace mají v nadpisu „(test)“**, aby je skutečný účastník nevzal vážně (`TestovaciNotifikace::OZNACENI`).
- „Předáno push službě“ (HTTP 2xx) není „zobrazeno“: jestli zařízení notifikaci ukázalo, server neví. HTTP 404 a 410 subscription smažou, jiná chyba ji nechá a zvedne `pocet_chyb_v_rade`.

## Gotchas
- **Service worker je statický soubor v kořeni webu**, aby jeho scope byl celý web: ostrá a preview `/sw.js`, lokálně `/web/sw.js`. Adresy si odvozuje ze svého scope. Není v UI bundlu, změna v něm ani ve skriptu stránky bundly nemění.
- **iOS:** push funguje jen z webu přidaného na plochu (iOS 16.4+), Safari v prohlížeči `PushManager` nemá. Web na ploše má vlastní cookies, uživatel se v něm přihlašuje znovu. Manifest proto dostane i nepřihlášený.
- **Preview za bránou:** manifest se stahuje s `crossorigin="use-credentials"`, jinak by ho brána nevydala. Service worker i API jdou s cookies. Viz docs/push-notifikace.md.
- **Kolace:** `tests/Db/CollationTest.php` pustí jen `utf8mb4_czech_ci` a `utf8mb4_bin`. Endpoint a klíče jsou base64 a rozlišují velikost písmen, proto `utf8mb4_bin`. Endpoint (až 2048 znaků) se pak nevejde do unikátního klíče, unikátnost a hledání jdou přes generovaný sloupec `endpoint_hash` (celé SHA-256).
- **Mezera: kde má admin vlastní host (ostrá, beta), drží „jiný uživatel na zařízení“ jen stránky webu.** Subscription patří k trvalému přihlášení **webu**. Kdo se na zařízení přihlásí jen do adminu (vlastní host, vlastní cookie, cookie webu nevidí) a web neotevře, tomu notifikace předchozího uživatele chodí dál, dokud nenačte stránku webu. Stejně tak odhlášení v adminu neruší trvalé přihlášení webu (mezera popsaná v [prihlaseni-na-vice-zarizenich](prihlaseni-na-vice-zarizenich.md)), a tak ani notifikace. Zařízení je v obou případech pořád trvale přihlášené na webu, notifikace tedy odpovídají stavu přihlášení; na sdíleném počítači to přesto překvapí. Lokálně a na preview admin sdílí host s webem a mezera tam není.
- **Service worker se při doručení serveru neptá**, jestli notifikace pořád patří přihlášenému (zavřelo by to mezeru výš): požadavek z pozadí by zařízení bez běžící session přihlásil cookie trvalého přihlášení a vyměnil její token, a odpověď ztracená na špatném signálu by uživatele při další návštěvě odhlásila (stav „zastaralý“). Kdo to bude chtít, potřebuje endpoint, který čte jen session a cookie neuplatní.
- `pushsubscriptionchange` bez `oldSubscription` (Chrome to tak dělává) server o nové subscription neinformuje: stránka ji při další návštěvě zruší jako neznámou a uživatel si notifikace zapne znovu. Starý řádek zmizí po odpovědi 410.
- Trvalé přihlášení nepoužité přes rok řádek ztrácí až s úklidem při něčím dalším přihlášení; `getAktivniUzivatele()` proto stáří kontroluje samo (`TrvalePrihlaseni::PLATNOST_DNU`).
- **Chyba databáze nesmí vynést endpoint ani klíče:** `DbException` nese celý dotaz a Vyjimkovač ji ukládá i s argumenty volání. Dotazy s endpointem a klíči v `PushSubscriptions` ji proto mění na `ChybaPushNotifikace` jen s číslem chyby (bez `previous`, bez closure s parametry v argumentech).
- Endpoint z těla požadavku jde do dotazu jen po kontrole allowlistem (`SpravaZarizeni::getEndpointZPozadavku()`), i u `stav` a `vypnout`: `dbQv()` dává text, který vypadá jako číslo, do SQL bez uvozovek.
- Opětovné přihlášení heslem zakládá nový řádek trvalého přihlášení (`TrvalePrihlaseni::zaloz()` starý smaže), takže notifikace na zařízení skončí a uživatel si je zapne znovu.
- Na ostré má admin vlastní host a vlastní cookie trvalého přihlášení. Subscription se váže k cookie hostu **webu**, kde se zapíná.
- `Subscription` knihovny má výchozí kódování `aesgcm` (zastaralé); `PushOdesilac` zadává `aes128gcm` výslovně.
- Knihovna `minishlink/web-push` 11 hledá HTTP klienta přes `php-http/discovery` (composer plugin, v `allow-plugins` zakázaný). Klienta a továrny proto vždy předáváme (`GuzzleHttp\Client`, `HttpFactory`). Recept Symfony Flex pro `php-http/discovery` založil kořenový `config/packages/http_discovery.yaml`, který projekt nepoužívá (Symfony má konfiguraci v `symfony/config`) a je smazaný; záznam v `symfony.lock` zůstává, aby ho Flex nezaložil znovu.
- Testovací prostředí má místo VAPID klíčů jen hodnoty jejich tvaru (`nastaveni/verejne-nastaveni-tests.php`): webové testy tak funkci zapnou, ale podepsat se jimi nic nedá. Testy odesílání si pár generují.
- Limit se kontroluje před odesláním a zapisuje po něm: dva souběžné požadavky ho mohou o jednu notifikaci překročit. Odesílá přihlášený organizátor s právem, proto to stačí.

## Otevřené otázky
- (nejisté) Doručení na skutečná zařízení, hlavně zamčený telefon, úsporný režim a web na ploše v iOS za bránou preview, je ověřené jen ručními testy z docs/push-notifikace.md, ne automaticky.
- Zavřít mezeru s vlastním hostem adminu (viz Gotchas): endpoint jen nad session pro service worker, nebo odhlášení, které projde i druhým hostem.
- Automatické obnovení notifikací po opětovném přihlášení téhož uživatele na témže zařízení (dnes klikne znovu).
- Hromadné a plánované odesílání bude chtít frontu a souběžné požadavky (`WebPush::flushPooled()` potřebuje `php-http/guzzle7-adapter`).
- Preference uživatele podle `TypNotifikace` a historie odeslaných notifikací (dnes jen počty v `push_notifikace_odeslani`, 90 dní).
- Ikona `badge` pro stavový řádek Androidu a vlastní ikony místo těch vygenerovaných z loga.
