TL;DR: Stav anglické verze veřejného webu (`/en/…`): co je hotové, co zůstává česky, rozhodnutí, která padla s uživatelem, a jak v práci pokračovat. Jak to funguje technicky, popisuje [jazyk-webu](jazyk-webu.md); celkový plán a analýza jsou v [docs/dvojjazycny-web.md](../dvojjazycny-web.md).

Stav k 20. 11. 2026: všechno je v [PR #1403](https://github.com/gamecon-cz/gamecon/pull/1403) (větev `dvojjazycny-web`, bez Trello karty, proto commity nemají prefix). PR založil Geretik (fáze 0 a 1 z plánu). Zbytek přidala práce s Claude: review, merge `main`, opravy po review, překlad obsahu a druhé kolo podle připomínek uživatele. Do `main` zatím sloučený není.

## Co je hotové

- **Jazyk aktivity** (`akce_seznam.jazyk`, `Gamecon\Aktivita\JazykAktivity`): editor, import/export, filtr a štítky v programu. Filtr „anglicky“ i „česky“ nechá jazykově nezávislé aktivity.
- **Rozhraní pod `/en/`:**
  - menu, patička a titulka;
  - stránky linií (`web/moduly/aktivity.php`): rozhraní výpisu, přihlašovátko, hlavička s vedoucím linie, lišta filtrů v Preactu včetně karet a seznamu a minulých ročníků, archiv přednášek;
  - program v Preactu: filtry, štítky, názvy linií a tagů;
  - účet: registrace, nastavení, přihlášení, přihlášení QR kódem, obnova hesla.
- **Obsah z DB** (sloupce `*_en`, NULL nebo prázdné = česká záloha):
  - `stranky.obsah_en`: 25 stránek, mimo jiné praktické informace, kontakty, `legal`, jídelníček, úvody linií a RPG návody. K tomu fotogalerie linií jako kopie české verze.
  - `akce_typy.*_en`: názvy a krátké popisy linií.
  - `akce_typy_hlavicky.sekce_en`: vedoucí linií.
  - `sjednocene_tagy.nazev_en` a `kategorie_sjednocenych_tagu.nazev_en`: všech 437 tagů a 50 kategorií.
  - Překlady přidávají migrace `migrace/2026-11-20-*` a mění jen `*_en`. Párují podle českého klíče (`url_stranky`, `nazev`, `sekce`), ne podle id.
- **Cizinci se zaregistrují:** pro „(jiný stát)“ platí volnější pravidla pro ulici a PSČ (`Uzivatel::registrujUprav()`), Česko a Slovensko beze změny.
- **Vyhledávače:** `hreflang` a anglickou kanonickou adresu mají jen stránky prohlášené za přeložené (`Modul::setObsahPrelozeny()`). Ostatní pod `/en/` odkazují kanonicky na českou verzi.

## Rozhodnutí (záměr uživatele)

- **Terminologie:** „vypravěč“ je **Game Master** (ne storyteller), „přednáška“ je **talk** (ne lecture), britská angličtina (programme, organise). Dál „deskoherna“ = board game library, „přihláška“ = GameCon registration, „infopult“ = info desk, „Celohra“ zůstává jménem. Platí pro katalogy, Preact i obsah stránek. `(záměr)`
- **Anglická titulka** je česká titulka s přeloženými texty. Ručně psaná CMS stránka `en` je zastaralá a nepoužívá se, `/en/en` přesměruje na `/en/`. `(záměr)`
- **Štítek „CZ“** u česky vedené aktivity v programu na anglickém webu má být nenápadný: šedý text, ne černý rámeček jako `EN`. `(záměr)`
- **Nepřejmenovávat kód kvůli angličtině.** Tým je český, metody a identifikátory zůstávají, jak jsou, i když porušují pravidlo `get…`/`is…`. `(záměr)`
- **Netvrdit, že je GameCon pro všechny věky.** V angličtině je vynechané „bez ohledu na věk“, česky to zůstalo. `(záměr)`
- **Popisy aktivit se nepřekládají.** Místo toho je jazyk aktivity. Ostatní nepřeložený obsah se pod `/en/` ukazuje česky s poznámkou.
- **Fotogalerie linií** mají anglický obsah jako kopii české verze (migrace `…124420`), aby tlačítko „Photo gallery“ bylo anglicky. Kdo galerii mění, musí změnit i pole „Obsah anglicky“; jinak anglická verze zastará a nic na to neupozorní.

## Co zbývá (nejdřív to podstatné)

- **Projít překlady.** Psala je AI. Hlavně `legal` (zvážit větu, že při rozporu platí česká verze) a sporné tagy („Přemýšlecí“ → Thinking, „Vztahová“ → Relationship-driven, „Žánrovka“ → Genre larp, „Bardi a balady“).
- **Přihláška na GameCon** (`/prihlaska`, e-shop, ubytování, jídlo, trička, finance): odhad 300–400 textů. Názvy předmětů jsou v e-shopu i logika (`'Spacák'`, `Shop::bezDne()`), proto `nazev_en` až v novém e-shopu (PR #1467). Ubytovací blok se dnes záměrně vykresluje česky přes `AktualniJazyk::provedVJazyce()` v `ShopUbytovani`.
- **E-maily:** fáze 5 z plánu, navázat na šablony e-mailů v DB (PR #1362) s jazykem v klíči. Jazyk příjemce je `uzivatele_hodnoty.jazyk`.
- **Zbytek Preact programu:** přihlašovátko v buňce, týmy a modální okna.
- **CMS podstránky bez překladu:** pravidla turnajů DrD, D&D a Legend, archivní stránky, novinky a blog.
- **Sitemapa** zatím neobsahuje `/en/` adresy, stačí `hreflang` v HTML.
- **Zastaralé údaje v českých textech** (převzaté i do angličtiny): kontakty mají datum ročníku 2026, RPG návody a boží larp jsou psané pro dřívější ročník, v „Jak si vybrat hru“ jsou překlepy.

## Jak pokračovat

- **Nový text rozhraní:** klíč do obou katalogů (`symfony/translations/messages+intl-icu.{cs,en}.yaml`), v Preactu do `ui/src/i18n/{cs,en}.ts`. `PrekladyKatalogTest` hlídá shodu klíčů, použití a ICU. Pravidla escapování jsou v [jazyk-webu](jazyk-webu.md).
- **Nový překlad obsahu z DB:**
  - nová migrace, která nastaví jen `*_en` a páruje podle českého klíče, escapuje přes `$this->connection->real_escape_string()` (`Migration::q()` nemá parametry);
  - stránku, jejíž hlavní obsah je teď anglicky, prohlas za přeloženou (`setObsahPrelozeny(true)`). Stránky z CMS to dělají samy podle `Stranka::isPrelozena()`.
- **Migrace se evidují podle cesty.** Úprava migrace, která už někde proběhla (preview větve), se tam znovu nespustí. Proto opravy překladů jdou do nové migrace; vzor je `migrace/2026-11-20-123256_anglicka-terminologie.php`, která přepíše hodnoty a migrace s překlady stránek přes `require` pustí znovu.
- **UI bundly:** každý commit, který mění `ui/` nebo LESS importovaný do UI, musí mít přebuildované `web/soubory/ui` a `admin/files/ui` (`make check-ui-bundles`).
- **Lokální vývoj s reálným obsahem:** `bin/stahni-zalohu-db.sh` a `bin/import-db.sh` (dump bez `CREATE DATABASE`/`USE`, viz `CLAUDE.md`), pak `make migrations-run`. Worktree potřebuje vlastní porty v `.env` (`WEB_HTTP_HOST_MAPPING_PORT` a další), jinak koliduje s hlavním checkoutem.
- **Webové testy** po několika bězích padají na „Too many connections“. Pomůže `apachectl -k graceful` v kontejneru `web`, s kódem to nesouvisí.

## Historie

- 25. 10. 2026: Geretik zakládá PR #1403: jazyk aktivity, prefix `/en/`, katalogy, přepínač, anglická titulka jako CMS stránka `en`.
- 20. 11. 2026, první kolo:
  - merge `main`;
  - review třemi agenty a opravy: kanonická adresa a `hreflang` jen u přeložených stránek, cookie a účet jen z volby jazyka, filtr nezávislých aktivit, escapování `t()`, CHECK přes `BINARY`, časová zóna dat, statický JSON programu česky;
  - překlad titulky, linií, CMS stránek, tagů a filtrů programu.
- 20. 11. 2026, druhé kolo podle připomínek uživatele: Game Masters a Talks, tichý štítek CZ, vedoucí linií, karty a seznam, minulé ročníky, fotogalerie, archiv přednášek, registrace, přihlášení a obnova hesla. Ke každému kolu proběhlo code review novým agentem.
