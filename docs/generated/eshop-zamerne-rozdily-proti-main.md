# Záměrné rozdíly chování proti `main`

TL;DR: seznam **všech vědomých** rozdílů v chování mezi e-shopovou větví ([PR #1467](https://github.com/gamecon-cz/gamecon/pull/1467)) a `main`. **Rozdíl, který tu není, je chyba**: buď se opraví na shodu s `main`, nebo se se souhlasem vlastníka produktu zapíše sem. Shoda s `main` je výchozí stav a nezapisuje se. *(záměr — zadáno uživatelem 2026-11-14)*

## Pravidla pro čtení i psaní

- **Rozdíl** je cokoli, co účastník, obsluha nebo správce pozná jinak než na `main`: pravidlo, cena, co jde a nejde, co se zapíše do databáze, co ukáže report, jak se ovládá obrazovka.
- **Nepatří sem** vnitřní přestavba bez dopadu na chování (entity, DTO, migrace, názvy tříd, výkon) ani **shoda s `main`**, i když se k ní došlo opravou. Příklad: mřížka „Prodej" na infopultu nejdřív ignorovala pracovního uživatele, kdežto `main` prodával jemu; to byla chyba, je opravená a řádek tu nemá.
- **Opačný příklad:** číslo „zbývá" u noci. `main` připočítával účastníkovi jeho vlastní postele zpět, větev ne; to je vědomý rozdíl a má řádek (Z3).
- **Nový řádek** patří do téhož PR, který rozdíl zavádí. Nese, co dělá `main`, co větev, proč a čím je to podložené (rozhodnutí s datem, měření proti `main`, dokument). Čísla se nepřečíslovávají a řádky se nemažou: zrušený rozdíl se označí „zrušeno" s datem.
- **Řádek, který vlastník nechce,** je chyba; smaže se spolu s opravou kódu.
- **Nalezený rozdíl, o kterém nikdo nerozhodl,** je chyba, dokud se nerozhodne; do té doby stojí v poslední kapitole.

## Základ srovnání

Referencí je aktuální `main`. Po každém rebase se větev porovnává s aktuálním `main`: co `main` mezitím přidal, musí větev zachovat, a každý rozpor je chyba, dokud není zapsaný tady. Chování `main` se měří diferenčním ověřením ([eshop-diferencni-overeni](eshop-diferencni-overeni.md)): dva worktree, tentýž dump, stejné scénáře.

Seznam vznikl 2026-11-14 z nálezů diferenčního ověření ([nalezy](eshop-diferencni-scenare/nalezy.md) a scénáře), z tematických dokumentů v `docs/generated/`, ze zpráv commitů větve a z rozhodnutí při práci na mřížce ubytování na infopultu. Značky ve sloupci „Zdroj": **rozhodnutí RRRR-MM-DD** = řekl vlastník produktu, **scénář N.N / nález** = změřeno proti `main`, odkaz na dokument = popsáno tam při přepisu.

## Přihláška účastníka a mřížky ubytování a jídla

| # | `main` | větev | zdroj |
|---|---|---|---|
| Z1 | Přihláška se odešle jedním tlačítkem a celá se zapíše v jedné transakci. Na obrazovkách Uživatel a Infopult se ubytování a jídlo ukládají tlačítky „Uložit" (`zpracujUbytovani`, `zpracujJidlo`). | Každé kliknutí v mřížce ubytování, matici jídla, merchi a vstupném se uloží hned přes API (`/cart/*`, pro obsluhu admin API); tlačítka „Uložit" u ubytování a jídla na Uživateli a Infopultu nejsou. Sekce jsou nezávislé, transakce přes celou přihlášku není. Odeslání přihlášky zapíše jen přihlášení na GC, sekce ve formuláři zůstávají jako zakázaný `noscript` záložní zápis. | [prihlaska-odeslani-objednavky](prihlaska-odeslani-objednavky.md) |
| Z2 | Formulář měl na každý den jeden přepínač, takže druhý typ pokoje na tutéž noc nešel vybrat. | Mřížka (noc × typ) má zaškrtávátka, proto to hlídá mřížka (nový výběr na tutéž noc nahradí původní) i server (`AccommodationWriter` odmítne sadu, do které se dvojice nově přidává, a pojmenuje všechny dotčené noci). **Odškrtnutí se neodmítá nikdy:** kdo už dvojici má, může ji odškrtávat po jedné a uložit i jen spolubydlícího. | rozhodnutí 2026-11-11; scénář 4.9; `AccommodationWriterTest` |
| Z3 | U noci, kterou má účastník objednanou, se počet „zbývá" počítal včetně jeho vlastní postele, takže se po zaškrtnutí nezměnil. | „Zbývá" je počet postelí **pro ostatní**: zaškrtnutí ubere jednu, odškrtnutí ji vrátí. Poslední postel, kterou má účastník sám, ukáže „zbývá 0", ne „vyprodáno" (noc zůstává odškrtnutelná). Platí v pohledu účastníka i obsluhy. | rozhodnutí 2026-11-11; scénář 4.10; `AccommodationAvailabilityTest`, `AccommodationProviderTest` |
| Z4 | Snídaně kryté hotelovou nocí se z požadavku tiše vyřadila a nikde se nezapamatovala; snídani poznával název začínající „Snídaně". | Snídaně se zapíše a hned ji zruší `BreakfastCanceller` (noc kryje ráno následujícího dne); zrušená se zapamatuje a když krycí noc zmizí, účastník ji může tlačítkem vrátit. Totéž tlačítko („Objednat je znovu") má obsluha na Uživateli a Infopultu a smí vrátit i po termínu jídla, po `nabizet_do` a pozastavenou, jako prodává matice jídel; stažená se nevrací nikomu. Snídani pozná štítek `snidane`; produkt bez něj, nebo varianta bez vyplněného dne, se z rušení i z nabídky vrácení tiše vynechá. | scénář 7.5; rozhodnutí 2026-11-14 (vrácení i pro obsluhu); [admin-objednavky-za-ucastnika](admin-objednavky-za-ucastnika.md) §6 |
| Z5 | Zadržená zásoba pro organizátory neexistuje, platí jen kapacita a celková zásoba. | Část zásoby ubytování a zboží lze zadržet pro organizátory (`reserved_for_organizers` na produktu nebo variantě): účastníkovi se odečte (nevidí ji a nekoupí ji), organizátor na ni dosáhne, pult ji obchází a mřížka v adminu ukazuje obsluze zvlášť, kolik jí je. | [prodej-na-pultu-kfc](prodej-na-pultu-kfc.md); `AccommodationAvailabilityTest` |

## Admin: Uživatel a Infopult

| # | `main` | větev | zdroj |
|---|---|---|---|
| Z6 | Zápis z obou admin obrazovek kapacitu ubytování nehlídal nikomu; tlačítko přeplnění nabízelo UI jen šéfovi infopultu a ručně poskládaný požadavek přeplnil noc komukoli (`// není zabezpečeno`). | Rozhoduje server: `AccommodationWriter::save()` přeplní jen s právem `MUZE_PRETIZIT_UBYTOVANI` (1040). Obsluha bez něj dostane 403 „přeplnit smí jen šéf infopultu", účastník 409 „obsazené" (přeplnění se mu nenabízí); zápis kapacity je pod zámky `CapacityManager`. | [admin-objednavky-za-ucastnika](admin-objednavky-za-ucastnika.md) §4; scénář 7.10 a 7.11 |
| Z7 | Pravomoci šéfa infopultu se řídily rolí (`jeSefInfopultu()`). | Jsou to tři samostatná práva: přeplnit ubytování (1040), zamykat a odemykat týmy (1041), nemusí potvrzovat na infopultu (1042). Nese je role Šéf infopultu, takže pro jejího držitele se nic nemění; kód se ptá na právo, ne na roli. | commit „Authenticate the API with JWT and give rights codes"; migrace `prava-sefa-infopultu` |
| Z8 | Ubytování se na obou obrazovkách upravovalo legacy tabulkou nocí (`ubytovani_tabulka`, `Shop::ubytovaniHtml()`). | Mřížka (Preact), ve které přepíná noc celá dlaždice, ne jen zaškrtávátko; dlaždice na sebe těsně navazují (odsazení je uvnitř buňky). **Jen v adminu:** popis typu pokoje je v nápovědě za otazníkem u názvu (`.hinted` / `.hint`) a slevená cena (přeškrtnutá původní i nová dohromady) má nápovědu s názvem pravidla, které cenu snížilo (`discountReason`, u ceny bez slevy chybí); účastník vidí popis pod názvem a nápovědu u ceny nemá. | rozhodnutí 2026-11-11 |

## Prodej na pultu (KFC)

| # | `main` | větev | zdroj |
|---|---|---|---|
| Z9 | Anonymní prodej se zapisoval na účet `SYSTEM` (id 1) a hotovost se hned připsala do `platby` s poznámkou „anonymní prodej". | Kupujícím je sdílený účet `ANONYM` (id 0, bez rolí, jeden pro všechny ročníky); starší anonymní nákupy migrace přeřadila ze `SYSTEM` na `ANONYM`. Hotovost anonymního prodeje se dál připisuje do `platby`. | [prodej-na-pultu-kfc](prodej-na-pultu-kfc.md); nález N1 a N2 |
| Z10 | `Shop::prodat()` účtoval syrovou cenu, hlídal jen kapacitu varianty a termín prodeje merche neřešil. | Pult prodává přes tentýž košík: cena prochází slevovým enginem podle kupujícího, kapacitu hlídá `CapacityManager`, termín prodeje platí. Obsluha smí obejít tři hlídače (`deadline`, `organizer_stock`, `restricted_product`) a každé obejití se zapíše do `shop_nakupy.override_log`; celkovou zásobu obejít nejde. | [prodej-na-pultu-kfc](prodej-na-pultu-kfc.md); `KfcSaleApiTest` |
| Z11 | `Shop::prodat()` zapisuje cenu nezaokrouhlenou. | Hotovost anonymního prodeje se zaokrouhluje na celé koruny, běžně od poloviny nahoru a **každý kus zvlášť** (`KfcSaleProcessor::naCeleKoruny()`); prodej na účet uživatele a e-shop haléře nechávají. | [prodej-na-pultu-kfc](prodej-na-pultu-kfc.md) (záměr) |
| Z12 | Protizápis k prodeji držel jen shodný čas a částka; zrušení ani změna prodeje se do platby nepromítly. | Platba nese `platby.order_id` (`ON DELETE SET NULL`). | [prodej-na-pultu-kfc](prodej-na-pultu-kfc.md) |
| Z13 | Mřížka neříkala, komu prodává. | Hlavička říká „Prodej uživateli … (ID …)" nebo „Anonymní prodej"; ID posílá stránka, ne relace, takže záložka otevřená u jiného uživatele prodá tomu, jehož jméno obsluha v hlavičce vidí. Po prodeji uživateli se stránka načte znovu, aby ukázala nový zůstatek. | rozhodnutí 2026-11-10 |
| Z27 | Dlaždice předmětu se nikdy nešedí a ukazuje popisek buňky, i když je předmět archivní nebo nemá varianty; klik jde dál do prodeje. Vyprodaný předmět nemá „(vyprodáno)". | Dlaždice předmětu, který se prodat nedá, zešedne a klik nic neudělá. Archivní předmět a předmět bez variant nese svůj název, ne popisek buňky, a důvod „(archivní)" / „(nelze prodat)"; vyprodaný si popisek nechává a píše „(vyprodáno)". | zadáno 2026-11-16 (název místo „N/A"); [prodej-na-pultu-kfc](prodej-na-pultu-kfc.md); `ObchodMřížka.test.tsx` |

## Ceny a slevy

| # | `main` | větev | zdroj |
|---|---|---|---|
| Z14 | Cena se počítala za běhu z živých rolí a nikdo nic neoznamoval. | Změna role přepočítá cenu **rozpracované** objednávky aktuálního ročníku (dokončená drží svou cenu) a když ji zdraží, pošle se e-mail CFO, až po commitu, který zdražení způsobil. | commit „Calculate discounts from rules held in the database"; `PriceIncreaseNotifierTest` |
| Z15 | Letošní placku a kostku zdarma vybírala heuristika (nákup v dřívějším roce, pořadí nahrání). | Pravidlo slevy ročníku jmenuje přesný kód předmětu; zapisuje ho import e-shopu ze sloupce `je_letosni_hlavni`. Dokud kód není (nový ročník před importem), pravidlo nic nedá: raději nikdo nedostane předmět zdarma, než aby ho dostal ke špatnému předmětu; chybějící kód hlásí stránka importu. Pro 2026 jsou jmenované tytéž předměty, které vybral `main`. Ostatních jedenáct pravidel slev platí v každém ročníku (`year = -1`) a ročník, který chce jedno změnit, si založí vlastní řádek. | [letosni-model-predmetu](letosni-model-predmetu.md); rozhodnutí 2026-11-10 |

## Katalog, ročník, import a reporty

| # | `main` | větev | zdroj |
|---|---|---|---|
| Z16 | Ročník produktu nesl sloupec `model_rok`. | Sloupec neexistuje, ročník je `COALESCE(YEAR(archived_at), ROCNIK)` a produkt existuje trvale. **Cena:** statistika „Prodané předměty" počítá placky, kostky a trička jako `rok nákupu = ročník produktu`, takže po přepnutí ročníku a květnovém importu ukáže řádek minulého ročníku u nich nuly (ověřeno čtením dotazu; jiné dotazy se stejným výrazem se nezkoumaly). | [prepnuti-rocniku-a-eshop](prepnuti-rocniku-a-eshop.md); rozhodnutí 2026-11-08 |
| Z17 | Katalog každého ročníku se zakládal zvlášť. | Při přepnutí ročníku se katalog nearchivuje; nový ročník se importuje až před otevřením e-shopu (květen) a import archivuje s `NOW()` vše, co v listu chybí. Rozpracované objednávky se dokončují úplně první krok uzavření ročníku. | [prepnuti-rocniku-a-eshop](prepnuti-rocniku-a-eshop.md); [Issue #1308](https://github.com/gamecon-cz/gamecon/issues/1308); rozhodnutí 2026-11-08 |
| Z18 | Každá velikost a každá noc je samostatný produkt („Dámské tílko červené S"); typ a podtyp určují, co produkt je. | Velikosti a noci jsou **varianty** jednoho produktu a název produktu je bez velikosti („Dámské tílko červené"); cena a kapacita jsou na variantě (`NULL` = zdědí z produktu). Typ a podtyp nahradily štítky (`ProductTagCode`) s povolenými kombinacemi. Nákup ukazuje na variantu a lidem se produkt nákupu ukazuje jako `product_id`; quick report „nakupy" (88) má produkt a variantu ve zvláštních sloupcích (`nazev`, `varianta`). Starší ročníky mají jednu výchozí variantu na produkt. | scénář 5; [nakup-produkt-a-varianta](nakup-produkt-a-varianta.md); [tricka-velikosti-a-varianty](tricka-velikosti-a-varianty.md) |
| Z19 | Řádek importního listu je velikost nebo noc (`nazev`, `kod_predmetu`). | Řádek listu je varianta (`product_code`, `variant_code`, `stav_varianty`); starý formát import odmítne. Report `finance-report-eshop` je šablona a jde bez změny naimportovat zpět; produkt, který v listu chybí, se archivuje, chybějící varianta se vyřadí (`RETIRED`) a minulý ročník import neoživí. | [import-a-export-eshopu](import-a-export-eshopu.md); `EshopImporterTest` |
| Z20 | Změny nákupu se neevidují (kromě archivu zrušených nákupů). | Každá změna řádku `shop_nakupy` (vznik, změna, smazání) se zapíše do `shop_order_item_log` ve stejné transakci, s tím, kdo a odkud; selhání logu shodí i nákup. Do `shop_nakupy` se proto nesmí psát ručním SQL. Anonymizovaná databáze log vyprázdní a vynuluje `shop_order.roommate` a `shop_nakupy.override_log`. | [audit-zmen-nakupu](audit-zmen-nakupu.md); `OrderItemAuditListenerTest` |
| Z21 | Stránka Finance → Shop (právo 108) je tabulka předmětů; na každém řádku se mění počet kusů celkem (`kusu_celkem`) a stav a ukládá se tlačítkem. | Editor produktů (Preact nad API s JWT): produkt s variantami a štítky, kapacita, cena a stav leží na variantě. Podštítek `snidane` nastavuje zaškrtávátko „Je to snídaně", ostatní (`mikina`, `spacak`, `tricko_*`) sloupec `podstitky` v importu. Prodaný produkt ani prodanou variantu nelze smazat. | commit „Let the desk edit a participant's orders"; [admin-objednavky-za-ucastnika](admin-objednavky-za-ucastnika.md) §6 |

## Odstraněno

| # | `main` | větev | zdroj |
|---|---|---|---|
| Z22 | Stránka Finance → Hromadné rušení objednávek (a API) zrušila objednávky lidem pod zadaným zůstatkem. | Stránka i API jsou pryč, neplatiče řeší automatická úloha (ubytování a aktivity jim zruší, případně je odhlásí z GC). `BulkCancelService` zůstává pro `Shop`. | rozhodnutí „ruční hromadné zrušení nepotřebujeme" (`MISSING_FEATURES.md`); `docs/napoveda/finance.md` |
| Z23 | Stránka Peníze → Převést bonus za vedení aktivity na peníze (virtuální předmět `PROPLACENI_BONUSU` a platba „Převedení bonusu"). | Stránka i její ajax jsou pryč. | commit „Sell at the counter through the same cart"; [eshop-diferencni-overeni](eshop-diferencni-overeni.md) |
| Z24 | Sekce přihlášky vykreslovaly šablony `shop-*.xtpl` a skripty `shop-*.js`. | Šablony i skripty jsou smazané; sekce jsou Preact komponenty nad API. Legacy `Shop` je jen čte (infopult, reporty); zbývá z něj ruční prodej v adminu (`Shop::prodat()`) a rušení neplatičů. | [prihlaska-odeslani-objednavky](prihlaska-odeslani-objednavky.md) |

## Technické, s dopadem na provoz a vývoj

| # | `main` | větev | zdroj |
|---|---|---|---|
| Z25 | Legacy databázová vrstva na mysqli; `dbQuery()` hádá z prvního slova dotazu, jestli čte, nebo zapisuje. | Legacy vrstva běží na PDO a sdílí jedno spojení s Doctrine. `dbQuery()` je odstraněn: volající říká `dbFetch()` (čte) nebo `dbExecute()` (zapisuje, vrací počet změněných řádků); `dbUpdate()` a `dbInsertUpdate()` vracejí počet změněných řádků. Kód z `main`, který `dbQuery()` volá, po sloučení spadne a test, který prohledá zdroje, ho jmenuje. `migrations:continue` běží ve striktním SQL režimu, nasazení ne. | [PR #1475](https://github.com/gamecon-cz/gamecon/pull/1475); `DbFetchExecuteTest` |
| Z26 | API neexistuje. | Košíkové a admin API pod `/symfony/api` za JWT; token nese verzi, takže odhlášení ostatních zařízení, změna a obnova hesla zneplatní všechny tokeny uživatele; obyčejné „Odhlásit" ukončí jen tento prohlížeč jako na `main` a tokeny ostatních zařízení nechá (jinak by přestal fungovat e-shop na zařízeních, která zůstávají přihlášená). Celé `/symfony/*` jde na front controller, nikdy na soubor ze stromu `symfony/` (`SymfonyPodAdresaremTest`). Odmítnutí požadavku je výjimka z `symfony/src/Exception/` a převede se na 400, 403 nebo 409 s českým textem z katalogu `errors.cs.yaml`; porucha je 500 bez detailu. | [chyby-v-nove-vrstve](chyby-v-nove-vrstve.md) |

## Shodné s `main`, i když to tak nevypadá

Tyhle věci se při ověřování opakovaně objevily jako „chyba" a nejsou. Nejsou to rozdíly, takže nemají čísla.

- Obsluha na Uživateli a Infopultu objedná ubytování i jídlo **po termínu prodeje** (nález N12; `main` posílal `muzeEditovatUkoncenyProdej` a `ubytovaniBezZamku`) a zapisuje i po konci GC (scénáře 8.3 a 8.4). U ubytování se `nabizet_do` ignoruje a noc zamyká jen stav `POZASTAVENY`.
- Účastník po termínu jídla jídlo **nekoupí ani nezruší**, pult ano (nález N13; `Shop.php` legacy zamykal jídlo bez výjimky pro koupené).
- Ubytování minimálně na dvě noci (jednu s právem 1037) a nocí, které na sebe navazují, hlídal i `ShopUbytovani`.
- Role jen z minulých ročníků nedávají organizátorské výsady ([Issue #1365](https://github.com/gamecon-cz/gamecon/issues/1365)).
- Infopult s vybraným uživatelem prodává jemu na jeho účet, bez připsání hotovosti.
- Ceny a slevy se při ověření 2026-09-17 shodovaly ve všech porovnaných případech (`bin-diff/porovnej-ceny.sh`); reporty BFGR a stravenky vyšly po scénáři 8 bajt po bajtu stejné.
- Věta v `docs/napoveda/infopult.md` „Zrušit jiné ubytování než neděli může pouze šéf infopultu" popisuje pravidlo, které v kódu není na `main` ani na větvi.

## Otevřené, nerozhodnuté

Rozdíly, které se našly při psaní tohoto dokumentu a o kterých nikdo nerozhodl. Dokud nepadne rozhodnutí, jsou to chyby: buď se opraví na shodu s `main`, nebo se přesunou do tabulek výš.

- **Mřížka v adminu nabízí obsluze víc, než uměl `main`.** Na Infopultu ukazuje editovatelné pole „Na pokoji s:" (spolubydlící, kterého si zvolil účastník) i zaškrtávátko „Ubytování nechci"; na Uživateli zaškrtávátko „Ubytování nechci". `main` je na těchto obrazovkách jen zobrazoval (na Infopultu soupis lidí na pokoji, na obou „nechce ubytování: ano/ne"); pole spolubydlícího editoval jen Uživatel ([admin-objednavky-za-ucastnika](admin-objednavky-za-ucastnika.md) §3).
