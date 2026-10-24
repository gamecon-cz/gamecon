TL;DR: Jak se účastník dostane na konkrétní pokoj. Účastník si koupí *postel daného typu* na noci a v přihlášce se kódem připojí ke **skupině spolubydlících** (dřív jen volný text); pokoje přiděluje člověk ručně v Excelu (export Reportu ubytování se sloupcem `skupina` → doplnit `pokoj` → import). Na hotelu skupina drží celý pokoj od založení, takže skupin není víc než pokojů. Systém nezná budovy, pokoje ani buňky jako entity.

## Datový model

- **Objednávka = postel na noc.** `shop_predmety` s `typ = TypPredmetu::UBYTOVANI`, jeden předmět na typ × noc (`ubytovani_den` 0 = st–čt … 4 = ne–po). Typ je kód předmětu bez 3znakové přípony dne (`2L_ct` → `2L`, `Hs-2L-ct` → `Hs-2L`), viz `ShopUbytovani::dejIdsPredmetuUbytovaniPodleKoduTypu()`. `kusu_vyrobeno` = počet postelí, ne pokojů.
- **Poznámka ke spolubydlícím** (dřív jediné místo pro přání spolubydlících) = `uzivatele_hodnoty.ubytovan_s`, VARCHAR(255) volný text. **Není vázané na ročník**: přání z loňska zůstane, dokud ho účastník nepřepíše. Nečistí se nikde kromě anonymizace.
- **Přidělený pokoj** = tabulka `ubytovani` (PK `rok, id_uzivatele, den`), sloupec `pokoj` je volný řetězec (v praxi `A112`, `B206`, `C315`; prefix `S…` pro spacákové místnosti). Písmeno budovy je jen konvence v textu, nikde se neparsuje.
- Pokoj se zapisuje na každou objednanou noc stejný (`ShopUbytovani::ulozPokojUzivatele()`), tedy model „jeden pokoj na celý pobyt“. `Pokoj::zUzivatele()` to předpokládá (`MAX(pokoj)`).

## Skupiny spolubydlících

Vstupní bod `model/Ubytovani/UbytovaciSkupiny.php`, tabulka `ubytovani_skupiny_clenove` (`rok, id_uzivatele` PK, `kod_skupiny`, `pripojen`). Řádek = členství; samostatná tabulka skupin není, skupina existuje, dokud má člena.

- Jeden skupinu založí (dostane 6znakový kód), ostatní se připojí kódem. Kód, ne hledání podle jména: souhlas dává to, že kód člověk dostal, a stránka nevystavuje seznam uživatelů. (záměr)
- Ve skupině musí mít všichni **stejný typ ubytování** (kód předmětu bez přípony dne) a na všechny noci jeden typ; **rozdílné noci jsou jen varování**. (záměr, výchozí volba bez potvrzení pořadatelů)
- Kapacita = číslo před `L` v kódu typu (`2L` → 2, `Hs-2L` → 2, `Hdb-1L` → 1, `spacak` → bez limitu), `UbytovaciSkupiny::maxClenu()`. Na 1L skupinu založit nejde. Člen bez objednaného ubytování postel nezabírá.
- Ze skupiny se vypadne: změnou typu, když ostatní mají jiný (`zkontrolujPoZmeneUbytovani()` volané z `ShopUbytovani::zpracuj()` při každém uložení ubytování, i z infopultu), zrušením ubytování a odhlášením z GC (`Uzivatel::odhlasZGc()`). Sám člen skupiny při změně typu zůstává.
- Na webu jdou skupiny měnit do konce prodeje ubytování, v adminu (`admin/uzivatel`) kdykoli; infopult skupinu jen zobrazuje.
- Chybný kód nebo plná skupina = varování, zbytek přihlášky se uloží (`ChybaUbytovaciSkupiny` se chytá v `ShopUbytovani::zpracujSkupinu()`).
- Import ubytování skupiny nečte ani nemění; když v importu někomu změníš typ, skupina zůstane a v přehledu ukáže varování.
- Buňky (2L+2L) skupiny neumí, píše se to do poznámky (`ubytovan_s`). (záměr: později)

## Hotel: skupina drží celý pokoj

Vstupní bod `model/Ubytovani/HotelovePokoje.php`. Hotelových pokojů je málo, proto skupina na hotelu (předmět s `podtyp = 'hotel'`, typ s 2+ postelemi) zabere celý pokoj hned při založení. Postele pokoje, které zatím nikdo ze skupiny neobjednal, drží pro ty, kdo se připojí kódem. Skupin na hotelu tak nikdy není víc než pokojů. (záměr)

- Kapacita zůstává v postelích (`kusu_vyrobeno`). Obsazeno = prodané postele + držené postele. Skupina drží pokoj na každou noc, kdy v něm někdo z ní spí: držené = postelí na pokoji − členů skupiny s tou nocí.
- Jednotlivec bez skupiny si postel na hotelu koupit smí a spolubydlícího mu přidělí pořadatelé. Počítání v postelích pak přesně odpovídá pokojům: 2 × skupiny + jednotlivci ≤ 2 × pokoje ⇔ skupiny + ⌈jednotlivci/2⌉ ≤ pokoje. (záměr, výchozí volba bez potvrzení pořadatelů)
- Postel zůstává držená do konce prodeje ubytování. Potom se k ní kódem nikdo nepřipojí a obsadit ji může doplnění samotářů (níže). Mřížka (web, infopult, admin) držené postele odečítá jen do konce prodeje, pak na ně infopult smí dát kohokoli; kontrola v přihlášce i dávka samotářů počítají pokoj skupiny dál, protože fyzicky ho zabírá. (záměr; otevřená otázka, jestli zakladatel nemá platit celý pokoj, když na volnou postel nikdo nezbude)
- Kolej drží jen postele jako dřív, skupina tam nic neblokuje. 1L hotel se netýká: postel = pokoj a skupinu tam založit nejde.
- **Kontrola jen na webové přihlášce** (`ShopUbytovani::zpracuj()` s `hlidatKapacituUbytovani`). Admin a infopult kapacitu nehlídají ani u postelí, takže skupinu tam jde založit i na plném hotelu.
- Kontroluje se **výsledný stav po zápisu** (`HotelovePokoje::preplnenePoZmene()`): odmítne se změna, po které je noc přes kapacitu a zároveň plnější než předtím. Pokrývá to založení, připojení s nocí, kdy skupina pokoj nemá, odchod ze skupiny s ponecháním hotelu (skupina dál drží postel a odcházející potřebuje další) i noc přeplněnou ručně v adminu (neblokuje nikoho, kdo ji nezvětší). Odmítnutí = `Chyba`, přihláška se celá vrátí jako u vyprodaného předmětu. Varování skupiny (chybný kód) se přidají do textu chyby, aby bylo jasné proč.
- Zamyká se jen při objednávce hotelu (podle `podtyp` v DB, ne podle předmětů formuláře; `FOR UPDATE` na hotelových předmětech ročníku, drží do commitu přihlášky), zrušení hotelu obsazenost zvětšit nemůže. Počty se čtou zamykacím čtením, které vidí commitnutá data i přes snapshot transakce.
- **Gotcha – rozsah zámků:** zamykací čtení zamkne každý řádek, kterým projde. `shop_predmety` nemá index na `typ/podtyp/model_rok`, `FOR UPDATE` podle těch podmínek by zamklo všechny předměty všech ročníků (a zablokovalo každý nákup). Proto se ids hotelu čtou bez zámku a zamykají se přes PK. Nákupy se čtou jen přes index předmětu, členství přes PK. Ze stejného důvodu `opust()` a `odhlasSamotare()` mažou jen řádek, který existuje: DELETE chybějícího klíče zamkne mezeru a dvě přihlášky se o ni zablokují.
- Připojení ke skupině čte ubytování členů zamykacím čtením: kdo se připojil těsně předtím, si postel koupil až po snapshotu transakce a bez toho by se do 2L skupiny vešli tři.
- **Formulář:** plná noc, kde zbylé postele drží skupiny, má u rádia `data-jen-s-kodem-skupiny="1"` a pod polem pro kód vysvětlení. JS ji odemkne, jakmile je kód vyplněný; jestli ji skupina opravdu drží, ověří server. Člen skupiny postele vlastní skupiny vidí jako volné (`drzenePostele()` bez jeho skupiny).
- **Formulář a celý pokoj:** člen skupiny má zamčenou noc hotelu, kdy skupina na pokoji nikoho nemá a nezbývá celý pokoj (přidáním noci by ho skupina začala držet). Kdo ve skupině není, má u nocí s volnými postelemi, ale méně než na pokoj, `data-malo-na-pokoj="1"`: JS je zamkne po zaškrtnutí „založit novou skupinu“ a ukáže vysvětlení. Server to hlídá stejně, formulář jen předejde vrácení celé přihlášky.
- Náhradníci (fronta na uvolněný hotel) zatím nejsou. (záměr: až nad přepsaným e-shopem, větev `1274-přepsat-e-shop`, který ubytování přepisuje do Symfony a skupiny zatím nemá)

## Samotáři: „přijedu sám, spolubydlící mi přidělte“

Vstupní bod `model/Ubytovani/DoplneniSamotaru.php`, přání v tabulce `ubytovani_samotari` (`rok, id_uzivatele` PK, `zapsan`). Kdo nemá s kým bydlet, zaškrtne to v přihlášce místo skupiny; po konci prodeje ho dávka doplní na pokoj. (záměr)

- Přání a členství ve skupině se vylučují: založení i připojení přání smaže (`UbytovaciSkupiny::pridejClena()`), odškrtnutí taky. Smaže ho i zrušení ubytování nebo změna na typ, pro který se spolubydlící nepřidělují (`zkontrolujPoZmeneUbytovani()`), a odhlášení z GC. Zapsat se jde jen s jedním typem na všechny noci a na pokoj pro víc lidí (ne 1L, ne spacák), `prihlasSamotare()`.
- **Dávka se spouští ručně až po konci prodeje** (admin → Finance, box nad importérem ubytování), před ním ji stránka nepustí: do té doby drží skupiny volné postele pro kamarády s kódem. (záměr) Patří před stažení reportu ubytování; jde spustit znovu.
- **Cizí lidi jen se stejným pohlavím** (`uzivatele_hodnoty.pohlavi`). Kdo pohlaví nemá, na pokoj nepůjde. (záměr)
- Nejdřív **do existujících skupin** (i kamarádů), ale jen když skupina na pokoji spí ve všechny noci samotáře a každou má volnou postel; skupina se smíšeným pohlavím nebo typem nebere nikoho. Na hotelu tak samotář obsadí postel, kterou skupina držela, a hotel se nezaplní víc. Z víc skupin ta, kde samotář zmešká nejmíň nocí. Delší pobyty jdou první.
- Zbytek **do nových skupin** podle typu a pohlaví, s kým má nejvíc společných nocí. Nikdo nedostane pokoj sám a každý má společnou noc s každým na pokoji. Na hotelu nová skupina zabírá celý pokoj, proto se každá kontroluje jako v přihlášce (`HotelovePokoje::preplnenePoZmene()`, savepoint) a přeplnění se vrátí.
- Kdo se nikam nevejde, čeká dál: box ho vypíše a Report ubytování má sloupec `ceka_na_spolubydlici`. Přidělí se ručně.
- Doplnění se nikomu neoznamuje e-mailem; samotář uvidí skupinu a jména spolubydlících v přihlášce.

## Toky

- **Objednávka na webu:** `model/Shop/templates/shop-ubytovani.xtpl` + `web/soubory/blackarrow/shop/shop-ubytovani.js`; skupinu zpracuje `ShopUbytovani::zpracujSkupinu()`, pole „Poznámka ke spolubydlícím“ (dřív „Na pokoji chci být s“) ukládá `ulozSKymChceBytNaPokoji()` (loguje do auditu osobních údajů).
- **Infopult / admin uživatele:** stejné ubytování přes `admin/scripts/modules/_submoduly/ubytovani_tabulka.php`; ruční přidělení pokoje jednomu člověku `Pokoj::ubytujNaCislo()` (formulář „Nastavení pokojů“ v `infopult.xtpl` a `uzivatel.xtpl`); výpis spolubydlících podle čísla pokoje `Pokoj::zCisla()`.
- **Hromadné přidělení:** `admin/scripts/zvlastni/reporty/finance-report-ubytovani.php` (export) → `admin/scripts/modules/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.php` (import). Import čte sloupce **podle názvu z hlavičky** (povinné `id_uzivatele, prvni_noc, posledni_noc, pokoj, typ`), ostatní sloupce ignoruje → přidat sloupec do reportu je bezpečné, přejmenovat ne. Import přepíše i objednávku (typ + noci) a `ubytovan_s`.

## Kdo čte pokoj / přání spolubydlících

| Místo | Čte |
|---|---|
| Report ubytování (+ import zpět) | `ubytovani.pokoj`, `ubytovan_s`, `skupina`, `spolubydlici_ve_skupine`, `ceka_na_spolubydlici` |
| Report ubytovaných cizinců | `ubytovani.pokoj` |
| BFGR (`model/Report/BfgrReport.php`, „Chci bydlet s“, „Skupina spolubydlících“, „Ostatní ve skupině“) | `ubytovan_s`, skupina |
| Report potvrzení pro mladší 15 let | `ubytovan_s`, `spolubydlici_ve_skupine` |
| Infopult | `ubytovani` přes `Pokoj`, skupina přes `UbytovaciSkupiny::prehled()` |
| Admin uživatele | `ubytovani` přes `Pokoj`, skupina ve formuláři ubytování |
| Anonymizace (`AnonymizovanaDatabaze`, `AnonymizovanyUzivatel`) | maže `ubytovan_s` |

Sloupce skupiny dávají reporty přes poddotazy `UbytovaciSkupiny::sqlKodSkupiny()` a `sqlSpolubydliciVeSkupine()` (ostatní členové s ubytováním jako „Jméno Příjmení (ID)“).

BFSR, `Accounting`, statistiky a stravenky pracují s `shop_nakupy`, pokoje je nezajímají.

## Gotchas

- Hlášky ke skupině (založena, připojeno, členství skončilo) `ShopUbytovani::zpracuj()` jen schová; zobrazí je `Shop::oznamZpravyUbytovani()`, které volající volá **až po commitu** (přihláška, admin uživatele, infopult, `AbstractTestPrihlaska`). Jinak by po vrácení přihlášky zůstalo „skupina je založená“. Nové místo, které volá `zpracujUbytovani()`, ho musí zavolat taky, jinak se hlášky ztratí.

- `ShopUbytovani::mozniUzivatele()` při každém vykreslení ubytování načte jména a loginy všech uživatelů do JSON, šablona proměnnou `{uzivatele}` ale nepoužívá. Mrtvý dotaz; kdyby se znovu zapojil, vynese osobní údaje všech uživatelů do stránky.
- Slučování uživatelů (`UzivatelSlucovani`) i anonymizace (`AnonymizovanaDatabaze`) najdou tabulky s FK na `uzivatele_hodnoty` samy, `ubytovani_skupiny_clenove` ani `ubytovani_samotari` tedy nepotřebují zvláštní péči; při sloučení dvou členů různých skupin zůstane členství nového uživatele, staré zmizí kaskádou.
- Ve vícelůžkových pokojích ročníku 2025 bylo zhruba 45 % smíšených podle pohlaví (lokální DB), pohlaví se tedy při přidělování striktně neodděluje.
