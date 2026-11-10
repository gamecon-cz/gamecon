TL;DR: Veřejný archiv proběhlých přednášek na `/prednasky/archiv`: vlastní tabulky, přednáška volitelně spárovaná s kartou programu (anotace a štítky se čtou z ní), import historické „Arcitabulky přednášek“ migrací, filtr v Preactu nad kartami z PHP, správa v adminu a doplňování nového ročníku po GC.

## Vstupní body v kódu

- Data: migrace `migrace/2026-11-10-123601_archiv-prednasek.php` (tabulky `archiv_prednasek*`), `Gamecon\ArchivPrednasek\ArchivPrednasekRepository` (zápisy), `SqlStruktura/*`.
- Import: `migrace/2026-11-10-124023_archiv-prednasek-import.php` → `Import\Arcitabulka` (čtení a sjednocení TSV), `Import\ImportArcitabulky` (zápis, kontrola párování), `Import\DataArcitabulky` (všechny mapy a pevná ID). Tabulka beze změny v `migrace/pomocne/archiv-prednasek/arcitabulka-prednasek.tsv`.
- Stránka: větev routeru ve `web/index.php` (`ArchivPrednasekStranka`), modul `web/moduly/archivprednasek.php`, šablona `web/sablony/blackarrow/archivprednasek.xtpl`, styl `web/soubory/blackarrow/archiv-prednasek/archiv-prednasek.less`, data `PrednaskyNaWebu`.
- Filtr: `ui/src/pages/archivPrednasek/` (`filtr.ts` čistá logika s testy, `index.tsx` lišta), sdílené se stránkou linie v `ui/src/pages/filtrStranky/`.
- Odkaz „Archiv přednášek“ v řadě podstránek `/prednasky` (`aktivity.php`, blok `archivPrednasek` v `aktivity.xtpl`).
- Admin: `admin/scripts/modules/web/archiv-prednasek.php` (+ `.xtpl`), logika `SpravaArchivu`, chyby pro admina `ChybaArchivu`.
- Doplnění po GC: `DoplneniZProgramu`, job `admin/cron/jobs/archiv_prednasek_po_gc.php`, volaný z hodinového `admin/cron.php` (ručně `?job=archiv_prednasek_po_gc`), tabulka `archiv_prednasek_zpracovane_aktivity`.

## Datový model

- `archiv_prednasek`: název, rok, `youtube_id` (jen ID videa, adresy skládá `YoutubeOdkaz`), `skryta`, `id_akce`. Unikátní (`rok`, `nazev`): přednášku určuje název a rok (záměr); unikátní `id_akce`.
- **`id_akce` = hlavní aktivita karty** (u instancí `akce_instance.id_hlavni_akce`). Jedna přednáška = jedna karta programu, ne termín (záměr). FK `ON DELETE SET NULL`: smazání hlavní instance spárování zruší (`Aktivita::smaz()` předá hlavní roli jiné instanci, archiv to neví); u proběhlých ročníků vzácné, přednáška pak spadne mezi nespárované.
- `archiv_prednasek_prednasejici`: jméno pro web a volitelně `id_uzivatele`. Na webu se ukazuje vždy uložené jméno, ne `jmenoNaWebu()` (tabulka vědomě používá občanská jména a některé přezdívky jsou nesmysl, např. „Proč je přezdívka povinný údaj?“). Skupina nebo firma je přednášející bez uživatele (záměr), i když má vlastní účet (CGE, Deskofobie, Altar, GameCon).
- **`id_uzivatele` schválně bez unikátního klíče:** `UzivatelSlucovani` převádí všechny FK na uživatele přes `UPDATE IGNORE` a řádek, který narazí na unikátní klíč, smaže. Přednášející by po sloučení dvou účtů zmizel i ze svých přednášek. Jednoho přednášejícího na uživatele hlídá aplikace.
- Klíčová slova: vlastní číselník (`archiv_prednasek_klicove_slovo`, unikátní název bez ohledu na velikost písmen díky collation), vazby M:N. Přednášející M:N s pořadím.
- **Anotace a štítky se nekopírují**, čtou se ze spárované aktivity, aby se oprava v programu propsala (záměr). Aktivity starých ročníků v adminu upravit jdou (`editorZpracuj()` rok nehlídá), důvod ke kopii tedy není.

## Import historické tabulky

- Sloupce A (název), F (přednášející), K (rok), L (klíčová slova), M (YouTube); hodnocení se neimportuje (záměr).
- Sjednocení v kódu (`DataArcitabulky`), tabulka zůstává beze změny, ať jde porovnat: klíčová slova podle mapy (kde to jde, na zápis štítku programu: Larp, Horor, Wargaming, Debata, Panelová diskuse, Dračí doupě, Pán prstenů), jména přednášejících (Jerry/Jerry!, Mackan/mackan, Jezevec/Jezevec_CoM, Ecthelion^2/Ecthelion1, bobaque/Bobaque, Torren/Toren), opravy tří překlepů v názvech. Názvy: spojovník s mezerami → pomlčka (`NazevPrednasky::uprav()`), protože i program míchá „Mistrovství DrD - …“ a „– …“ (záměr).
- „x“ = nevíme; přednášející „x“ = bez přednášejícího. „záznam není“ i „záznam dodáme“ = bez záznamu. Neznámý tvar odkazu import zastaví (výjimka), aby se odkaz neztratil potichu.
- Skupina Majstri svetov přednáší s členy Babcom a Thanatophagous (`CLENOVE_SKUPIN`), aby měli odkaz na medailonek (záměr).
- **Párování pevnými ID, nic se nehledá** (záměr: na ostré má proběhnout přesně to, co se schválilo). Migrace spáruje jen když aktivita s ID existuje a má očekávaný rok a název (porovnání po `NazevPrednasky::uprav()`); přednášejícího napojí jen když uživatel s ID má očekávaný login. Jinak nespárováno / nenapojeno a řádek v `LOGY/archiv-prednasek-import.log`. ID jsou z kopie ostré DB z 2026-11-10.
- **Ročníky do 2017 se nepárují** (záměr: staré ročníky nejsou dost kompatibilní: do 2016 bez štítků, do 2017 bez krátkých anotací, 2009 bez `url_akce`). Napojení přednášejících na uživatele přes vedoucí staré aktivity ale zůstalo (záměr), účty jsou napříč ročníky stejné.
- Přednášky z programu, které v tabulce chyběly (hlavně blok Parconu 2017), migrace přidala jako nespárované (`Z_PROGRAMU`); z let 2018+ jen „Organizace turnajů v deskových hrách“ (spárovaná). Organizační program, vyhlášení, soutěže, živé hraní, placené workshopy a zrušené aktivity typu přednáška do archivu nepatří (záměr).
- Přesná shoda názvu nestačí: „Příběhy impéria“ 2010 se shoduje s RPG hrou, přednáška je „Příběhy Impéria: Křest Rukověti mága“; „Armada Game“ 2011 s turnajem. Proto párování schvaluje člověk.
- Migrace zapisuje přes spojení migrace (`$this->connection`), takže je atomická s její transakcí.

## Veřejná stránka

- **Adresa:** větev routeru ve `web/index.php` (druhá část `archiv` pod linií s typem přednáška). Ne složka `web/moduly/prednasky/`: `CestyKSitemape::isCestaKterouRouterVrati()` bere každou složku v `web/moduly` za modul a vyřadila by ze sitemapy `/prednasky` i `/prednasky/fotogalerie`. Samotný modul (`/archivprednasek`) vrací 404.
- **Název modulu bez pomlčky a podtržítka:** XTemplate povoluje v názvu bloku jen písmena a čísla (`[a-zA-Z][a-zA-Z0-9]*`) a hlavní blok šablony se jmenuje jako modul; `archiv-prednasek` skončil chybou „undefined method parse_archiv-prednasek_…“.
- Kanonická adresa `prednasky/archiv` bez parametrů (filtr obsah nemění, jen schovává); v sitemapě jako cesta (`CestyKSitemape`), modul je v `ModulyVSitemapeTest::MODULY_MIMO_SITEMAPU`.
- Karty vykresluje PHP (funguje i bez JS, hledač vidí obsah), Preact je jen schovává jako na stránce linie. Řazení: rok sestupně, v roce `Collator('cs')`. Skryté přednášky na stránce nejsou.
- Rok odkazuje na `/prednasky?rocnik=Y` jen u roků z přepínače linie (`RocnikyAktivit::nabizene()`), letošní bez parametru; jiný rok by tam tiše přepnul na letošní (2008 v programu není, 2020 se GC nekonal).
- „Karta v programu“: `<url linie aktivity>[?rocnik=Y]#<url_akce>`, jen když stránka linie kartu ukazuje (viditelný stav, ne proběhlá technická, rok z přepínače její linie).
- Přednášející: jméno je odkaz na archiv s filtrem (`?prednasejici=ID`), s JS ho klik přidá do filtru. Medailonek (`/<id>`) jen u účtu se jménem (`Uzivatel::url() !== null`), stejně jako na stránce linie.
- Anotace: krátká jako HTML z programu (stránka linie ji tiskne stejně), dlouhá z Markdownu v `<details>`; bez krátké se rozbalovací blok jmenuje „Anotace“.
- Štítky na kartě: klíčová slova a štítky programu dohromady, stejný název jednou (zápis klíčového slova vyhrává). Bez kategorie „omezení“ a jejích podkategorií (věk, výška), týkají se účasti, ne obsahu (záměr).
- Video: náhled `i.ytimg.com` (`loading="lazy"`, `referrerpolicy="no-referrer"`), přehrávač z `youtube-nocookie.com` až po kliknutí, vedle „Přehrát na YouTube“. Bez JS je náhled odkaz na YouTube. Bez záznamu štítek „bez záznamu“.

## Filtr

- Rok (víc let), přednášející, štítky, „Jen se záznamem“, hledání (název, krátká a dlouhá anotace, bez diakritiky; text z DOM karty, ne podruhé v JSON).
- **Mezi kategoriemi A ZÁROVEŇ, uvnitř kategorie NEBO** (záměr). Štítky jsou jedna kategorie i pro štítky programu; stránka linie má mezi kategoriemi štítků A ZÁROVEŇ, archiv schválně ne.
- **Jeden společný filtr štítků** (klíčová slova + štítky programu, stejný název = jedna volba): staré nespárované přednášky štítky programu nemají, samostatný filtr by je potichu vyřadil (záměr). V adrese jsou štítky podle klíče názvu (`?stitky=rpg,horor`, `StitekNaWebu::klic()` = `slugify()`), protože jedna volba může mít dva zdroje.
- Adresa: `?hledat=…&rok=2024,2025&prednasejici=…&stitky=…&zaznam=1`, zápis `replaceState`. Neznámé hodnoty se při načtení zahodí. Počty u možností jako na linii (bez výběru „kolik zbude“, s výběrem „+kolik přibude“).
- Rok bez zobrazené přednášky schová i nadpis roku.

## Admin

- Modul Web → Archiv přednášek, `pravo: 105`, `prava: 105, 102` (záměr: archiv je obsah webu jako Medailonky, spárování ale dělá ten, kdo zná program). Kdo má jen 102, hlavní položku Web v menu nevidí (menu ukazuje sekci jen s jejím právem), stránka se mu ale otevře.
- Hlavička modulu musí mít `pravo:` i při `prava:` (`AdminMenu::nactiPolozku()` ho čte vždy, bez něj varování a pád menu).
- Seznam s filtrem: rok, spárovaná, záznam, viditelnost, text (název nebo přednášející); skrýt / vrátit tlačítkem v řádku.
- Detail (`?id=…`, nová `?id=nova`): název, rok (2000 až letošní), přednášející (jméno + uživatel přes omnibox), klíčová slova (`<datalist>` existujících, nové stačí napsat), YouTube (jakýkoli tvar adresy, uloží se ID, neplatný odmítne), spárování (omnibox karet programu `?hledatAktivitu=1`, v roce přednášky nebo ve všech ročnících; vymazané pole spárování zruší), skrytí.
- **Přednášející se stejným jménem je tentýž** ve všech přednáškách a změna jeho uživatele platí pro všechny. Napojení se mění jen když admin pole uživatele opravdu změnil (formulář posílá i původní hodnotu): přidat známého přednášejícího s prázdným polem ho neodpojí.
- Spárovat jde jen kartu, která nepatří jiné přednášce (kontrola přes všechny instance); uloží se hlavní aktivita karty.
- Po uložení se smažou přednášející a klíčová slova bez přednášky, ať nestraší v našeptávači.
- Chyba ve formuláři nepřesměruje (`chyba(…, false)`), vyplněné hodnoty zůstanou.

## Doplnění po GC

- Po skončení ročníku založí job v archivu karty typu přednáška, kde aspoň jeden termín proběhl (`StavAktivity::probehnuteStavy()`: zamčená, uzavřená; zamyká je hodinový cron hned po začátku). Jedna karta = jedna přednáška, `id_akce` = hlavní aktivita karty. Aktivity karty jsou hlavní aktivita a termíny typu přednáška; hlavní může mít jiný typ nebo rok, kontrola „už v archivu“ ji proto bere vždy (spárovaná je právě ona). Název z programu (`NazevPrednasky::uprav()`). Klíčová slova a YouTube doplní admin (záměr).
- **Přednášející** = vedoucí všech termínů karty, jeden dotaz na všechny karty. Nejdřív přednášející napojený na stejného uživatele (i se jménem z tabulky), pak přednášející stejného jména jako v adminu a v importu (skupina nebo firma tak zůstane bez uživatele, i když organizuje z vlastního účtu), jinak nový se jménem z `jmenoNaWebu()`, napojený na uživatele.
- **Skončené ročníky** (`getSkonceneRocniky()`): od `DoplneniZProgramu::PRVNI_ROCNIK` (2026, první rok mimo tabulku; starší rozhodují lidé) po poslední skončený. Letošní skončil po `gcBeziDo()`, ale jen když `GC_BEZI_DO` leží v letošním roce. Na ostré je to vlastní hodnota nastavení (2026-07-26 23:59, `rocnik_nastaveni` −1, tedy pro každý ročník), která po překlopení zůstane z loňska, dokud ji někdo nezmění, a někdo může nastavit příští ročník ještě před překlopením. Jinak se proto konec letošního ročníku spočítá jako výchozí hodnota nastavení (`DateTimeGamecon::spocitejKonecGameconu()`, neděle 21:00): neopravená hodnota letošní ročník nezdrží do prosince a nový ročník neskončí dřív, než začal. `rocnik()` je na ostré konstanta `ROCNIK` z kódu a mění ji PR překlopení ročníku (prosinec), ne Nový rok. Loňský ročník se doplní i po překlopení (záměr: 2026 hned po nasazení, ne až po GC 2027).
- **Každá karta jen jednou** (`archiv_prednasek_zpracovane_aktivity`, se všemi aktivitami karty, aby zůstala zpracovaná i po smazání hlavní instance). Rozhodnuté karty vynechá už první dotaz podle řádku hlavní aktivity (hodinový běh tak čte jen nerozhodnuté karty), přesně podle všech aktivit karty je pak hlídá zápis karty. Existující přednášky job nemění (záměr: žádná duplicita, ruční úpravy zůstanou, skrytá se nevrátí). Zaznamená i kartu, která v archivu už je, a kartu přeskočenou kvůli stejnému názvu a roku (s hláškou v logu cronu): bez záznamu by zrušené spárování v adminu vedlo k nové přednášce pro tutéž kartu při dalším běhu. Karta, která proběhne až po prvním běhu, přijde s dalším během, předčasný běh tedy nic neztratí.
- **Selhání:** každá karta má vlastní transakci, chyba jedné ostatní nezastaví. Selhaná karta dostane záznam s chybou (`chyba`, `zpracovano` = čas pokusu) a zkusí se znovu za den (`OPAKOVAT_CHYBU_ZA`), takže chyba, která se opakuje, pošle přes `CronErrorNotifier` mail jednou denně, ne každou hodinu. Záznam chyby nepřepíše aktivitu, kterou mezitím zpracoval jiný běh (jinak by se za den zpracovala znovu). Když selže i záznam chyby (spadlé spojení), job pokračuje dalšími kartami a selhanou kartu zkusí příští běh. Text chyby se před zápisem očistí od bajtů, které nejsou UTF-8 (`mb_scrub()`). Na konci běhu job vyhodí jednu výjimku se selhanými kartami.
- **Souběh** (hodinový cron a ruční `?job=`, nebo pomalý běh a další): karta se v transakci nejdřív zapíše jako zpracovaná, po jednotlivých aktivitách v pořadí ID (`INSERT … ON DUPLICATE KEY UPDATE`, které nahradí jen dřívější chybu). Druhý běh na klíči počká, a když první potvrdí, nic nezmění (0 dotčených řádků) a kartu nechá být; když první selže, kartu převezme. Žádný `DELETE` před zápisem: pod REPEATABLE READ zamkne mezeru u klíče, který ještě neexistuje, a dva běhy pak skončí deadlockem (`1213`, ověřeno dvěma spojeními 2026-11-10). Zámek na klíči primárního klíče zamyká jen řádek, ne mezeru.
- **Skrytě** job založí (záměr: admin ji vidí a jedním klikem vrátí):
  - co nevypadá na přednášku (`DoplneniZProgramu::isPrednaska()`): placená, štítek „Živé hraní“, štítek „Workshop“ bez „Talk“ / „Panelová diskuse“ / „Debata“, a podle názvu (`NAZVY_NEPREDNASKY`, tak se jmenovaly v programu 2009–2022) zrušená (každý tvar „zrušen…“: „ZRUŠENO“, „zrušená“, „ZRUŠENÉ“, „zrušenou“, ne podstatné jméno „zrušení“), zahájení a zakončení festivalu jen jako celý název („Slavnostní zahájení“, „Zakončení GC 2014“, „Zahájení Parconu“, ne „Zahájení kampaně v RPG“), „vyhlášení / vyhlašování výsledků“, soutěž jako podstatné jméno („soutěž“, „soutěže“, ne „soutěžní“, „soutěživost“) a „oběd“. V roce 2026 je to jen „Workshop pro PJe/GMy“ (220 Kč, kapacita 5).
  - kartu bez dokladu, že byla v programu: nikdo se na ni nepřihlásil (`akce_prihlaseni_log`) a `akce_stavy_log` u ní nemá veřejný stav. Hodinový cron (`Aktivita::zamkniZacinajiciDo()`) zamyká i nezveřejněné aktivity (nová), samotné zamčení tedy nestačí. Dřív nestačil ani log stavů: `aktivuj()`, `publikuj()` a `priprav()` do něj nepsaly, jen hromadná aktivace a zamčení (v roce 2025 má aktivaci v logu 3 ze 14 přednášek). S archivem přednášek (konec 2026) začaly psát (`Aktivita::zmenStav()` jde přes `zmenStavNa()` jako zamčení), takže od ročníku 2027 log ukáže i veřejnou přednášku bez přihlášek. Pro 2026 stačí přihlášky, má je každá přednáška 2022–2026.
- **Proč hodinový cron, ne vlastní `?job=`:** joby s `?job=` plánuje cron-job.org (viz `admin/cron.php`), nový název by se tam musel přidat ručně a bez toho by nikdy neběžel. Kontrola v hodinovém cronu je pár dotazů, chyba jde přes `CronErrorNotifier` a hodinový cron nezastaví.
- Ověřeno na kopii ostré DB 2026-11-10 s tímto algoritmem (po kartách, v transakci vrácené na konci, bez přednášek 2026 a bez zpracovaných aktivit, jako první běh na ostré): `rocnik()` 2026, `gcBeziDo()` 2026-07-26 23:59, doplnilo 13 karet, 12 viditelných a skrytý jen „Workshop pro PJe/GMy“ (placený), 14 nových přednášejících, ostatní převzatí z tabulky podle uživatele nebo jména (sirien, Babcom, Thanatophagous, Sniper bez uživatele…), druhý běh nic.

## Gotchas

- Lokální běh importu znovu: smazat řádky archivu a záznam migrace `2026-11-10-124023_archiv-prednasek-import.php` z `migrations`, pak migrace. Aby job po GC doplnil karty od 2026 znovu, smazat i řádky `archiv_prednasek_zpracovane_aktivity`, jinak je nechá být.
- V testovací DB (stavěné z migrací, bez aktivit a uživatelů) import vloží všechny přednášky nespárované a log plný zpráv; testy archivu si tabulky na začátku vyprázdní.
- Dlouhé anotace obsahují interní poznámky `<p hidden>` (např. „Korektura 2025“); jsou skryté, ale hledání je najde (stejně jako na stránce linie).
