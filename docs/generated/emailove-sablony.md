# Šablony e-mailů

Předmět a text e-mailů, které GameCon posílá, jsou v databázi a mění se v adminu (Web → Texty e-mailů, stejné právo jako Editace stránek) bez zásahu do kódu. Dokument popisuje, co z kódu vede k hotovému e-mailu, pravidla zápisu symbolů a jak přidat další e-mail.

## Vstupní body v kódu

- `Gamecon\Kanaly\EmailovaSablona\EmailoveSablony` — vstup pro kód, který e-mail posílá: `vytvorMail($kod, $promenne): GcMail`, `vykresli()` (jen předmět a text), `vykresliPredmet()`.
- `EmailoveSablonyKatalog` — seznam všech šablon: kód, skupina, kdy odchází, komu, formát a **proměnné** (název, popis, ukázka, smí být v předmětu, je to už HTML, je volitelná).
- `EmailovaSablonaRepository` — čtení a ukládání tabulky `emailova_sablona`.
- `RozkladSymbolu` — jediné místo, které rozhoduje, co je v textu symbol. `EmailovaSablonaValidator` a `EmailovaSablonaVykreslovac` z něj vycházejí.
- `SystemoveHodnotyProSablony` — které VELKÉ hodnoty se smí dosadit (viz níže).
- `EmailovaSablonaKontrola` + `ProblemySablonHtml` — zpětná kontrola uložených textů a červený blok nad stránkou.
- `EditorSablonyHtml` + `admin/files/editor-sablony.js` + `editor-sablony-logika.js` — formulář s našeptávačem. Modul `admin/scripts/modules/web/emailove-sablony.php`.
- `GcMail::format()` — formát (text / HTML), který určila šablona; `odeslat()` bez argumentu ho použije.
- `Gamecon\Admin\PosledniZmenaHtml` — sloupec Poslední změna (relativní čas a nápověda s autorem a přesným časem), sdílí ho stránka Nastavení a seznam e-mailů. Neznámý autor je uživatel System.

## Datový model

- `emailova_sablona(kod PK, predmet, text, zmenil, zmeneno_kdy)`. Jeden řádek na e-mail, **neprázdnost hlídá i `CHECK`** (aplikace ji hlídá dřív, ale ruční zásah do databáze ji nesmí obejít). Výchozí znění je seedované migrací z původního kódu.
- `upominka_vlastni_zneni` zůstává: vlastní znění upomínky je jeden text na ročník, ne pevná šablona. Používá stejný editor, validátor a vykreslovač (`UpominkaVlastniZneni::definice()`).
- Na stránce `finance/upominky-dluzniku` vede u každé ze dvou standardních voleb odkaz „Upravit obsah standardního znění“ rovnou na detail e-mailu `?kod=upominka_<typ>_pritomen`. Každý typ má čtyři varianty podle účasti (`pritomen`, `jen_prihlasen`, `nedorazil_drive`, `nedorazil_nikdy`) a odkaz vede na první; ostatní jsou v seznamu e-mailů, kam se z detailu dostaneš odkazem „Zpět na seznam e-mailů“. Kódy `promlceni_varovani_*` jsou jiný e-mail (varování před promlčením zůstatku), ne upomínky dlužníkům. CFO bez práva 105 dostane na detailu e-mailu odmítnutou stránku, což je záměr.
- Sloupec bez názvu před „Poslední změna“ má v seznamu e-mailů i v Nastavení odkaz `#` (`web/emailove-sablony#<kód>`, `nastaveni#<KLÍČ>`), kterým se dá sdílet přesný řádek. Třída `lokalni-odkaz` zařídí zablikání cíle (`admin/files/zablikej-1.1.js`).
- `systemove_nastaveni.verejne` (a `systemove_nastaveni_log.verejne`) — příznak „veřejné“, výchozí 0.

## Pravidla zápisu symbolů (záměr)

- `%jmeno%` **malými** písmeny je proměnná, kterou doplní kód (jmeno, dluh, vs, a = koncovka podle pohlaví…). `%GC_BEZI_OD%` **VELKÝMI** je systémová hodnota. `%%` je samotné procento (holé `%` na konci slova nebo před mezerou funguje taky).
- Jen pojmenované symboly: `%1` je chyba. Složené závorky ze starého zápisu (`{jmeno}`) se odmítnou.
- V **předmětu** smí být jen proměnné označené `vPredmetu` a systémové hodnoty. U upomínek jen systémové hodnoty.
- Prázdný předmět nebo text se neuloží; předmět musí být na jednom řádku. Prázdný je i text jen z bílých znaků včetně nezlomitelné mezery (`RozkladSymbolu::jePrazdny()`, stejně to bere `String.trim()` v editoru).
- Systémová hodnota se dosadí, jen když je nastavení **veřejné** (sloupec Veřejné na stránce Nastavení) nebo je v `SystemoveHodnotyProSablony::POVOLENE_KONSTANTY` (`UCET_CZ`, `IBAN`). **Neveřejné nebo neznámé zůstane v e-mailu jako holé `%FOO%` a e-mail odejde.** Dosazení jakékoli konstanty by prozradilo i heslo do databáze.
- Kdo smí texty měnit: každý s právem 105 (Administrace - panel Web), tedy stejně jako Editace stránek. Modul nemá vlastní kontrolu role: `admin/index.php` pustí na stránku držitele práva záložky Web nebo práva z hlavičky modulu (`pravo: 105`). Vlastní znění upomínky (`finance/upominky-dluzniku?zneni`) zůstává jen pro CFO. Držitel jen práva 108 (CFO bez organizátorské role, tedy bez práva 105) k modulu přístup nemá; odkazy z červeného boxu na Nastavení by ho vedly na odmítnutou stránku.

## Kde se hlídají neveřejné hodnoty

1. Při uložení (server odmítne, JS jen upozorní dřív).
2. Při každém načtení stránky Nastavení, seznamu e-mailů a detailu e-mailu (u detailu i u vlastního znění upomínky) se zkontrolují už uložené texty a nahoře se vypíše chyba s odkazem na úpravu. Nastavení totiž půjde odveřejnit i poté, co ho nějaký e-mail dosazuje. Stejný blok (druhá část, „e-mail se neodešle“) hlásí i šablonu, jejíž řádek v tabulce chybí nebo je prázdný, protože odeslání takového e-mailu skončí výjimkou `EmailovaSablonaNeniVyplnena`.

## Formát HTML

U e-mailů ve formátu HTML se každý nový řádek v **šabloně** převede na `<br />`. Hodnoty proměnných se escapují (bez dvojího kódování `&amp;`), kromě proměnných označených `html: true`. Nové řádky uvnitř hodnot se nepřevádějí.

## Přidání dalšího e-mailu

1. Definice v `EmailoveSablonyKatalog` (proměnné včetně ukázky pro náhled).
2. **Nová migrace** s `INSERT IGNORE INTO emailova_sablona` výchozího znění (už sloučenou migraci se nemění). Test `EmailoveSablonyKatalogTest` ohlídá, že kód a databáze si odpovídají, že výchozí znění projde validací a že se každá nevolitelná proměnná někde použije.
3. V místě odeslání `EmailoveSablony::vytvorMail($kod, [...])` s přesně deklarovanými proměnnými (jiná sada je `ChybiPromennaSablony`).

## Mimo šablony schválně

Technická upozornění, která musí odejít i tehdy, když nejde přečíst databáze (ze šablony v databázi by se neodeslala právě tehdy, kdy je nejdůležitější): `CronErrorNotifier`, selhání zálohy databáze v `admin/cron/zaloha_databaze.php`, `VyjimkovacChyba::odesli()`, `Log::warning()`. A testovací e-mail v Mail logy, jehož text píše ten, kdo ho posílá.

## Co se proti původnímu kódu změnilo v odeslaných e-mailech

- Odhlášení z GameConu a e-maily pro CFO (promlčení, podezřelá platba) odcházely jako HTML s textem bez odřádkování. Teď jsou textové, jak mají být.
- Oddělovací čára v e-mailech pro CFO má pevnou délku, dřív podle délky úvodní věty.
- Potvrzovací odkaz v rychlé registraci vede na `URL_WEBU`, dřív natvrdo na `https://gamecon.cz`.
- Hodnoty v HTML e-mailech se escapují (jméno aktivity s `&`, `<`).
- Odstraněna dvojitá mezera v předmětu „Uživatel 'X' (1)  se odhlásil ale platil“ a zbytečné `</p>` v BFGR e-mailu.

## Gotchas

- Moduly admina se `require`ují v rozsahu proměnných `admin/index.php`. Proměnná `$stranka` v modulu shodila celé menu (500) — názvy proměnných v modulu musí být jiné než `$stranka`, `$podstranka`, `$url`, `$u`, `$menu`, `$xtpl`….
- V PHP řetězci se za proměnnou s uvozovkou `„$x“` čte jako proměnná `$x“` (`“` je platný znak názvu). Piš `{$x}“`.
- Sdílená instance `SystemoveNastaveni::zGlobals()` si drží záznamy v paměti i po vrácení transakce testu. Test, který mění příznak veřejné, bere čerstvou instanci (`CerstveSystemoveNastaveniTrait`).
- `zaznamyDoKonstant()` musí snést chybějící sloupec, který teprve přidá čekající migrace, i při výpočtu odvozených konstant; jinak spadne bootstrap včetně `migrations:continue`.
- `MailLogger` ukládá celou zprávu včetně hlaviček, tělo textového e-mailu je v něm jako quoted-printable. HTML tělo je ve `telo_html`.
- Migrace vlastních znění upomínek (`…-upominka-vlastni-zneni-symboly-v-procentech.php`) zdvojí v uloženém předmětu a textu každé procento, které starý kód nedosazoval, a vrátí zpátky jen `%ROCNIK%`, `%IBAN%` a `%UCET_CZ%`. Jinak by `10%sleva` po převodu nebylo platné.
- `CHECK (TRIM(text) <> '')` v MariaDB bere kvůli kolaci `utf8mb4_czech_ci` jako prázdný i text jen z nezlomitelných mezer; spoléhat na to se nedá, hlídá to hlavně `RozkladSymbolu::jePrazdny()`.
- Test editoru (`ui/src/adminEditorSablony`) sdílí s PHP soubor `tests/Model/Kanaly/EmailovaSablona/validace-pripady.json`, takže JS a PHP validátor na stejný vstup dávají stejnou odpověď. Spouští se `yarn test` v `ui/` a vyžaduje node 24 (v dev kontejneru je 20, proto při ruční práci `docker run node:24`).
- Editor se při odchodu s neuloženými změnami (jiný předmět nebo text než při načtení) ptá přes `beforeunload`, v detailu e-mailu i ve vlastním znění upomínky (sdílený editor). Porovnává se s tím, co vykreslil server (`defaultValue`), ne s hodnotou při spuštění skriptu: po Zpět vrací Chrome rozepsaný text až po skriptu a bez události `input` (proto se stav přepočítá při `pageshow`), Firefox ho vrací ještě před skriptem. Posluchač se registruje jen, dokud je co ztratit (Firefox s ním stránku nedá do cache zpět/vpřed); odeslání formuláře otázku vypne, další úprava ji zapne znovu; po neúspěšném uložení na serveru se ptá hned. Text dialogu určuje prohlížeč, nejde ho změnit. Chování pokrývá `ui/src/adminEditorSablony/editorSablony.test.ts` (jsdom nad skutečnou šablonou editoru).
- **Sloupec Odesláno v seznamu e-mailů** (`3 ×`, v tooltipu příjemci a selhání) čte `MailLogger::souhrnPodleSablon()` z `logy/maily.sqlite`. Log má od zavedení sloupce `sablona` (kód šablony, vyplní ho `GcMail::sablona()`; všechny e-maily z šablon jdou přes `EmailoveSablony::vytvorMail()`, výjimky jsou upomínky dlužníkům v `UpominaniDluzniku` a obnova hesla v `ObnovaHeslaController`) a `rocnik` (z `SystemoveNastaveni::rocnik()` v době odeslání). Starší záznamy mají oba sloupce prázdné a do počtů se nezapočítají; doplnění sloupců je jen `ALTER TABLE`, schválně bez `UPDATE`, který by přepsal celý (velký) log při prvním odeslání po nasazení. Vlastní znění upomínky se loguje pod kódem `upominka_vlastni`, ale v seznamu se nezobrazuje, protože není v `EmailoveSablonyKatalog`.
- **Překlopení ročníku se nikam nezapisuje**: `ROCNIK` je konstanta v `nastaveni/verejne-nastaveni-produkce.php` (viz `PREKLOPENI_ROCNIKU_NAVOD.md`), jediná stopa je commit a nasazení. Proto log ukládá ročník ke každému e-mailu a „letos“ je `WHERE rocnik = ROCNIK`, ne datum. Počet „odesláno“ je zpráva bez chyby, příjemci se sčítají jen z odeslaných (zpráva může mít víc příjemců v BCC), selhání je zpráva, která neodešla ani po druhém pokusu.
- Na betě (`MAILY_DO_SOUBORU`) se příjemci mimo povolené role zapíšou do souboru a do logu, ale `GcMail::odeslat()` je přidá jako Bcc do stejné zprávy, která pak jde přes SMTP, takže zpráva se smíšenými příjemci dojde i jim (ověřeno obálkou `Symfony\Component\Mailer\Envelope`). Počty na betě proto neříkají, kolika lidem e-mail opravdu došel.
- Řádek s názvy sloupců pod nadpisem skupiny (seznam e-mailů, Nastavení) má třídu `nazvy-sloupcu` (`admin/files/design/styl.css`): tučně, menší, velkými písmeny, šedě. Tooltipy v záhlaví by to vzhled zdědily, proto je pro `.hint` zvláštní pravidlo.
- **Odeslat test** v detailu e-mailu (POST na `?kod=…&test`, `ZkusebniEmail`): po potvrzení pošle předmět a text tak, jak jsou v editoru, i neuložené, s ukázkovými hodnotami proměnných (`EmailovaSablonaDefinice::ukazky()`, stejné jako v náhledu) na e-mail přihlášeného admina. Server text znovu zkontroluje validátorem a před odesláním ho upraví jako při ukládání (`EmailovaSablonaRepository::normalizuj()`). Na prostředí, kde se e-maily mimo povolené role jen zapisují do souboru (beta, `MAILY_DO_SOUBORU`), odpověď řekne, že e-mail jen skončil v souboru (`VysledekZkusebnihoEmailuEnum::JEN_DO_SOUBORU`), ne že odešel. Na preview e-maily chodí do sdíleného Mailpitu, proto k hlášce o odeslání přibude odkaz na webmail.preview.gamecon.cz. Předmět má předponu `[TEST]` a zpráva nemá kód šablony, takže se nezapočítá do sloupce Odesláno. Editor tlačítko nabízí jen tehdy, když dostane adresu a URL (`EditorSablonyHtml::vykresli()`), takže na stránce vlastního znění upomínky zatím není. Nástroje nad textem jdou za sebou: přepínač Úprava / Náhled, Odeslat test, Vložit symbol, nápověda.
