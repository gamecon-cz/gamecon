# prihlaseni-na-vice-zarizenich

TL;DR: Jak přihlášení drží na víc zařízeních naráz a jak se odhlašuje: session s razítkem přihlášení, trvalé přihlášení („Trvale přihlásit") s řádkem na zařízení, odhlášení tohoto / ostatních / všech zařízení.

## Vstupní body v kódu
- Session a razítko: `model/uzivatel.php` — `Uzivatel::zSession()`, `prihlas()`, `prihlasId()`, `prihlasTrvalymPrihlasenim()`, společný konec všech přihlášení `prihlasDoSession()`, konstanty `RAZITKO_PRIHLASENI`, `ID_TRVALEHO_PRIHLASENI`.
- Výměna razítka na jednom místě: `Uzivatel::zmenRazitkoPrihlaseni()` (odhlášení ostatních, změna hesla v profilu, obnova hesla, sloučení s převzatým heslem).
- Trvalé přihlášení: `model/Uzivatel/TrvalePrihlaseni.php`, tabulka `uzivatele_trvala_prihlaseni` a sloupec `uzivatele_hodnoty.razitko_prihlaseni` (migrace `2026-11-10-113933_trvale-prihlaseni-na-vice-zarizenich.php`).
- Odhlášení: `Uzivatel::odhlas()` (toto zařízení), `Uzivatel::odhlasNaOstatnichZarizenich()`, `Uzivatel::heslo()` (obnova hesla odkazem), změna hesla v `Uzivatel::registrujUprav()`, sloučení v `UzivatelSlucovani::sluc()`.
- Tlačítko „Odhlásit ostatní zařízení": stránka Nastavení pod tlačítkem Uložit, vlastní formulář (`web/moduly/registrace.php` → `Registrace::zpracujOdhlaseniOstatnichZarizeni()`).
- Token pro e-shop v C#: `nastaveni/jwt-bridge.php` (`deleteStoredJwtForUser()`, `deleteJwtCookie()`).
- Testy: `tests/Uzivatel/TrvalePrihlaseniTest.php`, zařízení a požadavky v `AbstractPrihlaseniNaZarizenichTestDb`.

## Jak to funguje
- **Session** je v každém prohlížeči zvlášť, cookie zmizí zavřením prohlížeče, server ji drží 24 h nečinnosti (`nastaveni/zavadec-zaklad.php`). Web a admin ji sdílí (cookie pro celou doménu).
- **Razítko přihlášení** = `uzivatele_hodnoty.razitko_prihlaseni`. Session si ho uloží při přihlášení, `zSession()` ji zahodí, když nesedí s DB. Kontroluje se jen pod klíčem `Uzivatel::UZIVATEL`, ne u pracovního uživatele v adminu, a jen když v DB není NULL (tedy už bylo vyměněno, viz Gotchas).
- **Trvalé přihlášení**: cookie `gcTrvalePrihlaseni` nese token, DB jen jeho SHA-256. Každé přihlášení z cookie token vymění. Předchozí token platí ještě 60 s kvůli souběžným kartám po startu prohlížeče a ty dostanou tentýž nový token: odvozuje se z předchozího (HMAC s náhodnou solí v `sul_tokenu`), takže prohlížeč nese nový, ať uloží kteroukoli odpověď, i když tu s výměnou zahodil. Nepoužitý řádek vyprší po roce; vypršelé maže každé další trvalé přihlášení kohokoli, nejvýš 100 naráz. Cookie, která žádný řádek nenajde, v prohlížeči zůstane (nepřihlásí).
- Cookie je **jen pro host webu** (bez domény), aby token nechodil na betu, preview a archivy. Admin ji proto nevidí; session si pamatuje id řádku (`ID_TRVALEHO_PRIHLASENI`), aby ho zrušilo i odhlášení v adminu.
- **Token pro e-shop** (JWT) je jeden soubor na uživatele. Výměna razítka ho přepíše novým (zařízení zůstává přihlášené), nebo smaže (odhlašují se všechna). „Odhlásit" a session zahozená kvůli razítku smažou cookie s tokenem jen v tom prohlížeči, uložený token zůstane ostatním zařízením.

## Pravidla
- (záměr) „Odhlásit" odhlásí **jen toto zařízení**.
- (záměr) „Odhlásit ostatní zařízení" v Nastavení: ostatní přijdou o session i trvalé přihlášení, **toto zůstane** přihlášené.
- (záměr) **Změna hesla v profilu** odhlásí ostatní zařízení, toto zůstane.
- **Obnova hesla odkazem** odhlásí všechna zařízení, i to, ve kterém se obnovuje (přihlásí se novým heslem).
- **Sloučení účtů**: trvalá přihlášení slučovaného účtu se zruší, nepřevádí se. Převzaté heslo odhlásí všechna zařízení výsledného účtu jako obnova hesla.
- (záměr) Nepoužívané trvalé přihlášení vyprší **po roce**.

## Gotchas
- `random` dřív nesl přímo token trvalého přihlášení (jeden na uživatele, takže přihlášení na dalším zařízení odhlásilo předchozí). Migrace tokeny převedla do tabulky a `random` všem vyprázdnila. Nepřevedla účty bez hesla a rychloregistrace: jejich `random` mohl být kód z e-mailu po rychloregistraci. Trvalé přihlášení potřebovalo heslo a jeho nastavení přes web (obnova, profil) token přepsalo, takže účet bez hesla žádný token v prohlížeči nemá; heslo bez nového tokenu mohla dát rychloregistraci jen sloučení účtů, ty vyřadí `z_rychloregistrace`, který je ale až od července 2023. Od té doby tam píše jen rychloregistrace kód do odkazu v e-mailu, který nic nečte; běžná registrace píše prázdný řetězec.
- Starý kód psal do `random` nový token i při každé registraci, odhlášení a změně hesla, takže migrace založila řádek skoro každému účtu a většinu z nich nikdo nepoužije. Všechny mají stejný čas, a proto se vypršelé mažou po kouscích (`TrvalePrihlaseni::UKLID_NAJEDNOU`), ne naráz v jednom přihlášení.
- Razítko NULL se nekontroluje, takže platí i session z doby před nasazením, které razítko nemají. Rychloregistrace proto sloupec razítka do INSERTu nedává (výchozí NULL).
- **Nasazení nahraje kód dřív, než doběhne migrace.** Těch pár sekund bez tabulky a sloupce trvalé přihlášení z cookie i heslem, odhlášení s cookie, změna hesla, sloučení a stránky Symfony, které načítají entitu `User`, skončí chybou jako u každé migrace. Nic se tiše nepřeskočí: zrušení trvalého přihlášení, které by neproběhlo, by migrace z `random` vzkřísila. Kdo tomu chce předejít, nasadí za údržbového režimu (`dokumentace/nasazeni.md`, `admin/deploy/*` za ním projde).
- **Výměna razítka a trvalá přihlášení jsou v jedné transakci** (`zmenRazitkoPrihlaseni()`), razítko první. Přihlášení z cookie čte razítko stejným dotazem jako svůj řádek (`TrvalePrihlaseni::uplatniCookie()` → `Uzivatel::prihlasTrvalymPrihlasenim()`), takže vidí buď řádek se starým razítkem (session skončí s dalším požadavkem), nebo nic. Trvalé přihlášení heslem zakládá řádek `INSERT … SELECT` jen při razítku, proti kterému ověřilo heslo (`TrvalePrihlaseni::zaloz()`); zámek řádku uživatele ho pozdrží, dokud transakce nedoběhne. Bez toho by přihlášení starým heslem souběžné se změnou hesla dostalo trvalé přihlášení, které ji přežije.
- `prihlasDoSession()` bere razítko aktuální z DB. Kdo zná to, se kterým se prohlížeč přihlásil doopravdy (přihlášení z cookie, `otoc()`), ho po něm vrátí `obnovPrihlaseni()`. `otoc()` tak nechá session staré razítko, i když razítko mezitím vyměnilo jiné zařízení: běžící požadavek ji jinak přihlásil znovu.
- Session s neplatným razítkem se zahodí celá, ne jen klíč `Uzivatel::UZIVATEL`: pracovního uživatele v adminu, potvrzená zobrazení kontaktů a další by jinak zdědil ten, kdo se v tom prohlížeči přihlásí příště (sdílený počítač na infopultu).
- Kdo mění razítko, ukončí všechny session uživatele. Aktuální zařízení si nové razítko převezme jen přes `zmenRazitkoPrihlaseni(ponechatTotoZarizeni: true)`.
- „Odhlásit ostatní zařízení" pozná toto zařízení **jen podle cookie, ne podle session**, a předchozím tokenem jen 60 s po výměně. Kdo použil ukradenou cookie, sdílí s okradeným zařízením řádek; podle session nebo staršího tokenu by v něm zůstal. Okradené zařízení pak přijde o trvalé přihlášení taky, session mu zůstane. Stejně tak starý token nesmaže řádek, když se s ním někdo přihlásí nebo odhlásí.
- Kdo s ukradenou kopií přijde do 60 s po majiteli, dostane odvozený nový token jako souběžná karta a sdílí s majitelem řádek. „Odhlásit ostatní zařízení" proto tomuto zařízení vydá token, který se z ničeho odvodit nedá (sůl i předchozí hash smaže), a kopie přestane platit.
- Cookie bez řádku se nemaže schválně: tabulka bývá chvíli prázdná (kopie DB na betě, obnova zálohy) a smazaná cookie by přihlášení zrušila napořád.
- Mezera: kdo se přihlásí do adminu heslem v prohlížeči, kde má z dřívějška cookie trvalého přihlášení na webu (session vznikla bez ní), tomu odhlášení v adminu tu cookie nezruší. Další návštěva webu ho přihlásí.
- **Po „Přihlásit se jako“** ([prepnuti-na-uzivatele](prepnuti-na-uzivatele.md)) má session razítko uživatele, na kterého se někdo přepnul, ale id trvalého přihlášení a cookie s JWT pořád toho, kdo sedí u zařízení. „Odhlásit" tak ruší trvalé přihlášení toho, kdo se přepnul. Výměna razítka za druhého jeho uložený JWT jen smaže, nový by přepsal cookie toho, kdo se přepnul. Když se ten, na koho se někdo přepnul, odhlásí na ostatních zařízeních, přepnutá session skončí celá.
- Přepnutá session nese razítko druhého uživatele, takže ji odhlášení ostatních zařízení toho, kdo se přepnul, neukončí. `PrepnutiNaUzivatele` si proto pamatuje i razítko, se kterým se přihlásil on, a návrat (`vratSe()` → `Uzivatel::prihlasIdSRazitkem()`) ho s jiným razítkem nepřihlásí a session zahodí celou. Do návratu ale u zařízení zůstane přepnutý na druhého.
- `otoc()` v CLI jen načte data znovu, session nepřihlašuje, takže ho testy nepokryjí.
- Anonymizovaná DB tabulku `uzivatele_trvala_prihlaseni` vyprázdní. Sloupec `razitko_prihlaseni` nechá (náhodná hodnota, nikoho neidentifikuje).

## Otevřené otázky
- JWT pro e-shop: výměna razítka přepíše nebo smaže uložený token, kopie v cookie ostatních zařízení ale podpis neztratí a platí do `exp` (1 h). Odhlášení zabere, jen když e-shop token porovnává s uloženým (mimo tento repozitář). Tokeny vydané v téže sekundě jsou navíc stejné. Každé přihlášení uložený token přepíše, takže e-shop drží jen poslední přihlášené zařízení. „Odhlásit" uložený token nechá (dřív ho mazalo, a tím odhlásilo e-shop všem), kopie z cookie tohoto prohlížeče tak do `exp` sedí s uloženým dál.
- AJAX stavu kopie DB v `admin/index.php` a `Uzivatel::aktualniEditorIdZeSession()` čtou id ze session bez kontroly razítka (kopie DB běží bez tabulek uživatelů). Session odhlášená jinde tam platí do prvního průchodu `zSession()`.
