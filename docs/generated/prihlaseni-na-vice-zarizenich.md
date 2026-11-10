# prihlaseni-na-vice-zarizenich

TL;DR: Jak přihlášení drží na víc zařízeních naráz a jak se odhlašuje: session s razítkem přihlášení, trvalé přihlášení („Trvale přihlásit") s řádkem na zařízení, odhlášení tohoto / ostatních / všech zařízení.

## Vstupní body v kódu
- Session a razítko: `model/uzivatel.php` — `Uzivatel::zSession()`, `prihlas()`, `prihlasId()`, `prihlasTrvalymPrihlasenim()`, společný konec všech přihlášení `prihlasDoSession()`, konstanty `RAZITKO_PRIHLASENI`, `ID_TRVALEHO_PRIHLASENI`.
- Výměna razítka na jednom místě: `Uzivatel::zmenRazitkoPrihlaseni()` (odhlášení ostatních, změna hesla v profilu, obnova hesla, sloučení s převzatým heslem).
- Trvalé přihlášení: `model/Uzivatel/TrvalePrihlaseni.php`, tabulka `uzivatele_trvala_prihlaseni` a sloupec `uzivatele_hodnoty.razitko_prihlaseni` (migrace `2026-11-10-103100_trvale-prihlaseni-na-vice-zarizenich.php`).
- Odhlášení: `Uzivatel::odhlas()` (toto zařízení), `Uzivatel::odhlasNaOstatnichZarizenich()`, `Uzivatel::heslo()` (obnova hesla odkazem), změna hesla v `Uzivatel::registrujUprav()`, sloučení v `UzivatelSlucovani::sluc()`.
- Tlačítko „Odhlásit ostatní zařízení": stránka Nastavení pod tlačítkem Uložit, vlastní formulář (`web/moduly/registrace.php` → `Registrace::zpracujOdhlaseniOstatnichZarizeni()`).
- Token pro e-shop v C#: `nastaveni/jwt-bridge.php` (`deleteStoredJwtForUser()`, `deleteJwtCookie()`).
- Testy: `tests/Uzivatel/TrvalePrihlaseniTest.php`, okno nasazení `tests/Uzivatel/TrvalePrihlaseniPredMigraciTest.php`, společné zařízení a požadavky v `AbstractPrihlaseniNaZarizenichTestDb`.

## Jak to funguje
- **Session** je v každém prohlížeči zvlášť, cookie zmizí zavřením prohlížeče, server ji drží 24 h nečinnosti (`nastaveni/zavadec-zaklad.php`). Web a admin ji sdílí (cookie pro celou doménu).
- **Razítko přihlášení** = `uzivatele_hodnoty.razitko_prihlaseni`. Session si ho uloží při přihlášení, `zSession()` ji zahodí, když nesedí s DB. Kontroluje se jen pod klíčem `Uzivatel::UZIVATEL`, ne u pracovního uživatele v adminu, a jen když v DB není NULL (tedy už bylo vyměněno, viz Gotchas).
- **Trvalé přihlášení**: cookie `gcTrvalePrihlaseni` nese token, DB jen jeho SHA-256. Každé přihlášení z cookie token vymění. Předchozí token platí ještě 60 s kvůli souběžným kartám po startu prohlížeče. Nepoužitý řádek vyprší po roce; vypršelé maže každé další trvalé přihlášení kohokoli, nejvýš 100 naráz. Cookie, která žádný řádek nenajde, v prohlížeči zůstane (nepřihlásí).
- Cookie je **jen pro host webu** (bez domény), aby token nechodil na betu, preview a archivy. Admin ji proto nevidí; session si pamatuje id řádku (`ID_TRVALEHO_PRIHLASENI`), aby ho zrušilo i odhlášení v adminu.
- **Token pro e-shop** (JWT) je jeden soubor na uživatele. Výměna razítka ho přepíše novým (zařízení zůstává přihlášené), nebo smaže (odhlašují se všechna). Session zahozená kvůli razítku smaže cookie s tokenem jen v tom prohlížeči.

## Pravidla
- (záměr) „Odhlásit" odhlásí **jen toto zařízení**.
- (záměr) „Odhlásit ostatní zařízení" v Nastavení: ostatní přijdou o session i trvalé přihlášení, **toto zůstane** přihlášené.
- (záměr) **Změna hesla v profilu** odhlásí ostatní zařízení, toto zůstane.
- **Obnova hesla odkazem** odhlásí všechna zařízení, i to, ve kterém se obnovuje (přihlásí se novým heslem).
- **Sloučení účtů**: trvalá přihlášení slučovaného účtu se zruší, nepřevádí se. Převzaté heslo odhlásí všechna zařízení výsledného účtu jako obnova hesla.
- (záměr) Nepoužívané trvalé přihlášení vyprší **po roce**.

## Gotchas
- `random` dřív nesl přímo token trvalého přihlášení (jeden na uživatele, takže přihlášení na dalším zařízení odhlásilo předchozí). Migrace tokeny převedla do tabulky (kromě rychloregistrací, jejich `random` mohl být kód z e-mailu) a `random` všem vyprázdnila. Od té doby tam píše jen rychloregistrace kód do odkazu v e-mailu, který nic nečte; běžná registrace píše prázdný řetězec.
- Starý kód psal do `random` nový token i při každé registraci, odhlášení a změně hesla, takže migrace založila řádek skoro každému účtu a většinu z nich nikdo nepoužije. Všechny mají stejný čas, a proto se vypršelé mažou po kouscích (`TrvalePrihlaseni::UKLID_NAJEDNOU`), ne naráz v jednom přihlášení.
- **Nasazení nahraje kód dřív, než doběhne migrace.** Do té doby:
  - razítko NULL (nebo chybějící sloupec) se nekontroluje, takže platí i session z doby před nasazením, které razítko nemají;
  - dotazy `TrvalePrihlaseni` snesou chybějící tabulku i sloupec (chyby 1146, 1054): trvalé přihlášení přihlásí jen na session a cookie nedá, cookie nepřihlásí, odhlášení a sloučení projdou;
  - změna hesla (profil, obnova, sloučení) heslo uloží, ostatní zařízení ale neodhlásí (`zmenRazitkoPrihlaseni()` zopakuje zápis bez razítka);
  - tlačítko „Odhlásit ostatní zařízení" skončí chybou: nemá co vyměnit a hláška o odhlášení by lhala;
  - rychloregistrace sloupec razítka do INSERTu nedává (výchozí NULL).
- **Pořadí při výměně razítka:** nejdřív smazat trvalá přihlášení, pak vyměnit razítko, a přihlášení z cookie čte razítko stejným dotazem jako svůj řádek (`TrvalePrihlaseni::uplatniCookie()` → `Uzivatel::prihlasTrvalymPrihlasenim()`). Jinak by přihlášení z cookie, které proběhne mezi tím, dostalo do session nové razítko a přežilo.
- `prihlasDoSession()` bere razítko aktuální z DB. Kdo zná to, se kterým se prohlížeč přihlásil doopravdy (přihlášení z cookie, `otoc()`), ho po něm vrátí `obnovPrihlaseni()`. `otoc()` tak nechá session staré razítko, i když razítko mezitím vyměnilo jiné zařízení: běžící požadavek ji jinak přihlásil znovu.
- Session s neplatným razítkem se zahodí celá, ne jen klíč `Uzivatel::UZIVATEL`: pracovního uživatele v adminu, potvrzená zobrazení kontaktů a další by jinak zdědil ten, kdo se v tom prohlížeči přihlásí příště (sdílený počítač na infopultu).
- Kdo mění razítko, ukončí všechny session uživatele. Aktuální zařízení si nové razítko převezme jen přes `zmenRazitkoPrihlaseni(ponechatTotoZarizeni: true)`.
- „Odhlásit ostatní zařízení" pozná toto zařízení **jen podle cookie, ne podle session**, a předchozím tokenem jen 60 s po výměně. Kdo použil ukradenou cookie, sdílí s okradeným zařízením řádek; podle session nebo staršího tokenu by v něm zůstal. Okradené zařízení pak přijde o trvalé přihlášení taky, session mu zůstane. Stejně tak starý token nesmaže řádek, když se s ním někdo přihlásí nebo odhlásí.
- Cookie bez řádku se nemaže schválně: tabulka bývá chvíli prázdná (kopie DB na betě, obnova zálohy) a smazaná cookie by přihlášení zrušila napořád.
- Mezera: kdo se přihlásí do adminu heslem v prohlížeči, kde má z dřívějška cookie trvalého přihlášení na webu (session vznikla bez ní), tomu odhlášení v adminu tu cookie nezruší. Další návštěva webu ho přihlásí.
- `otoc()` v CLI jen načte data znovu, session nepřihlašuje, takže ho testy nepokryjí.
- Anonymizovaná DB tabulku `uzivatele_trvala_prihlaseni` vyprázdní. Sloupec `razitko_prihlaseni` nechá (náhodná hodnota, nikoho neidentifikuje).

## Otevřené otázky
- JWT pro e-shop: výměna razítka přepíše nebo smaže uložený token, kopie v cookie ostatních zařízení ale podpis neztratí a platí do `exp` (1 h). Odhlášení zabere, jen když e-shop token porovnává s uloženým (mimo tento repozitář). Tokeny vydané v téže sekundě jsou navíc stejné. Totéž dřív: každé přihlášení token přepíše a odhlášení na jednom zařízení ho smaže všem.
- AJAX stavu kopie DB v `admin/index.php` a `Uzivatel::aktualniEditorIdZeSession()` čtou id ze session bez kontroly razítka (kopie DB běží bez tabulek uživatelů). Session odhlášená jinde tam platí do prvního průchodu `zSession()`.
