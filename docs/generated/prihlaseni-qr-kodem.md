# prihlaseni-qr-kodem

TL;DR: Přihlášení zařízení bez hesla QR kódem (web i admin a online prezence): zařízení ukáže QR kód a číslo, uživatel ho schválí na telefonu, kde je přihlášený, a zařízení se přihlásí za něj. Co číslo chrání a co ne, oddělené žádosti webu a adminu, limity, časy.

## Vstupní body v kódu
- Model: `model/Uzivatel/PrihlaseniQrKodem.php` (`zaloz()`, `getZivouZadost()`, `zjistiStav()`, `odpovezStavem()`, `schval()`, `zamitni()`, `najdiProSchvaleni()`), tabulka `prihlaseni_qr` (migrace `2026-11-18-151933_prihlaseni-qr-kodem.php`).
- Zařízení, web: `web/moduly/prihlaseni.php` (tlačítko „Přihlásit QR kódem“, obrazovka s kódem), dotaz na stav `web/moduly/api/prihlaseniQr.php`.
- Zařízení, admin a online prezence: `admin/scripts/prihlaseni.php` (POST `Login::QR_INPUT_NAME`, `Login::QR_ZRUSIT_INPUT_NAME`), obrazovka v `Login::htmlLogin()` + `model/Login/templates/login.xtpl` (blok `qr`), dotaz na stav `admin/scripts/api/prihlaseni-qr.php` (v `ApiAdminu::BEZ_PRIHLASENI`).
- Ptaní se na stav: `web/soubory/prihlaseni-qr.js` (sdílí web i admin, každé 2 s).
- Telefon: stránka schválení `web/moduly/prihlasit-zarizeni.php` (odkaz z QR kódu), čtečka v Nastavení `web/soubory/ctecka-qr.js` + `Registrace::zobrazHtml()` (BarcodeDetector, jinak `web/soubory/jsqr/`).
- Přihlášení po schválení: `Uzivatel::prihlasSchvalenymZarizenim()` (nové id session, razítko, trvalé přihlášení), viz [prihlaseni-na-vice-zarizenich](prihlaseni-na-vice-zarizenich.md).
- Testy: `tests/Uzivatel/PrihlaseniQrKodemTest.php` (zařízení a požadavky z `AbstractPrihlaseniNaZarizenichTestDb`).

## Jak to funguje
1. Zařízení založí žádost: do DB jde hash kódu, správné číslo, nabídka tří čísel pro telefon, IP a User-Agent. Kód a číslo zná jen session zařízení.
2. Telefon otevře odkaz, vidí prohlížeč, IP a stáří kódu, vybere číslo. Správné žádost schválí za přihlášeného uživatele i s razítkem jeho session; špatné ji zamítne (druhý pokus by ze tří uhodl skoro jistě).
3. Zařízení se ptá na stav. Schválenou žádost dokončí sám dotaz: přihlásí session, která žádost založila, a JS přejde dál (web na přihlášku, admin obnoví stránku).

## Pravidla
- Přihlásit se může **jen session, která žádost založila**. Kód z odkazu jen najde žádost, takže vyfocený nebo přeposlaný kód k cizímu účtu nepustí.
- **Číslo hlídá, že uživatel schvaluje zařízení, které má před sebou.** Kdo má jen odkaz (historie, Referer, vyfocený QR kód bez čísla), trefí jednou ze tří. Návnady jsou náhodné a uložené (`nabidka_cisel`), z kódu se správné číslo nepozná; pro jeden kód jsou stálé, jinak by správné prozradilo to, které zůstává.
- (záměr) **Relay phishing číslo nezastaví**: útočník, který oběti pošle QR kód ze své obrazovky i s číslem („naskenuj a vyber 42“), dostane po schválení její účet. Proti tomu je jen varování na stránce schválení. Rozhodnuto 2026-11-18 při review: obrácený tok (telefon ukáže číslo, uživatel ho opíše na zařízení) by to ztížil na úroveň SMS kódu, uživatel zůstal u výběru čísla.
- Stejně tak kdo vyfotí obrazovku zařízení i s číslem, může žádost schválit svým účtem a zařízení přihlásí k sobě (login CSRF). Zařízení pak ukazuje cizí jméno.
- Stránka schválení **nesmí být v rámu** (`X-Frame-Options: DENY`, `frame-ancestors 'none'`): schvaluje se jedním kliknutím a subdomény gamecon.cz (preview s kódem libovolné větve) cookie session dostanou.
- Kód platí **120 s** (`PLATNOST_SEKUND`), schválenou žádost zařízení uplatní nejpozději **120 s po schválení** (`DOKONCENI_SEKUND`), jen jednou (`uplatneno_unix`). Časy jsou v sekundách od epochy z databáze.
- Schválení platí s razítkem přihlášení telefonu: odhlášení ostatních zařízení nebo nové heslo mezi schválením a dokončením zařízení nepřihlásí.
- „Přihlásit se jako“ schvalovat nedovolí: přihlásilo by účet někoho jiného.
- **Žádosti webu a adminu jsou v session zvlášť** (`zSession(doAdminu: …)`, řádek nese `do_adminu`). Session je společná oběma hostům, ale trvalé přihlášení dává cookie jen hostu, kde se zařízení přihlásí, a „Trvale přihlásit“ je na webu výchozí zaškrtnuté, v adminu ne (sdílené počítače infopultu). Žádost webu by jinak v adminu dala trvalé přihlášení, které tam nikdo nezvolil.
- Volba „Trvale přihlásit“ se bere z formuláře, kde zařízení kód vygenerovalo, a uloží se do session se žádostí.
- **Nový kód** zamítne předchozí neschválený, ale až když nový vznikne: kdo narazí na limit, dál použije ten, který má.
- **Limity**: 10 kódů na session (`MAX_ZADOSTI_ZE_SESSION`) a 300 na rozsah IP (`MAX_ZADOSTI_Z_IP`, IPv6 po /64, sloupec `ip_rozsah` jako u [obnovy hesla](obnova-hesla.md)) za 10 minut. Limit IP brání jen plnění tabulky; za jednou IP je celá wifi festivalu, a limit session proto hlídá, aby jeden člověk mačkáním „Nový kód“ nevyčerpal limit ostatním. Kdo se nevejde, přihlásí se heslem. Žádost se zapíše dřív, než se limit IP spočítá (souběh), odmítnutá se smaže.
- **Mrtvý kód přihlašovací stránka zapomene** (`getZivouZadost()`, `zjistiStav()`): vypršelý nebo zamítnutý kód nenahradí formulář s heslem do konce session.
- Po přihlášení dostane session **nové id** (`session_regenerate_id()` v `prihlasSchvalenymZarizenim()`): cookie session je pro celou doménu a podstrčit ji jde z kterékoli subdomény. Ostatní cesty přihlášení (heslo, trvalé přihlášení) id dnes nemění.
- Řádky se mažou po 30 dnech (každá nová žádost smaže nejvýš 100 starých), do anonymizované DB se tabulka nedostane (`AnonymizovanaDatabaze::PRILIS_CITLIVE_TABULKY`).

## Gotchas
- `vytvoreno_unix` je `INT UNSIGNED`: rozdíl s `UNIX_TIMESTAMP()`, který vyjde záporný (hodiny DB skočí zpět), MariaDB odmítne chybou 1690, proto `CAST(… AS SIGNED)` v `najdiProSchvaleni()`.
- Telefon, který schvaluje, nesmí nabízet „Přihlásit QR kódem“: přihlašovací stránka s `?schvalitKod=` (přesměrování ze stránky schválení) tlačítko schová a po přihlášení vrátí na schválení (`getUrlSchvaleniZParametru()` pustí jen tvar kódu, jinam nepřesměruje).
- Lokálně web vidí všechny požadavky z brány Dockeru, takže QA řádky sdílí jeden limit IP s vývojářovým prohlížečem.
- Session zámek PHP řadí požadavky jedné session za sebe; dvojí uplatnění drží i podmínka `uplatneno_unix IS NULL` (kopie session bez zámku).
