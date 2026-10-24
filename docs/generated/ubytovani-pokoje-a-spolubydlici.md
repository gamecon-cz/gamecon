TL;DR: Jak se účastník dostane na konkrétní pokoj. Účastník si koupí *postel daného typu* na noci a v přihlášce se kódem připojí ke **skupině spolubydlících** (dřív jen volný text); pokoje přiděluje člověk ručně v Excelu (export Reportu ubytování se sloupcem `skupina` → doplnit `pokoj` → import). Systém nezná budovy, pokoje ani buňky jako entity.

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

## Toky

- **Objednávka na webu:** `model/Shop/templates/shop-ubytovani.xtpl` + `web/soubory/blackarrow/shop/shop-ubytovani.js`; skupinu zpracuje `ShopUbytovani::zpracujSkupinu()`, pole „Poznámka ke spolubydlícím“ (dřív „Na pokoji chci být s“) ukládá `ulozSKymChceBytNaPokoji()` (loguje do auditu osobních údajů).
- **Infopult / admin uživatele:** stejné ubytování přes `admin/scripts/modules/_submoduly/ubytovani_tabulka.php`; ruční přidělení pokoje jednomu člověku `Pokoj::ubytujNaCislo()` (formulář „Nastavení pokojů“ v `infopult.xtpl` a `uzivatel.xtpl`); výpis spolubydlících podle čísla pokoje `Pokoj::zCisla()`.
- **Hromadné přidělení:** `admin/scripts/zvlastni/reporty/finance-report-ubytovani.php` (export) → `admin/scripts/modules/_ubytovani-a-dalsi-obcasne-infopultakoviny-import-ubytovani.php` (import). Import čte sloupce **podle názvu z hlavičky** (povinné `id_uzivatele, prvni_noc, posledni_noc, pokoj, typ`), ostatní sloupce ignoruje → přidat sloupec do reportu je bezpečné, přejmenovat ne. Import přepíše i objednávku (typ + noci) a `ubytovan_s`.

## Kdo čte pokoj / přání spolubydlících

| Místo | Čte |
|---|---|
| Report ubytování (+ import zpět) | `ubytovani.pokoj`, `ubytovan_s`, `skupina`, `spolubydlici_ve_skupine` |
| Report ubytovaných cizinců | `ubytovani.pokoj` |
| BFGR (`model/Report/BfgrReport.php`, „Chci bydlet s“, „Skupina spolubydlících“, „Ostatní ve skupině“) | `ubytovan_s`, skupina |
| Report potvrzení pro mladší 15 let | `ubytovan_s`, `spolubydlici_ve_skupine` |
| Infopult | `ubytovani` přes `Pokoj`, skupina přes `UbytovaciSkupiny::prehled()` |
| Admin uživatele | `ubytovani` přes `Pokoj`, skupina ve formuláři ubytování |
| Anonymizace (`AnonymizovanaDatabaze`, `AnonymizovanyUzivatel`) | maže `ubytovan_s` |

Sloupce skupiny dávají reporty přes poddotazy `UbytovaciSkupiny::sqlKodSkupiny()` a `sqlSpolubydliciVeSkupine()` (ostatní členové s ubytováním jako „Jméno Příjmení (ID)“).

BFSR, `Accounting`, statistiky a stravenky pracují s `shop_nakupy`, pokoje je nezajímají.

## Gotchas

- `ShopUbytovani::mozniUzivatele()` při každém vykreslení ubytování načte jména a loginy všech uživatelů do JSON, šablona proměnnou `{uzivatele}` ale nepoužívá. Mrtvý dotaz; kdyby se znovu zapojil, vynese osobní údaje všech uživatelů do stránky.
- Slučování uživatelů (`UzivatelSlucovani`) i anonymizace (`AnonymizovanaDatabaze`) najdou tabulky s FK na `uzivatele_hodnoty` samy, `ubytovani_skupiny_clenove` tedy nepotřebuje zvláštní péči; při sloučení dvou členů různých skupin zůstane členství nového uživatele, staré zmizí kaskádou.
- Ve vícelůžkových pokojích ročníku 2025 bylo zhruba 45 % smíšených podle pohlaví (lokální DB), pohlaví se tedy při přidělování striktně neodděluje.
