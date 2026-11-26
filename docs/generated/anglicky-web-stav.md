TL;DR: Stav anglické verze veřejného webu (`/en/…`) a co chybí, aby šla od ledna nabízet cizincům: co je hotové, ověření proti Trello kartě „Anglický web“, TODO podle priority, rozhodnutí, otevřené otázky pro tým a jak pokračovat. Mechaniku (prefix, cookie, katalogy, přeložená stránka) popisuje [jazyk-webu](jazyk-webu.md), původní plán a analýza jsou v [docs/dvojjazycny-web.md](../dvojjazycny-web.md).

## Stav k 21. 11. 2026

- Všechno je v [PR #1403](https://github.com/gamecon-cz/gamecon/pull/1403), větev `dvojjazycny-web` (bez čísla karty, commity proto nemají prefix). PR je otevřený, bez konfliktů, CI zelené. **Do `main` sloučený není**, na betě ani na ostré angličtina zatím není.
- Preview: <https://dvojjazycny-web.preview.gamecon.cz/en/>, za basic auth brány; organizátor přihlášený v adminu projde bez hesla ([preview-vstup-pres-adminu](preview-vstup-pres-adminu.md)).
- PR založil Geretik (fáze 0 a 1 plánu), zbytek je práce s Claude: review, překlady a tři kola připomínek uživatele.
- Karta týmu: [Trello 563 „Anglický web“](https://trello.com/c/6bhPahq1/563-anglick%C3%BD-web) (board 1G: Obecná organizace). Navázaná IT karta [1002 gamecon.cz EN](https://trello.com/c/bp0v3lBx/1002-gameconcz-en) je soukromá, její obsah tu ověřený není `(nejisté)`.
- Mimo tuto větev: oprava přihlášky, která při každém uložení mazala zájem o pomoc (`pomoc_typ`, sekce „Chceš se zapojit“), je v samostatném [PR #1530](https://github.com/gamecon-cz/gamecon/pull/1530), otevřeném.
- Počty aktivit, štítků a účtů níže jsou z lokální kopie ostré DB (registrace do 9. 11. 2026).

## Co je hotové

- **Jen čeština a angličtina** (`model/Jazyk/Jazyk.php`). `/pl/` a `/de/` dají 404, podle jazyka prohlížeče se nepřesměrovává.
- **Rozhraní pod `/en/`:**
  - titulka, menu, patička;
  - stránky linií (`web/moduly/aktivity.php`): výpis, přihlašovátko, hlavička s vedoucím linie, lišta filtrů (karty / seznam, minulé ročníky), archiv přednášek;
  - celý program v Preactu: legenda a filtry, štítky, názvy linií a tagů, přihlašovátko, náhled aktivity s cenou, okna odhlášení a týmu;
  - účet: registrace (i adresa v cizině, volnější pravidla pro ulici a PSČ v `Uzivatel::registrujUprav()`), nastavení, přihlášení i QR kódem, obnova hesla;
  - přihláška na GameCon (`/prihlaska`) kromě e-shopu, který je v ní česky v `lang="cs"`;
  - přehled financí (`/finance`): částky v CZK i EUR, pokyny k platbě, QR kódy; názvy aktivit, předmětů a poznámky k platbám jsou data a zůstávají česky.
- **Obsah z DB** (sloupce `*_en`, prázdné = česká záloha):
  - `stranky.obsah_en`: 36 stránek, z toho 25 přeložených (praktické informace, kontakty, `legal`, pro sponzory a média, jídelníček, úvody linií, RPG návody…) a 11 fotogalerií linií jako kopie české verze;
  - `akce_typy.*_en` (názvy a krátké popisy linií), `akce_typy_hlavicky.sekce_en` (vedoucí linií), všech 437 tagů (`sjednocene_tagy.nazev_en`) a 50 kategorií (`kategorie_sjednocenych_tagu.nazev_en`);
  - překlady přidávají migrace `migrace/2026-11-20-*`, mění jen `*_en` a párují podle českého klíče (`url_stranky`, `nazev`, `sekce`), ne podle id.
- **Jazyk aktivity** (`akce_seznam.jazyk`: `cs`, `en`, `cs-en`, `nezavisla`; `model/Aktivita/JazykAktivity.php`): pole „Jazyk“ v editoru aktivity (`model/Aktivita/templates/editor-aktivity.xtpl:368`), sloupec v importu a exportu (`ActivitiesImportSqlColumn::JAZYK`, `ExportAktivitSloupce::JAZYK`), ruční filtr `?jazykAktivity=` a štítky `EN` / `CZ/EN` / tichý `CZ` v programu.
- **Vyhledávače:** `hreflang` a anglickou kanonickou adresu mají jen stránky prohlášené za přeložené (`Modul::setObsahPrelozeny()`), ostatní pod `/en/` odkazují kanonicky na českou verzi.
- **Terminologie je rozhodnutá** (viz [Rozhodnutí](#rozhodnutí)). **Všechny překlady ale psala AI a žádný člověk je neprošel.**

## Ověření proti kartě Trello 563

Karta: anglická verze stránek do Vánoc, test mezi Vánoci a Novým rokem, od ledna lákat cizince, programové sekce potřebují čas připravit program pro cizince. Komentář sirien (20. 11.): jen CZ + EN, na anglickém webu neukazovat aktivity jen v češtině (výchozí filtr „en accessible“ / „en only“) a ty aktivity mají mít anotace anglicky. Ověřeno 21. 11. 2026 proti této větvi.

| Požadavek | Stav | Poznámka |
|---|---|---|
| Anglická verze stránek | částečně | Rozhraní, účet, program, finance a 25 stránek ano. E-shop přihlášky, e-maily, pravidla turnajů a anotace aktivit česky (TODO 5, 9, 10, 12). |
| Hotovo do Vánoc | zatím ne | Vše jen v PR #1403, ne v `main` (TODO 8). |
| Test mezi Vánoci a Novým rokem | čeká | Na preview už jde (za basic auth), na betě až po sloučení. |
| Připraveno na lákání od ledna | částečně | Chybí výchozí filtr a data jazyka (TODO 1–3) a lidská kontrola překladů (TODO 6). |
| Sekce mají čas připravit program pro cizince | ne | Pole „Jazyk“ v editoru i importu je, sekce ale nemají pokyn a značí angličtinu štítky (TODO 2, 4). |
| Jen CZ + EN (sirien) | splněno | `/pl/`, `/de/` → 404. |
| Na EN webu výchozí filtr, bez jen-českých aktivit (sirien) | ne | `/en/program` výchozí jazyk nemá, stránky linií filtr jazyka nemají vůbec, titulka na anglický program neodkazuje (TODO 3). |
| Filtr „en accessible“ / „en only“ | částečně | Filtr v programu existuje, data jsou ale prázdná: všech 650 aktivit 2026 má `jazyk = 'cs'` (viz rozpor níže). |
| Anotace anglických aktivit anglicky (sirien) | ne | Technicky nejde, `akce_seznam` nemá `*_en` sloupce. Dřívější rozhodnutí „nepřekládat“ je teď otevřená otázka (TODO 4, 5). |
| Vysvětlit v zahraničí, co je GameCon | částečně | Titulka, praktické informace, kontakty, pro sponzory a média anglicky. Chybí stránka o GameConu a historii a věta, že program je většinou česky (TODO 7). |

### Rozpor: štítky „Eng Only“ / „Eng Friendly“ vs. `jazyk`

- Sekce značí anglické aktivity štítky. 2026: „Eng Only“ 4 aktivity (RPG Court of Eternals, Royal Blood, Spellbound!, Wandering Code), „Eng Friendly“ 8 řádků = 6 aktivit (jóga má po dvou instancích, dál wargaming, epické deskovky a RPG). 2025: 10 a 2 řádky.
- Atribut `akce_seznam.jazyk` je u všech 650 aktivit 2026 `cs`: `migrace/2026-10-19-165528_jazyk-aktivity.php` přidala sloupec s výchozím `cs` a štítky nepřevedla. Žádný kód štítky nečte, mají jen `nazev_en` („English only“, „English-friendly“).
- Důsledek pod `/en/`: anglické RPG Court of Eternals má v programu štítek „CZ“ a filtr „in English“ ukáže prázdný program (jazykově nezávislé aktivity taky žádné nejsou).
- Program 2027 v DB zatím není (nejvyšší `rok` je 2026). Jak bude vypadat, rozhodne, jestli sekce zadají `jazyk` (TODO 2).

## TODO

Dokument sleduje jen anglickou podobu webu; organizační témata z karty (pozvánky vydavatelů a sponzorů, zahraniční PJ, kapacita na překlady) sem nepatří `(záměr)`.

Velikost: malé = hodiny až den, střední = dny, velké = týden a víc `(nejisté, odhad)`. Kdo: **tým** rozhoduje, **vývoj** píše kód, **obsah** píše a kontroluje texty (sekce, překladatelé).

### A. Před lákáním (do Vánoc, k 1. 1.)

- [ ] **1. Jediný zdroj jazyka aktivity** (malé · tým). Doporučení: atribut `jazyk`, protože ho už zná editor, import, export, filtr i štítky v programu. Štítky „Eng Only“ / „Eng Friendly“ zrušit nebo jen dopočítávat (otevřená otázka).
- [ ] **2. Převést štítky na `jazyk`** (malé · vývoj + obsah). Po bodu 1.
  - migrace pro 2026: „Eng Only“ → `en`, „Eng Friendly“ → `cs-en` (nebo `nezavisla`, otevřená otázka); páruje přes `akce_sjednocene_tagy`, instance mají vlastní řádky; starší ročníky volitelně (minulé ročníky na stránkách linií);
  - pokyn sekcím pro 2027: vyplnit „Jazyk“ v editoru nebo sloupec „Jazyk“ v importu, místo štítků.
- [ ] **3. Výchozí filtr jazyka na anglickém webu** (střední · vývoj, chování určí tým). Až po bodu 2, jinak ukáže prázdný program.
  - `/en/program`: výchozí `jazykAktivity` v `PROGRAM_VYCHOZI_NASTAVENI` (`Program::gameconKonstanty()`, `model/Aktivita/Program.php:124`, klíč = query parametr Preactu) jen pod angličtinou; angličtina + CZ/EN + jazykově nezávislé (`jazykyFiltruAktivity()`, `ui/src/utils/jazykAktivity.ts:54`), viditelné „Show all activities“;
  - filtr jazyka na stránkách linií (`ui/src/pages/aktivityFiltr/filtr.ts`); PHP dnes jazyk karty jen vypisuje jako štítek (`web/moduly/aktivity.php`, `jazykTextu`), filtr ho potřebuje v datech karty;
  - odkaz z titulky (`web/sablony/blackarrow/titulka.xtpl`) na program pro anglicky mluvící.
- [ ] **4. Rozhodnout anglické anotace** (malé · tým): ano / ne, pro které aktivity (jen `en` a `cs-en`?), kdo je píše, kdo dělá korekturu, termín pro sekce.
- [ ] **5. Anglické anotace aktivit** (velké · vývoj). Po bodu 4.
  - sloupce `nazev_akce_en`, `popis_kratky_en`, `popis_en` v `akce_seznam` (dnes žádné `*_en`);
  - editor (`editor-aktivity.xtpl`), import a export (`ActivitiesImportSqlColumn`, `ExportAktivitSloupce`, Google Sheets v `admin/scripts/modules/aktivity/_GoogleSheets`), kopírování do instancí (`Aktivita::instancuj()`);
  - vykreslení: program (statický JSON `ProgramStaticFileGenerator::aktivitaDoPole()` vedle českého textu jako `linie` / `linieEn`, výběr v Preactu), stránky linií, náhled aktivity, `lang` textu.
- [ ] **6. Lidská kontrola AI překladů** (střední · obsah, opravy zapíše vývoj novou migrací).
  - `legal` a v něm věta, že při rozporu platí česká verze (dnes tam není);
  - sporné tagy: „Přemýšlecí“ → Thinking, „Vztahová“ → Relationship-driven, „Žánrovka“ → Genre larp, „Bardi a balady“;
  - stránky z CMS; zastaralé údaje převzaté z češtiny (kontakty mají datum ročníku 2026, RPG návody a boží larp jsou psané pro dřívější ročník, překlepy v „Jak si vybrat hru“).
- [ ] **7. Říct cizinci, že program je většinou česky, a stránka o GameConu** (střední · obsah + vývoj).
  - věta na titulce, v programu nebo v praktických informacích: většina programu je česky, hledej `EN` a `CZ/EN`;
  - stránka o GameConu a jeho historii pro partnery v zahraničí; dnes je jen „pro sponzory a média“ (`pro-sponzory-a-media`).
- [ ] **8. Sloučit PR #1403 do `main`** (malé · tým rozhodne, vývoj sloučí přes „Rebase and merge“), aby test mezi Vánoci a Novým rokem běžel na betě. Sloučení nasadí `/en/` i na ostrou, přepínač jazyka v menu je pak vidět všem.

### B. Před otevřením registrací (2026 to bylo 13. 5., `REG_GC_OD`; termín 2027 `(nejisté)`)

- [ ] **9. E-maily anglicky** (velké · vývoj + obsah). `emailova_sablona` má klíč jen `kod`, jazyk přidat do klíče; 37 šablon přeložit; e-maily mimo šablony viz [emailove-sablony](emailove-sablony.md). Jazyk příjemce je `uzivatele_hodnoty.jazyk`: všech 6137 účtů má `cs`, nový účet dostane jazyk registrace.
- [ ] **10. E-shop v přihlášce na GameCon** (velké · vývoj). Předměty, ubytování, jídlo, vstupné: `model/Shop/templates/shop-*.xtpl`, texty v `Shop` a `ShopUbytovani`, `web/soubory/blackarrow/shop/*.js`, povinné údaje pro ubytování.
  - názvy předmětů jsou i logika (`'Spacák'`, `Shop::bezDne()`, `LIKE 'Snídaně%'`), proto `nazev_en` nejlépe až v novém e-shopu ([PR #1467](https://github.com/gamecon-cz/gamecon/pull/1467));
  - ubytovací blok se dnes záměrně vykresluje česky (`AktualniJazyk::provedVJazyce()` v `ShopUbytovani`); po překladu e-shopu zmizí z přihlášky `jazykEshopu`.
- [ ] **11. Chyby API programu a názvy týmů** (střední · vývoj). Chyby přihlašování a týmů jsou česky: `web/moduly/api/aktivitaTym.php`, `model/Aktivita/AktivitaTym.php`, `hlaska()` z `Aktivita::prihlasovatkoZpracujBezBack()` (`nastaveni/hlasky/`); Preact je jen zobrazí. Náhodné názvy týmů: `ui/src/utils/nazevTymu.ts`, `model/Aktivita/NazevTymuGenerator.php`.

### C. Průběžně a drobnosti

- [ ] **12. Zbylé stránky z CMS** (střední · obsah): pravidla turnajů (`drd/*`, `dnd/*`, `legendy/*`), `organizatori/jak-se-stat-organizatorem`, `manual-vypravece`, `o-workshopech-na-gc`, `soutez`, `wargaming/malovaci-vyzva`; blog a novinky jsou data. Nadpis `/en/sponzori` je česky natvrdo (`web/sablony/blackarrow/sponzori.xtpl:3`, malé · vývoj).
- [ ] **13. Anglický obsah v adminu** (střední · vývoj): editor anglických názvů a popisů linií (`akce_typy.*_en`) a kategorií tagů (`kategorie_sjednocenych_tagu.nazev_en`), dnes jen migrací; přehled nepřeloženého a zastaralého anglického obsahu. Dnes jde anglicky upravit stránku z CMS („Obsah anglicky“), vedoucí linií (Web → Hlavičky linií) a název tagu (Štítky).
- [ ] **14. `/en/program` jako přeložená stránka** (malé · vývoj + obsah).
  - dnes má českou kanonickou adresu, žádný `hreflang`, `og:locale` `cs_CZ` a obsah v `<div lang="cs">`, i když je rozhraní anglicky (`web/moduly/program.php` nevolá `setObsahPrelozeny()`);
  - text „Program se načítá ...“ (`model/Aktivita/Program.php:36`);
  - anglické praktické informace uvádějí česká tlačítka se závorkou („*Založit nový tým* (Create a new team)“), rozhraní už je anglicky;
  - sitemapa nemá žádnou `/en/` adresu (`model/Web/CestyKSitemape.php`).
- [ ] **15. Fotogalerie linií** (malé · vývoj): upozornit v editoru, že anglická kopie zastará, nebo anglickou verzi skládat za běhu z české (nahradit tři texty).
- [ ] **16. Anglické uvozovky v anglických textech mimo `*_en`** (malé · vývoj, u medailonků rozhodne tým). [Trello 1137](https://trello.com/c/Jxi4xST1): `Smartyp` v `main` mění každé `"` na `„“` a i správné `“…”` přepíše na české. Tato větev to řeší pro `stranky.obsah_en` (jazyk obsahu v `Stranka::html()`) a náhled v adminu; po sloučení jde kartu 1137 zavřít s odkazem na PR. Česky převedené zůstávají anglické texty v českých sloupcích, na českém i anglickém webu:
  - anotace aktivit: `Aktivita::popis()` volá `markdown()` bez jazyka; pro `jazyk = 'en'` předat `Jazyk::ANGLICTINA`, `cs-en` je smíšený text a zůstane česky. Projeví se až po TODO 2 (dnes mají všechny aktivity `cs`), s anglickými anotacemi (TODO 5) to odpadne;
  - medailonky (`model/Uzivatel/Medailonek.php`) jazyk nemají, zahraniční vypravěč píše anglicky: pole s jazykem, nebo smířit se s českými uvozovkami;
  - okrajově novinky a anotace v archivu přednášek.

## Rozhodnutí

- **Jen čeština a angličtina.** Polština nebo němčina by naznačovala, že ty návštěvníky aktivně podporujeme (sirien na kartě 20. 11.). `(záměr)`
- **Terminologie:** „vypravěč“ je **Game Master** (ne storyteller), „přednáška“ je **talk** (ne lecture), britská angličtina (programme, organise). Dál „deskoherna“ = board game library, „přihláška“ = GameCon registration, „infopult“ = info desk, „Celohra“ zůstává jménem. Platí pro katalogy, Preact i obsah stránek. `(záměr)`
- **Anglická titulka** je česká titulka s přeloženými texty. Ručně psaná CMS stránka `en` je zastaralá a nepoužívá se, `/en/en` přesměruje na `/en/`. `(záměr)`
- **Štítek „CZ“** u česky vedené aktivity v programu na anglickém webu má být nenápadný: šedý text, ne černý rámeček jako `EN`. `(záměr)`
- **Nepřejmenovávat kód kvůli angličtině.** Tým je český, metody a identifikátory zůstávají, jak jsou, i když porušují pravidlo `get…`/`is…`. `(záměr)`
- **Netvrdit, že je GameCon pro všechny věky.** V angličtině je vynechané „bez ohledu na věk“, česky to zůstalo. `(záměr)`
- **Koruny anglicky jako „CZK 1,234.50“** (kód měny před částkou, anglický zápis čísla), eura „€41.32“, stejně jako „CZK 2,500“ v anglických stránkách z CMS. Česky beze změny („1 234,50 Kč“). E-maily a admin mají částky česky vždy.
- **Nepřeložený obsah** se pod `/en/` ukazuje česky s poznámkou (`stranka.jen_cesky`).
- **Fotogalerie linií** mají anglický obsah jako kopii české verze (migrace `…124420`), aby tlačítko „Photo gallery“ bylo anglicky. Kdo galerii mění, musí změnit i pole „Obsah anglicky“; jinak anglická verze zastará a nic na to neupozorní (TODO 15).

## Otevřené otázky pro tým

- **Výchozí filtr na `/en/`:** zahrnout jazykově nezávislé aktivity (ruční filtr „in English“ je zahrnuje)? Viditelný přepínač „Show all activities“? Platí i na stránkách linií? Slováci a Poláci, kteří zvolí angličtinu, ale česky rozumí, by bez přepínače přišli o většinu programu.
- **Štítky vs. `jazyk`:** nechat obojí a synchronizovat, nebo štítky zrušit? Znamená „Eng Friendly“ `cs-en`? U jógy, wargamingu a epických deskovek se možná víc hodí `nezavisla`.
- **Anglické anotace:** dosud platilo „popisy aktivit se nepřekládají, místo nich je jazyk aktivity“ (rozhodnutí z 20. 11.); sirien chce u anglických aktivit anotace anglicky. Pro které aktivity, kdo je píše (vypravěč, sekce, AI s korekturou), kdo je kontroluje a do kdy?
- **Kdy sloučit PR #1403:** hned kvůli testu na betě (sloučení nasadí `/en/` i na ostrou), nebo až po kontrole překladů?
- **Kdo projde AI překlady**, hlavně `legal`, a má v něm být věta o přednosti české verze?

## Jak pokračovat

- **Nový text rozhraní:** klíč do obou katalogů (`symfony/translations/messages+intl-icu.{cs,en}.yaml`), v Preactu do `ui/src/i18n/{cs,en}.ts`. `PrekladyKatalogTest` hlídá shodu klíčů, použití a ICU. Pravidla escapování jsou v [jazyk-webu](jazyk-webu.md).
- **Nový překlad obsahu z DB:**
  - nová migrace, která nastaví jen `*_en` a páruje podle českého klíče, escapuje přes `$this->connection->real_escape_string()` (`Migration::q()` nemá parametry);
  - stránku, jejíž hlavní obsah je teď anglicky, prohlas za přeloženou (`setObsahPrelozeny(true)`). Stránky z CMS to dělají samy podle `Stranka::isPrelozena()`.
- **Migrace se evidují podle cesty.** Úprava migrace, která už někde proběhla (preview větve), se tam znovu nespustí. Proto opravy překladů jdou do nové migrace; vzor je `migrace/2026-11-20-123256_anglicka-terminologie.php`, která přepíše hodnoty a migrace s překlady stránek přes `require` pustí znovu.
- **UI bundly:** každý commit, který mění `ui/` nebo LESS importovaný do UI, musí mít přebuildované `web/soubory/ui` a `admin/files/ui` (`make check-ui-bundles`).
- **Kontrola dat jazyka:** `SELECT jazyk, COUNT(*) FROM akce_seznam WHERE rok = … GROUP BY jazyk` a štítky „Eng Only“ / „Eng Friendly“ přes `akce_sjednocene_tagy` a `sjednocene_tagy.nazev`.
- **Lokální vývoj s reálným obsahem:** `bin/stahni-zalohu-db.sh` a `bin/import-db.sh` (dump bez `CREATE DATABASE`/`USE`, viz `CLAUDE.md`), pak `make migrations-run`. Worktree potřebuje vlastní porty v `.env` (`WEB_HTTP_HOST_MAPPING_PORT` a další), jinak koliduje s hlavním checkoutem.
- **Webové testy** po několika bězích padají na „Too many connections“. Pomůže `apachectl -k graceful` v kontejneru `web`, s kódem to nesouvisí.
- **Po každém kole** aktualizuj tento dokument: TODO odškrtni, nové nálezy doplň, rozhodnutí týmu přesuň z otevřených otázek do rozhodnutí s `(záměr)`.

## Historie

- 25. 10. 2026: Geretik zakládá PR #1403: jazyk aktivity, prefix `/en/`, katalogy, přepínač, anglická titulka jako CMS stránka `en`.
- 20. 11. 2026, první kolo:
  - merge `main`;
  - review třemi agenty a opravy: kanonická adresa a `hreflang` jen u přeložených stránek, cookie a účet jen z volby jazyka, filtr nezávislých aktivit, escapování `t()`, CHECK přes `BINARY`, časová zóna dat, statický JSON programu česky;
  - překlad titulky, linií, CMS stránek, tagů a filtrů programu.
- 20. 11. 2026, druhé kolo podle připomínek uživatele: Game Masters a Talks, tichý štítek CZ, vedoucí linií, karty a seznam, minulé ročníky, fotogalerie, archiv přednášek, registrace, přihlášení a obnova hesla. Ke každému kolu proběhlo code review novým agentem.
- 20. 11. 2026, třetí kolo podle připomínek uživatele: celé rozhraní programu v Preactu (legenda, přihlašovátko, okna týmů), přihláška na GameCon kromě e-shopu, přehled financí; nahrání souhlasu rodičů po vypršení session vede na přihlášení. Větev je lineárně přerebasovaná na `main`, protože repozitář slučuje jen přes „Rebase and merge“ a merge commity mainu ve větvi GitHub neuměl přehrát. Oprava mazání zájmu o pomoc v přihlášce šla zvlášť do PR #1530.
- 21. 11. 2026: ověření proti Trello kartě 563 a komentáři sirien; nalezen rozpor štítků „Eng Only“ / „Eng Friendly“ s atributem `jazyk`, dokument přepsán na TODO list.
