# prihlaseni-na-vice-zarizenich

TL;DR: Jak přihlášení drží na víc zařízeních naráz a jak se odhlašuje: session s razítkem přihlášení, trvalé přihlášení („Trvale přihlásit") s řádkem na zařízení, odhlášení tohoto / ostatních / všech zařízení.

## Vstupní body v kódu
- Session a razítko: `model/uzivatel.php` — `Uzivatel::zSession()`, `prihlas()`, `prihlasId()`, konstanta `RAZITKO_PRIHLASENI`.
- Trvalé přihlášení: `model/Uzivatel/TrvalePrihlaseni.php`, tabulka `uzivatele_trvala_prihlaseni` (migrace `2026-10-27-105050_trvale-prihlaseni-na-vice-zarizenich.php`).
- Odhlášení: `Uzivatel::odhlas()` (toto zařízení), `Uzivatel::odhlasNaOstatnichZarizenich()`, `Uzivatel::heslo()` (obnova hesla odkazem), změna hesla v `Uzivatel::registrujUprav()`.
- Tlačítko „Odhlásit ostatní zařízení": stránka Nastavení (`web/moduly/registrace.php` → `Registrace::zpracujOdhlaseniOstatnichZarizeni()`).
- Testy: `tests/Uzivatel/TrvalePrihlaseniTest.php`.

## Jak to funguje
- **Session** je v každém prohlížeči zvlášť, cookie zmizí zavřením prohlížeče, server ji drží 24 h nečinnosti (`nastaveni/zavadec-zaklad.php`).
- **Razítko přihlášení** = `uzivatele_hodnoty.random`. Session si ho uloží při přihlášení, `zSession()` ji zahodí, když nesedí s DB. Kontroluje se jen pod klíčem `Uzivatel::UZIVATEL`, ne u pracovního uživatele v adminu.
- **Trvalé přihlášení**: cookie `gcTrvalePrihlaseni` nese token, DB jen jeho SHA-256. Každé přihlášení z cookie token vymění. Předchozí token platí ještě 60 s kvůli souběžným kartám po startu prohlížeče. Nepoužitý řádek vyprší po roce, smaže se při dalším trvalém přihlášení kohokoli.

## Pravidla
- (záměr) „Odhlásit" odhlásí **jen toto zařízení**.
- (záměr) „Odhlásit ostatní zařízení" v Nastavení: ostatní přijdou o session i trvalé přihlášení, **toto zůstane** přihlášené.
- (záměr) **Změna hesla v profilu** odhlásí ostatní zařízení, toto zůstane.
- **Obnova hesla odkazem** odhlásí všechna zařízení, i to, ve kterém se obnovuje (přihlásí se novým heslem).
- (záměr) Nepoužívané trvalé přihlášení vyprší **po roce**.

## Gotchas
- `random` dřív nesl přímo token trvalého přihlášení (jeden na uživatele, takže přihlášení na dalším zařízení odhlásilo předchozí). Migrace tokeny převedla do tabulky a `random` všem vyprázdnila. Session bez razítka se bere jako prázdné razítko, takže session z doby před nasazením běží, dokud se `random` uživatele poprvé nezmění.
- Kdo mění `random`, ukončí všechny session uživatele. Aktuální zařízení si nové razítko musí převzít (`ponechTotoZarizeniPrihlasene()`, nebo `otoc()`, které session přihlásí znovu z čerstvého řádku).
- Odhlášení ostatních zařízení pozná „toto zařízení" podle cookie, ne podle uživatele: cookie počítá i s předchozím tokenem.
- Anonymizovaná DB tabulku `uzivatele_trvala_prihlaseni` vyprázdní.

## Otevřené otázky
- JWT pro e-shop v C# (`nastaveni/jwt-bridge.php`) je pořád jeden soubor na uživatele: každé přihlášení ho přepíše a odhlášení na jednom zařízení ho smaže všem. Jestli to e-shopu vadí, záleží na tom, jak token ověřuje (mimo tento repozitář).
