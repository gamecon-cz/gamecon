TL;DR: Veřejný archiv proběhlých přednášek na `/prednasky/archiv`: vlastní tabulky, přednáška volitelně spárovaná s kartou programu (anotace a štítky se čtou z ní), import historické „Arcitabulky přednášek“ migrací, filtr v Preactu nad kartami z PHP, správa v adminu a doplňování nového ročníku po GC.

## Vstupní body v kódu

- Data: migrace `migrace/2026-11-10-123601_archiv-prednasek.php` (tabulky `archiv_prednasek*`), `Gamecon\ArchivPrednasek\ArchivPrednasekRepository` (zápisy), `SqlStruktura/*`.
- Import: `migrace/2026-11-10-124023_archiv-prednasek-import.php` → `Import\Arcitabulka` (čtení a sjednocení TSV), `Import\ImportArcitabulky` (zápis, kontrola párování), `Import\DataArcitabulky` (všechny mapy a pevná ID). Tabulka beze změny v `migrace/pomocne/archiv-prednasek/arcitabulka-prednasek.tsv`.
- Stránka: větev routeru ve `web/index.php` (`ArchivPrednasekStranka`), modul `web/moduly/archivprednasek.php`, šablona `web/sablony/blackarrow/archivprednasek.xtpl`, styl `web/soubory/blackarrow/archiv-prednasek/archiv-prednasek.less`, data `PrednaskyNaWebu`.
- Filtr: `ui/src/pages/archivPrednasek/` (`filtr.ts` čistá logika s testy, `index.tsx` lišta), sdílené se stránkou linie v `ui/src/pages/filtrStranky/`.
- Odkaz „Archiv přednášek“ v řadě podstránek `/prednasky` (`aktivity.php`, blok `archivPrednasek` v `aktivity.xtpl`).
- Admin: `admin/scripts/modules/web/archiv-prednasek.php` (+ `.xtpl`), logika `SpravaArchivu`, chyby pro admina `ChybaArchivu`.

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

## Gotchas

- Lokální běh importu znovu: smazat řádky archivu a záznam migrace `2026-11-10-124023_archiv-prednasek-import.php` z `migrations`, pak migrace.
- V testovací DB (stavěné z migrací, bez aktivit a uživatelů) import vloží všechny přednášky nespárované a log plný zpráv; testy archivu si tabulky na začátku vyprázdní.
- Dlouhé anotace obsahují interní poznámky `<p hidden>` (např. „Korektura 2025“); jsou skryté, ale hledání je najde (stejně jako na stránce linie).
