# prihlaseni-na-vice-zarizenich

TL;DR: Jak přihlášení drží na víc zařízeních naráz a jak se odhlašuje: session s razítkem přihlášení, trvalé přihlášení („Trvale přihlásit") s řádkem na zařízení, odhlášení tohoto / ostatních / všech zařízení.

## Vstupní body v kódu
- Session a razítko: `model/uzivatel.php` — `Uzivatel::zSession()`, `prihlas()`, `prihlasId()`, konstanty `RAZITKO_PRIHLASENI`, `PREDPONA_RAZITKA`, `ID_TRVALEHO_PRIHLASENI`.
- Výměna razítka na jednom místě: `Uzivatel::zmenRazitkoPrihlaseni()` (odhlášení ostatních, změna hesla v profilu, obnova hesla, sloučení s převzatým heslem).
- Trvalé přihlášení: `model/Uzivatel/TrvalePrihlaseni.php`, tabulka `uzivatele_trvala_prihlaseni` (migrace `2026-10-27-105050_trvale-prihlaseni-na-vice-zarizenich.php`).
- Odhlášení: `Uzivatel::odhlas()` (toto zařízení), `Uzivatel::odhlasNaOstatnichZarizenich()`, `Uzivatel::heslo()` (obnova hesla odkazem), změna hesla v `Uzivatel::registrujUprav()`, sloučení v `UzivatelSlucovani::sluc()`.
- Tlačítko „Odhlásit ostatní zařízení": stránka Nastavení pod tlačítkem Uložit, vlastní formulář (`web/moduly/registrace.php` → `Registrace::zpracujOdhlaseniOstatnichZarizeni()`).
- Testy: `tests/Uzivatel/TrvalePrihlaseniTest.php`.

## Jak to funguje
- **Session** je v každém prohlížeči zvlášť, cookie zmizí zavřením prohlížeče, server ji drží 24 h nečinnosti (`nastaveni/zavadec-zaklad.php`). Web a admin ji sdílí (cookie pro celou doménu).
- **Razítko přihlášení** = `uzivatele_hodnoty.random`. Session si ho uloží při přihlášení, `zSession()` ji zahodí, když nesedí s DB. Kontroluje se jen pod klíčem `Uzivatel::UZIVATEL`, ne u pracovního uživatele v adminu, a jen když razítko začíná předponou `PREDPONA_RAZITKA` (tedy už bylo vyměněno, viz Gotchas).
- **Trvalé přihlášení**: cookie `gcTrvalePrihlaseni` nese token, DB jen jeho SHA-256. Každé přihlášení z cookie token vymění. Předchozí token platí ještě 60 s kvůli souběžným kartám po startu prohlížeče. Nepoužitý řádek vyprší po roce, smaže se při dalším trvalém přihlášení kohokoli. Cookie, která už žádný řádek nenajde, se prohlížeči smaže.
- Cookie je **jen pro host webu** (bez domény), aby token nechodil na betu, preview a archivy. Admin ji proto nevidí; session si pamatuje id řádku (`ID_TRVALEHO_PRIHLASENI`), aby ho zrušilo i odhlášení v adminu.

## Pravidla
- (záměr) „Odhlásit" odhlásí **jen toto zařízení**.
- (záměr) „Odhlásit ostatní zařízení" v Nastavení: ostatní přijdou o session i trvalé přihlášení, **toto zůstane** přihlášené.
- (záměr) **Změna hesla v profilu** odhlásí ostatní zařízení, toto zůstane.
- **Obnova hesla odkazem** odhlásí všechna zařízení, i to, ve kterém se obnovuje (přihlásí se novým heslem).
- **Sloučení účtů**: trvalá přihlášení slučovaného účtu se zruší, nepřevádí se. Převzaté heslo odhlásí všechna zařízení výsledného účtu jako obnova hesla.
- (záměr) Nepoužívané trvalé přihlášení vyprší **po roce**.

## Gotchas
- `random` dřív nesl přímo token trvalého přihlášení (jeden na uživatele, takže přihlášení na dalším zařízení odhlásilo předchozí). Migrace tokeny převedla do tabulky (kromě rychloregistrací, jejich `random` mohl být kód z e-mailu) a `random` všem vyprázdnila.
- **Nasazení nahraje kód dřív, než doběhne migrace.** Proto razítko platí, až když má předponu: do té doby je v `random` starý token nebo prázdno a každá session uživatele platí (i session z doby před nasazením, které razítko nemají). Bez toho by nahrání kódu odhlásilo každého, kdo zrovna klikl. Přihlášení z cookie snese chybějící tabulku (chyba 1146 → nepřihlásí).
- **Pořadí při výměně razítka:** nejdřív smazat trvalá přihlášení, pak vyměnit razítko, a přihlášení z cookie čte razítko stejným dotazem jako svůj řádek (`TrvalePrihlaseni::uplatniCookie()` → `prihlasId(..., $razitko)`). Jinak by přihlášení z cookie, které proběhne mezi tím, dostalo do session nové razítko a přežilo.
- Session s neplatným razítkem se zahodí celá, ne jen klíč `Uzivatel::UZIVATEL`: pracovního uživatele v adminu, potvrzená zobrazení kontaktů a další by jinak zdědil ten, kdo se v tom prohlížeči přihlásí příště (sdílený počítač na infopultu).
- Kdo mění razítko, ukončí všechny session uživatele. Aktuální zařízení si nové razítko musí převzít (`zmenRazitkoPrihlaseni(ponechatTotoZarizeni: true)`, nebo `otoc()`, které session přihlásí znovu z čerstvého řádku a id trvalého přihlášení přenese).
- „Odhlásit ostatní zařízení" pozná toto zařízení **jen podle cookie, ne podle session**, a předchozím tokenem jen 60 s po výměně. Kdo použil ukradenou cookie, sdílí s okradeným zařízením řádek; podle session nebo staršího tokenu by v něm zůstal. Okradené zařízení pak přijde o trvalé přihlášení taky, session mu zůstane. Stejně tak starý token nesmaže řádek, když se s ním někdo přihlásí nebo odhlásí.
- Mezera: kdo se přihlásí do adminu heslem v prohlížeči, kde má z dřívějška cookie trvalého přihlášení na webu (session vznikla bez ní), tomu odhlášení v adminu tu cookie nezruší. Další návštěva webu ho přihlásí.
- Anonymizovaná DB tabulku `uzivatele_trvala_prihlaseni` vyprázdní.

## Otevřené otázky
- JWT pro e-shop v C# (`nastaveni/jwt-bridge.php`) je pořád jeden soubor na uživatele: každé přihlášení ho přepíše a odhlášení na jednom zařízení ho smaže všem. Jestli to e-shopu vadí, záleží na tom, jak token ověřuje (mimo tento repozitář).
- AJAX stavu kopie DB v `admin/index.php` a `Uzivatel::aktualniEditorIdZeSession()` čtou id ze session bez kontroly razítka (kopie DB běží bez tabulek uživatelů). Session odhlášená jinde tam platí do prvního průchodu `zSession()`.
