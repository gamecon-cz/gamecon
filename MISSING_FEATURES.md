# Chybějící funkce v novém e-shopu

Tento dokument obsahuje **funkce ze současného e-shopu (OLD_ESHOP.md), které nejsou zmíněny v plánu nového e-shopu (NEW_ESHOP.md)**.

> **Stav ověřen proti kódu 2026-09-08.** Dokument vznikl jako seznam otázek „co s tím",
> ne jako plán, takže původně bylo ❌ i to, co je dnes hotové. Značky níž jsou ověřené:
> ✅ hotové (u každého je uvedeno čím), 🟡 rozpracované, ❌ neřešené.
> Otázky „Rozhodnutí potřeba" u hotových položek zůstávají jako záznam, jak se rozhodlo.

---

## ⚠️ KRITICKÉ FUNKCE - Musí být v novém e-shopu

### 1. Typy produktů - Speciální kategorie

Současný e-shop má **7 speciálních typů položek** (`TypPredmetu`), které nejsou pokryty v NEW_ESHOP.md:

#### 🟡 **UBYTOVÁNÍ (typ 2)**
> **Rozpracováno.** Rozhodnuto: varianty normálního produktu. Migrace
> `2026-09-08-100016_accommodation-days-into-variants.php` sloučila 40 řádků na
> 8 produktů × 5 nočních variant (`ProductVariant.accommodationDay`, zásoba po nocích).
> Zbývá storefront (čtecí endpoint + mřížka) a zápisová cesta; spolubydlící a
> „nechci ubytování" se mají přesunout z účtu na objednávku.
**Co to je:**
- Ubytování po dnech (St, Čt, Pá, So, Ne)
- Každý den je samostatná položka
- Pole `ubytovani_den` (0-4)

**Současné funkce:**
- Výběr ubytování po dnech
- **Funkcionalita sdílení pokoje** ("s kým chceš být na pokoji")
- Správa kapacity ubytování
- Možnost vynutit ubytování nad kapacitu (admin)
- Automatické zrušení ubytování pro neplatící

**Třída:** `ShopUbytovani`

**Rozhodnutí potřeba:** Potřebujeme toto jako speciální typ produktu nebo jako varianty normálního produktu "Ubytování"?

---

#### ✅ **JIDLO (typ 4)**
> **Hotovo.** Tag `jidlo` + varianty; `MealProductsProvider`, `GET /cart/meals`,
> matice `ui/src/pages/jidlo/JídloMatice.tsx`.
**Co to je:**
- Jídla po dnech a typech (snídaně, oběd, večeře)
- Matrixový výběr (dny × typy jídel)

**Současné funkce:**
- Objednávání napříč dny a typy
- Matrixový výběrový interface (checkboxy)
- Podpora více verzí (změny cen v průběhu sezóny)
- Uzamčený stav po ukončení prodejního období
- Admin override pro úpravu po ukončení prodeje

**Rozhodnutí potřeba:** Potřebujeme toto jako speciální typ produktu nebo jako varianty?

---

#### ✅ **VSTUPNE (typ 5) - Dobrovolné vstupné**
> **Hotovo.** `EntryFeeService`, `GET`/`POST /cart/entry-fee`,
> `ui/src/pages/vstupne/Vstupne.tsx`.
**Co to je:**
- Pay-what-you-want - zákazník si zvolí částku (0-∞)
- Dvě varianty: "včas" a "pozdě" (podle data platby)

**Současné funkce:**
- Nelineární posuvník s **gama korekcí**
- Průměr z minulého roku zobrazený jako reference
- Zpětná vazba smajlíkem podle částky
- Rozděleno na varianty "včas" a "pozdě"
- Nyní vše započítáváno jako "včas"

**Šablona:** `shop-vstupne.xtpl`

**Rozhodnutí potřeba:** Je to normální produkt s možností zadat vlastní cenu nebo speciální typ?

---

#### ✅ **PARCON (typ 6)**
> **Hotovo jako tag.** `ProductTagCode::PARCON` — normální produkt, ne speciální typ.
**Co to je:**
- ParCon mini-akce
- Samostatný typ produktu

**Rozhodnutí potřeba:** Je to normální produkt nebo speciální typ?

---

#### ✅ **PROPLACENI_BONUSU (typ 7)**
> **Hotovo jako tag.** `ProductTagCode::PROPLACENI_BONUSU`.
**Co to je:**
- Virtuální "produkt" pro převod organizátorského bonusu na peníze
- **Ne pro přímý prodej**
- Používá se interně systémem Finance

**Současná funkce:**
- Organizátor si může nechat vyplatit nevyužitý bonus
- Vytvoří se záznam v `shop_nakupy` s tímto typem
- Převod bonusu na kredit

**Rozhodnutí potřeba:** Musíme mít tuto funkcionalitu v novém e-shopu?

---

### 2. Multi-year produktové modely

#### ✅ **Pole `model_rok` - Verze produktů napříč roky**
> **Vyřešeno zrušením.** Sloupec v DB ani v entitě není; ročník nese `archived_at`
> (NULL = aktuální). Produkt existuje trvale, nezakládá se každý rok znovu.
**Co to je:**
- Každý produkt má pole `model_rok` (např. 2023, 2024, 2025)
- Stejná položka (např. "Kostka GameCon") existuje ve více ročnících
- Unikátní omezení: `UNIQ_nazev_model_rok`, `UNIQ_kod_predmetu_model_rok`

**Současné funkce:**
- Zobrazení starších verzí produktů zákazníkům, kteří je koupili
- Import starých objednávek z minulých let
- Reporty napříč roky

**Použití:**
- Zákazník si koupil kostku v roce 2024 za 50 Kč
- V roce 2025 kostka stojí 60 Kč
- Zákazník stále vidí svou zakoupenou verzi za 50 Kč

**Rozhodnutí potřeba:** Potřebujeme multi-year verze produktů?

---

#### ✅ **Pole `je_letosni_hlavni` - Označení hlavní verze roku**
> **Vyřešeno zrušením.** Bez multi-year verzí produktu nemá co označovat.
**Co to je:**
- Boolean flag označující "hlavní" verzi produktu v daném roce
- Používá se při výběru, která verze se má zobrazit/použít

**Rozhodnutí potřeba:** Potřebujeme tuto logiku?

---

### 3. Časově omezená dostupnost

#### ✅ **Pole `nabizet_do` - Automatické pozastavení prodeje**
> **Hotovo.** `Product::$availableUntil`; `isAvailable()` po uplynutí data vrací false
> a `ProductRepository::findPublic()` takový produkt nevrací.
**Co to je:**
- Datum a čas, do kdy se produkt nabízí
- Po vypršení se stav automaticky změní na `POZASTAVENY`

**Současné funkce:**
- Automatická změna stavu po vypršení termínu
- Použití např. pro "Jídlo objednatelné do 15.7. 23:59"

**Kód:**
```php
if ($r['nabizet_do'] && strtotime($r['nabizet_do']) < time()) {
    $r['stav'] = StavPredmetu::POZASTAVENY;
}
```

**V NEW_ESHOP.md:** Označeno jako ❌ Časově omezené akce

**Rozhodnutí potřeba:** Chceme automatické pozastavení prodeje podle data?

---

### 4. Speciální slevy podle položky

#### ✅ **Slevy na konkrétní produkty podle kódu**
> **Hotovo.** `DiscountCalculator` + tabulka `discount_rule` (pravidla podle role,
> s limitem počtu kusů) místo natvrdo psaných větví v `Cenik`.
**Současný systém:**
- Kostka zdarma (1 na uživatele)
- Placka zdarma (1 na uživatele)
- Modré tričko zdarma při dosažení bonusového prahu
- Trička zdarma (1 nebo 2 podle úrovně oprávnění)

**Kód:**
```php
if (Predmet::jeToKostka($r['kod_predmetu'])) {
    $cenaPoSleve = (float)$cenik->cenaKostky($predmet);
}
```

**Detekce podle kódu produktu:**
- `jeToKostka()` - obsahuje 'kostka'
- `jeToPlacka()` - obsahuje 'placka'
- `jeToNicknack()` - obsahuje 'nicknack'
- `jeToBlok()` - obsahuje 'blok'
- `jeToPonozka()` - obsahuje 'ponozk'
- `jeToTaska()` - obsahuje 'taska'
- `jeToSnidane()` - obsahuje 'snidane'
- `jeToObed()` - obsahuje 'obed'
- `jeToVecere()` - obsahuje 'vecere'
- `jeToTricko()` - typ = TRICKO a obsahuje 'tricko'
- `jeToTilko()` - typ = TRICKO a obsahuje 'tilko'
- `jeToModre()` - název obsahuje 'modr'
- `jeToCervene()` - název obsahuje 'červen'

**V NEW_ESHOP.md:**
- ✅ Procentuální slevy podle zákaznických skupin
- ❌ Konkrétní produkt v košíku

**Rozhodnutí potřeba:** Jak řešit slevy na konkrétní typy produktů (kostka, placka...)? Pomocí kategorií? Tagů? Nebo stále podle kódu?

---

### 5. Admin funkce - Prodej

#### ✅ **Mřížkové prodejní rozhraní (KFC)**
> **Hotovo.** `KfcGridProvider`, `KfcGridProcessor`, `KfcSaleProcessor`,
> `ui/src/pages/obchod/`.
**Co to je:**
- Speciální admin rozhraní pro prodej na místě
- Rychlý prodej bez plného checkoutu
- Soubor: `admin/scripts/zvlastni/reporty/finance-report-sirien.php`

**Funkce:**
- Rychlý výběr zákazníka
- Okamžitý prodej položky
- Generování QR platby
- Používá se na akci u pokladny

**Rozhodnutí potřeba:** Potřebujeme zachovat KFC prodejní rozhraní?

---

#### ✅ **Import položek e-shopu z externích zdrojů**
> **Hotovo (zůstalo v legacy).** `EshopImporter` čte XLSX; validuje tag i `stav`
> (hodnota mimo `ProductStateEnum` řádek odmítne).
**Co to je:**
- Možnost importovat produkty z externího souboru/API
- Soubor: `admin/scripts/modules/finance/_import-eshopu.php`

**Rozhodnutí potřeba:** Potřebujeme import produktů?

---

### 6. Finanční integrace - Detailní funkce

#### ❌ **Integrace s třídou `Finance` - Komplexní výpočty**
**Co současný e-shop dělá:**
- Záznamy nákupů vstupují do `Finance::cenaPredmetu()`, `Finance::cenaStravy()`, `Finance::cenaUbytovani()`
- Výpočet celkového zůstatku zahrnuje:
  - Poplatky za aktivity (přihlášení na hry)
  - Náklady na merchandise
  - Náklady na jídlo
  - Náklady na ubytování
  - Dobrovolné vstupné (včas/pozdě)
  - Bonus organizátora (slevy za vedení aktivit)
  - Obecné slevy
  - Přijaté platby
  - Zůstatek z minulých let

**Metody v `Finance`:**
- `cenaPredmetu()` - cena všech merchandise
- `cenaStravy()` - cena všech jídel
- `cenaUbytovani()` - cena ubytování
- `cenaVstupne()` - dobrovolné vstupné včas
- `cenaVstupnePozde()` - dobrovolné vstupné pozdě
- `bonusZaVedeniAktivit()` - získaný bonus
- `vyuzityBonusZaVedeniAktivit()` - použitý bonus
- `nevyuzityBonusZaVedeniAktivit()` - zbývající bonus
- `stav()` - celkový zůstatek

**V NEW_ESHOP.md:** ❌ Platby - "používáme stávající systém"

**Rozhodnutí potřeba:** Jak napojit nový e-shop na stávající Finance systém? Musíme zachovat všechny tyto výpočty?

---

#### ❌ **Generování QR kódu pro platbu**
**Co to je:**
- Automatické generování QR platby s variabilním symbolem
- Integrace s českou bankou (QR platba)

**Současná třída:** `Gamecon\Finance\QrPlatba`

**Rozhodnutí potřeba:** Zůstane toto v Finance nebo bude součást nového e-shopu?

---

### 7. Reportování - Speciální reporty

#### ❌ **BFSR Report - Black Friday Shirt Report**
**Co to je:**
- Report o prodeji triček se slevami
- Zahrnuje speciální počítání slev na modrá/červená trička
- Metoda: `Predmet::jeToModre()`, `Predmet::jeToCervene()`

**Soubor:** `model/Report/BfsrReport.php`

**Rozhodnutí potřeba:** Musí nový e-shop poskytovat data pro tento report?

---

#### ❌ **BFGR Report - Black Friday Gaming Report**
**Co to je:**
- Report o finančních tocích včetně shopu
- Detailní rozpad slev a bonusů

**Soubor:** `model/Report/BfgrReport.php`

**Rozhodnutí potřeba:** Musí nový e-shop poskytovat data pro tento report?

---

### 8. Hromadné operace - Bulk actions

#### ✅ **Hromadné zrušení objednávek**
> **Hotovo.** `BulkCancelService` + `BulkCancelProcessor`; vrací zásobu přes
> `CapacityManager`.
**Současné metody:**
- `Shop::zrusObjednavkyPro($uzivatele, $typ)` - zruší objednávky daného typu pro více uživatelů
- `Shop::zrusLetosniObjednaneUbytovani($zdrojZruseni)` - zruší ubytování
- `Shop::zrusVsechnyLetosniObjedavky($zdrojZruseni)` - zruší všechny nákupy za rok
- `Shop::zrusPrihlaseniNaLetosniLarpy($odhlasujici, $zdrojZruseni)` - zruší LARPy
- `Shop::zrusPrihlaseniNaLetosniRpg($odhlasujici, $zdrojZruseni)` - zruší RPG
- `Shop::zrusPrihlaseniNaVsechnyAktivity($odhlasujici, $zdrojZruseni)` - zruší všechny aktivity

**Důvod:** Automatické zrušení pro neplatící účastníky

**V NEW_ESHOP.md:** ❌ Hromadné akce

**Rozhodnutí potřeba:** Potřebujeme hromadné zrušení objednávek? (Pro automatické skripty při kontrole plateb)

---

#### ✅ **Archiv zrušených nákupů s důvodem**
> **Hotovo.** Entita `CancelledOrderItem` nad `shop_nakupy_zrusene` — nese cenu,
> datum nákupu i zrušení a `cancellationReason`.
**Co to je:**
- Tabulka `shop_nakupy_zrusene`
- Ukládá důvod zrušení (`zdroj_zruseni`)
- Auditní stopa kdo a proč zrušil

**Metody:**
- `Shop::dejNazvyZrusenychNakupu($zdrojZruseni, $rocnik)` - seznam zrušených položek

**Rozhodnutí potřeba:** Potřebujeme archiv zrušených objednávek?

---

### 9. Admin prodej za jiného uživatele

#### ✅ **Rozlišení zákazníka a objednatele**
> **Hotovo.** `OrderItem` mapuje `id_objednatele` zvlášť od `id_uzivatele`.
**Co to je:**
- Pole `id_uzivatele` (zákazník - komu patří nákup)
- Pole `id_objednatele` (objednatel - kdo provedl nákup, může být admin)

**Použití:**
- Admin může nakoupit položky pro jiného uživatele
- Trasovatelnost, kdo provedl objednávku

**Metoda:**
- `Shop::prodat($idPredmetu, $kusu, $vcetneOznamemi)` - admin prodej

**V NEW_ESHOP.md:** Není zmíněno

**Rozhodnutí potřeba:** Potřebujeme admin možnost nakoupit pro jiného uživatele?

---

### 10. Diff-based aktualizace nákupů

#### ❌ **Porovnávání a aktualizace existujících nákupů**
**Co to je:**
- Současný systém nemá košík jako entitu
- Při odeslání formuláře se porovnají nové hodnoty vs existující nákupy
- Přidají se jen změny (diff)

**Metoda:**
- `Shop::zmenObjednavku($stare, $nove)` - diff-based update
- `Shop::zpracujPredmety()` - manuální počítání diference

**Kód (zjednodušeně):**
```php
$nove = []; // z formuláře
$stare = []; // z databáze
$nechce = array_diff($stare, $nove); // co smazat
$chceNove = array_diff($nove, $stare); // co přidat
```

**V NEW_ESHOP.md:**
- ✅ Perzistence košíku
- ✅ CRUD operace košíku

**Rozhodnutí potřeba:** Nový e-shop bude mít normální košík s CRUD operacemi, takže diff-based update nebude potřeba. ✅ OK

---

### 11. Speciální UI komponenty

#### ✅ **Matrixový výběr jídel**
> **Hotovo.** `JídloMatice.tsx` — každé kliknutí ukládá přes API.
**Co to je:**
- Tabulka s dny v sloupcích a typy jídel v řádcích
- Checkboxy pro výběr (např. "Oběd v pátek")

**Šablona:** `shop-jidlo.xtpl`

**Rozhodnutí potřeba:** Jak řešit UI pro výběr jídel?

---

#### 🟡 **Dynamické přidávání triček**
> **Rozpracováno.** Velikosti jsou varianty (migrace `100011`), ale výběr triček a
> mikin zatím jede přes legacy formulář — nová mřížka pokrývá jen ostatní merch.
**Co to je:**
- Dropdown s tričky
- JavaScript pro přidání dalšího dropdownu
- Neomezený počet triček

**Šablona:** `shop-predmety.xtpl`
**JavaScript:** `shop-tricka.js`

**Rozhodnutí potřeba:** Jak řešit UI pro více kusů stejného produktu s variantami?

---

#### ✅ **Posuvník pro dobrovolné vstupné s gama korekcí**
> **Hotovo.** `ui/src/pages/vstupne/Vstupne.tsx`.
**Co to je:**
- Nelineární posuvník (gama korekce 0.5)
- Dynamické smajlíky podle částky
- Reference na průměr z minulého roku

**Šablona:** `shop-vstupne.xtpl`
**JavaScript:** `shop-vstupne.js`
**Konstanta:** `Shop::VSTUPNE_GAMA_KOREKCE = 0.5`

**Rozhodnutí potřeba:** Jak řešit UI pro pay-what-you-want vstupné?

---

### 12. Pole v databázi

#### ✅ **`shop_nakupy.id_objednatele`**
> **Zachováno**, mapováno na `OrderItem`.
- ID uživatele, který provedl objednávku (může být jiný než zákazník)

#### ✅ **`shop_predmety.kod_predmetu`**
> **Zachováno.** `Product::$code`, UNIQUE; nese i strukturu variant
> (`Hs-2L-ct` = typ + noc), takže se z něj dá číst bez parsování názvu.
- SKU/kód produktu
- Používá se pro detekci typu (kostka, placka...)
- Unikátní omezení s `model_rok`

#### ✅ **`shop_predmety.model_rok`**
> **Zrušeno**, viz výš.
- Rok verze produktu
- Multi-year produkty

#### ✅ **`shop_predmety.nabizet_do`**
> **Zachováno** jako `Product::$availableUntil`.
- Časově omezená dostupnost

#### ✅ **`shop_predmety.je_letosni_hlavni`**
> **Zrušeno**, viz výš.
- Boolean flag pro hlavní verzi roku

#### ✅ **`shop_predmety.ubytovani_den`**
> **Zachováno** a nově i na variantě (`ProductVariant::$accommodationDay`).
- Den ubytování (0-4 pro St-Ne)
- Používá se jen pro typ UBYTOVANI

#### ✅ **`shop_nakupy.rok`**
> **Zachováno.** `OrderItem::$year` (`rok`), včetně indexu `IDX_rok_id_uzivatele`.
- Rok nákupu (např. 2025)
- Pro multi-year management

---

## 🤔 ROZHODNUTÍ POTŘEBNÁ

### Priorita 1 - KRITICKÉ rozhodnutí

1. **Speciální typy produktů** - Jak řešit UBYTOVANI, JIDLO, VSTUPNE, PARCON, PROPLACENI_BONUSU?
   - Option A: Speciální typy produktů (jako nyní)
   - Option B: Normální produkty s variantami + custom fieldy
   - Option C: Mix - některé speciální typy, některé normální

2. **Multi-year produkty** - Potřebujeme `model_rok`?
   - Option A: Ano, zachováme multi-year logiku
   - Option B: Ne, každý rok nový e-shop, migrace dat
   - Option C: Soft-delete starých produktů, bez multi-year

3. **Časově omezená dostupnost** - Potřebujeme `nabizet_do`?
   - Option A: Ano, automatické pozastavení podle data
   - Option B: Ne, admin ručně změní stav
   - Option C: Ano, ale jinak (scheduled tasks?)

4. **Slevy na konkrétní produkty** - Jak detekovat kostku, placku atd.?
   - Option A: Podle kódu produktu (jako nyní)
   - Option B: Tagy/kategorie
   - Option C: Custom atributy
   - Option D: Produktové typy/varianty

5. **Integrace s Finance** - Jak napojit na stávající Finance systém?
   - Musíme detailně specifikovat API mezi e-shopem a Finance

6. **Admin prodej za jiného uživatele** - Potřebujeme `id_objednatele`?
   - Option A: Ano, zachovat současnou logiku
   - Option B: Ne, admin jen upravuje objednávky uživatelů

### Priorita 2 - DŮLEŽITÉ rozhodnutí

7. **KFC prodejní rozhraní** - Zachovat?
   - Používá se aktivně na akci u pokladny

8. **Hromadné zrušení** - Potřebujeme bulk cancel operations?
   - Používá se v automatických skriptech pro neplatící

9. **Archiv zrušených** - Tabulka `shop_nakupy_zrusene`?
   - Auditní stopa důvodů zrušení

10. **BFSR/BFGR reporty** - Musí nový e-shop poskytovat data?

11. **Import produktů** - Potřebujeme import z externích zdrojů?

### Priorita 3 - UI rozhodnutí

12. **Matrixový výběr jídel** - Jak řešit v novém UI?

13. **Dynamické přidávání triček** - Jak řešit multiple selection?

14. **Posuvník vstupného** - Zachovat gama korekci a smajlíky?

---

## ✅ Co je v NEW_ESHOP.md DOBŘE pokryto

- ✅ Produkty s variantami
- ✅ Obrázky produktů
- ✅ Viditelnost podle stavu
- ✅ Sledování zásob
- ✅ **Prevence přeprodání** (KRITICKÉ - chybělo v OLD)
- ✅ Slevy podle zákaznických skupin
- ✅ Košík s perzistencí
- ✅ Objednávky s čísly a stavy
- ✅ Historie objednávek
- ✅ Filtrování objednávek
- ✅ REST API
- ✅ Vícejazičnost
- ✅ Event system
- ✅ Migrace
- ✅ Testy

---

## Doporučení

Body 1, 2, 4 a 5 z původního seznamu jsou rozhodnuté a hotové — speciální typy produktů
nahradily tagy plus varianty, multi-year verze se zrušily ve prospěch `archived_at`,
KFC mřížka i hromadné rušení objednávek existují. Zbývá:

1. **Integrace s `Finance`** — dosud jediná velká nedořešená vazba; nový e-shop počítá
   ceny a slevy, ale zůstatek a platby drží legacy `Finance`.
2. **QR platby a reporty BFSR/BFGR** — zůstávají v legacy. Rozhodnout, jestli se
   překlápějí, nebo tam zůstanou natrvalo.
3. **Dokončit ubytování** — čtecí endpoint a mřížka, pak zápis; spolubydlící a
   „nechci ubytování" přesunout z účtu na objednávku.
4. **Odstranit legacy formulář přihlášky** — až budou všechny sekce na novém e-shopu.
   Merch už z něj vypadl, trička a mikiny v něm zatím zůstávají.

### Nice to have: převést i starší ročníky na varianty

Migrace na varianty (velikosti `100011`, ubytování `100016`) záměrně řeší jen aktuální
ročník. Starší ročníky používají jinou konvenci kódů i názvů (`Dvojlůžák středa` bez
strukturovaného `kod_predmetu`), takže by každý potřeboval vlastní čtení — proto jim
zůstala jedna výchozí varianta na produkt.

Nespěchá to: staré produkty se už neprodávají, jen se z nich čtou reporty, a ty jedou
přes `shop_nakupy` se snapshotem názvu, ne přes strukturu produktu. Kdyby se to někdy
dělalo, dává smysl jeden ročník po druhém a s ověřením proti reportům daného roku, ne
jedna migrace na všechno.

---

**Poznámka:** Stav položek výš je ověřený proti kódu (2026-09-08), ne odhad.
