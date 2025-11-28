# Současná implementace e-shopu GameCon

Tento dokument popisuje **existující** funkce e-shopu v aplikaci GameCon. Všechny nákupy jsou zaznamenány v tabulce `shop_nakupy`.

## Databázové schéma

### Hlavní tabulky

#### `shop_nakupy` (Záznamy nákupů)
Primární tabulka sledující všechny nákupy provedené uživateli.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id_nakupu` | bigint(20) unsigned | Primární klíč, auto-increment |
| `id_uzivatele` | bigint(20) unsigned | ID uživatele-zákazníka (kdo dostává položku) |
| `id_objednatele` | bigint(20) unsigned | ID uživatele-objednatele (kdo provedl objednávku, nullable) |
| `id_predmetu` | bigint(20) unsigned | ID položky shopu (cizí klíč do shop_predmety) |
| `rok` | smallint(6) | Rok nákupu (např. 2025) |
| `cena_nakupni` | decimal(6,2) | Nákupní cena v okamžiku objednávky (původní cena bez slev) |
| `datum` | timestamp | Časová značka nákupu (výchozí: current_timestamp) |

**Klíčové vlastnosti:**
- Každý řádek = jedna koupená položka (množství je reprezentováno více řádky)
- Ukládá **historickou cenu** v okamžiku nákupu (ne aktuální cenu)
- Propojuje zákazníka (kdo dostává položku) a objednatele (kdo provedl objednávku - může se lišit u adminů)
- Nákupy specifické pro rok (pro správu víceletých akcí)

#### `shop_predmety` (Položky shopu)
Katalog všech položek dostupných k nákupu.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id_predmetu` | bigint(20) unsigned | Primární klíč, auto-increment |
| `nazev` | varchar(255) | Název položky |
| `kod_predmetu` | varchar(255) | Kód položky/SKU |
| `model_rok` | smallint(6) | Modelový rok (který ročník verze) |
| `cena_aktualni` | decimal(6,2) | Aktuální cena |
| `stav` | smallint(6) | Stav položky (0=vyřazeno, 1=veřejné, 2=jen orgové, 3=pozastaveno) |
| `nabizet_do` | datetime | Nabízet do data (nullable, auto-pause po termínu) |
| `kusu_vyrobeno` | smallint(6) | Množství vyrobeno/dostupné (nullable) |
| `typ` | smallint(6) | Typ položky (viz konstanty TypPredmetu) |
| `je_letosni_hlavni` | tinyint(1) | Je to letošní hlavní verze (výchozí: 0) |
| `ubytovani_den` | smallint(6) | Den ubytování (0-4 pro St-Ne, nullable) |
| `popis` | varchar(2000) | Popis |

**Unikátní omezení:**
- `UNIQ_nazev_model_rok` - Unikátní kombinace názvu a modelového roku
- `UNIQ_kod_predmetu_model_rok` - Unikátní kombinace kódu položky a modelového roku

#### `shop_nakupy_zrusene` (Zrušené nákupy)
Archiv zrušených nákupů pro auditní stopu.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id_nakupu` | bigint(20) unsigned | Původní ID nákupu |
| `id_uzivatele` | bigint(20) unsigned | ID uživatele-zákazníka |
| `id_predmetu` | bigint(20) unsigned | ID položky shopu |
| `rocnik` | smallint(6) | Rok |
| `cena_nakupni` | decimal(6,2) | Nákupní cena |
| `datum_nakupu` | timestamp | Původní datum nákupu |
| `datum_zruseni` | timestamp | Datum zrušení |
| `zdroj_zruseni` | varchar(255) | Zdroj/důvod zrušení |

## Typy položek (`TypPredmetu`)

Systém podporuje 7 různých typů položek:

1. **PREDMET (1)** - Merchandise (kostky, placky, bloky, tašky atd.)
2. **UBYTOVANI (2)** - Ubytování (po dnech: St, Čt, Pá, So, Ne)
3. **TRICKO (3)** - Trička (různé velikosti a barvy, včetně speciálních orgových triček)
4. **JIDLO (4)** - Jídla (snídaně, oběd, večeře po dnech)
5. **VSTUPNE (5)** - Dobrovolné vstupné (pay-what-you-want)
6. **PARCON (6)** - ParCon mini-akce
7. **PROPLACENI_BONUSU (7)** - Výplata bonusu (převod organizátorského bonusu na peníze, ne pro přímý prodej)

## Stavy položek (`StavPredmetu`)

Položky mohou být ve 4 různých stavech:

- **MIMO (0)** - Vyřazeno/Odstraněno (není dostupné)
- **VEREJNY (1)** - Veřejné (dostupné všem uživatelům)
- **PODPULTOVY (2)** - Podpultové (dostupné pouze organizátorům/speciálním uživatelům)
- **POZASTAVENY (3)** - Pozastaveno (prodej pouze na místě, ne online)

## Současné funkce e-shopu

### 1. Správa produktů

#### Typy produktů
- **Merchandise** - Kostky, placky, bloky, ponožky, tašky
  - Speciální zpracování pro konkrétní položky (kostky, placky) se slevami
  - Identifikace na základě kódu produktu
- **Trička** - Více velikostí a barev
  - Modrá trička (jen orgové, vyžaduje speciální oprávnění)
  - Červená trička (jen orgové, vyžaduje speciální oprávnění)
  - Běžná trička (veřejná)
  - Použita maximální cena napříč všemi typy triček
- **Ubytování** - Rezervace po dnech (St-Ne)
  - Komplexní logika ve třídě `ShopUbytovani`
  - Funkcionalita sdílení pokoje
- **Jídla** - Snídaně, oběd, večeře po dnech
  - Objednávání napříč dny a typy
  - Matrixový výběrový interface
- **Dobrovolné vstupné** - Pay-what-you-want s posuvníkem
  - Dvě varianty: "včas" a "pozdě"
  - Gama korekce pro nelineární posuvník
  - Zobrazení průměru z minulého roku

#### Funkce katalogu produktů
- Multi-year produktové modely (stejná položka napříč různými roky)
- Viditelnost produktu podle stavu
- Časově omezená dostupnost (pole `nabizet_do`)
- Automatická změna stavu po vypršení termínu nabídky
- Řazení produktů podle typu, dne (pro ubytování/jídla), názvu
- Popisy produktů
- Označení "hlavní položky roku"

#### Správa skladu
- Ruční sledování skladu (`kusu_vyrobeno`)
- Sledování počtu prodejů přes záznamy nákupů
- Výpočet zbývajícího skladu
- Žádná automatická prevence přeprodání (jen sledování)
- Admin rozhraní pro zobrazení úrovní skladu a prodejů

### 2. Cenový systém

#### Základní cenotvorba
- Aktuální cena uložena v `shop_predmety.cena_aktualni`
- Historická cena uložena v `shop_nakupy.cena_nakupni` (v okamžiku nákupu)
- Změny cen povoleny (existující objednávky si ponechají starou cenu)
- Podpora více verzí stejné položky s různými cenami ve stejném roce

#### Systém slev (třída `Cenik`)
Komplexní logika slev založená na uživatelských právech a bonusech organizátorů:

**Slevy organizátorů:**
- Kostka zdarma (1 na uživatele s oprávněním)
- Placka zdarma (1 na uživatele s oprávněním)
- Ubytování zdarma (celé nebo pouze středeční noc)
- Jídlo zdarma nebo se slevou
- Modré tričko zdarma při dosažení bonusového prahu
- Trička zdarma (1 nebo 2 podle úrovně oprávnění)
- Modrá/červená orgová trička se slevou

**Bonusový systém:**
- Organizátoři získávají bonus za vedení aktivit
- Výše bonusu podle délky aktivity
- Bonus lze aplikovat na nákupy jako slevu
- Nevyužitý bonus sledován samostatně
- Bonus lze převést na peněžní výplatu

**Aplikace slev:**
- Slevy počítány dynamicky podle uživatelských oprávnění
- Slevy aplikovány v určitém pořadí
- Finální cena vypočtena ve třídě `Cenik`
- Původní cena zachována v záznamu nákupu

#### Speciální cenová pravidla
- Dobrovolné vstupné: pay-what-you-want (0-∞)
- Stejné jídlo/ubytování za různé ceny (řeší změny cen v průběhu sezóny)
- Uživatel vidí cenu své zakoupené verze

### 3. Nákupní košík a pokladna

#### Funkce košíku
- Žádná perzistentní entita košíku
- Výběr založený na formuláři:
  - Checkboxy pro jídla (matice den/typ)
  - Radio buttony pro ubytování (na den)
  - Rozbalovací seznamy pro trička (vícenásobný výběr)
  - Číselné inputy pro merchandise
  - Posuvník pro dobrovolné vstupné
- Změny porovnány s existujícími nákupy (diff-based aktualizace)
- Množství reprezentováno více záznamy nákupů

#### Proces objednávky
- Žádný vícekrokový checkout proces
- Odeslání formuláře přímo vytváří/aktualizuje nákupy
- Zpracovatelské metody:
  - `zpracujPredmety()` - merchandise a trička
  - `zpracujUbytovani()` - ubytování
  - `zpracujJidlo()` - jídla
  - `zpracujVstupne()` - dobrovolné vstupné
- Okamžité potvrzení objednávky (bez kontroly košíku)
- Žádná entita objednávky (nákupy jsou objednávka)

#### Správa objednávek
- **Žádná entita objednávky** - každý nákup je nezávislý
- Záznamy nákupů seskupeny podle:
  - ID uživatele
  - Roku
  - Typu
- Žádné číslo/ID objednávky
- Žádný uložený součet objednávky (počítáno za běhu)
- Žádné stavy objednávek (zrušené nákupy přesunuty do archivní tabulky)

### 4. Sledování nákupů

#### Záznamy nákupů
- Jeden řádek na položku v `shop_nakupy`
- Propojuje zákazníka a objednatele (může se lišit)
- Ukládá původní nákupní cenu
- Časová značka nákupu
- Přiřazení k roku pro víceletou akci

#### Historie nákupů
- Zobrazení všech nákupů na uživatele
- Filtrování podle roku
- Seskupení podle typu položky
- Dynamický výpočet součtů z jednotlivých záznamů

#### Obsluha zrušení
- Metody hromadného zrušení:
  - Zrušit vše ubytování
  - Zrušit všechny nákupy určitého typu
  - Zrušit všechny nákupy za rok
  - Zrušit registrace aktivit (oddělené od shopu)
- Zrušené nákupy přesunuty do `shop_nakupy_zrusene` s důvodem
- Sledování zdroje zrušení (kdo/co spustilo zrušení)

### 5. Uživatelská oprávnění a řízení přístupu

#### Nákupy založené na oprávněních
- Veřejné položky (stav = 1) - všichni uživatelé
- Položky jen pro orgy (stav = 2) - uživatelé se specifickými oprávněními
- Modrá trička - `Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA`
- Červená trička - `Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA`
- Speciální slevy podle oprávnění

#### Host vs registrovaní uživatelé
- Detekce prvních kupujících
- Odlišné zpracování pro autentizované uživatele
- Žádný host checkout (uživatelé musí být registrováni)

### 6. Admin rozhraní

#### Správa shopu (`/admin/finance/shop`)
- Zobrazení všech položek podle typu
- Statistiky prodejů na položku:
  - Celkem prodáno
  - Tržby
  - Čas posledního nákupu
  - Zbývající sklad
- Úprava vlastností položky:
  - Vyrobené množství
  - Stav položky
- Import položek e-shopu z externích zdrojů
- Mřížkové prodejní rozhraní (KFC)

#### Funkce
- Žádné hromadné operace
- Žádné CRUD produktů (produkty spravovány jinde/importovány)
- Zaměření na monitorování skladu a prodejů
- Změny stavu pro řízení viditelnosti

### 7. Finanční integrace

#### Sledování financí uživatele (třída `Finance`)
- Komplexní finanční přehled na uživatele
- Sledované komponenty:
  - Poplatky za aktivity
  - Náklady na merchandise
  - Náklady na jídlo
  - Náklady na ubytování
  - Dobrovolné vstupné (včas)
  - Dobrovolné vstupné (pozdě)
  - Bonus organizátora
  - Obecné slevy
  - Přijaté platby
  - Zůstatek z minulých let

#### Integrace plateb
- Záznamy nákupů vstupují do uživatelského finančního zůstatku
- Výpočet zůstatku zahrnuje všechny typy nákupů
- Generování QR kódu pro platbu
- Sledování plateb oddělené od nákupů
- Žádný výběr platební metody při nákupu

### 8. Reportování

#### Dostupné reporty
- BFSR Report - zahrnuje nákupy ze shopu se slevami
- BFGR Report - detailní finanční rozpad
- Finance reporty - uživatelské zůstatky a nákupy

#### Statistiky shopu
- Celkové prodeje na položku
- Tržby podle typu položky
- Počty nákupů
- Prodeje v čase
- Stav skladu

### 9. Speciální funkce

#### Systém ubytování (`ShopUbytovani`)
- Výběr ubytování po dnech
- Funkcionalita sdílení pokoje ("s kým chceš být na pokoji")
- Správa kapacity
- Možnost vynutit ubytování nad kapacitu (admin funkce)
- Automatické zrušení pro neplatící

#### Systém jídel
- Matrixový výběr (dny × typy jídel)
- Snídaně, oběd, večeře
- Podpora více verzí (změny cen v průběhu sezóny)
- Uzamčený stav po ukončení prodejního období
- Admin override pro úpravu po ukončení prodeje

#### Systém triček
- Dynamické rozbalovací seznamy (přidat další podle potřeby)
- Varianty velikostí a barev
- Speciální barvy orgových triček
- Tričko zdarma získané dosažením bonusového prahu organizátora
- JavaScript-enhanced rozhraní

#### Dobrovolné vstupné
- Nelineární posuvník s gama korekcí
- Průměr z minulého roku zobrazený jako reference
- Zpětná vazba smajlíkem podle částky
- Rozděleno na varianty "včas" a "pozdě" (historicky)
- Nyní vše započítáváno jako "včas"

### 10. Technická implementace

#### Architektura
- Vlastní MVC s XTemplate šablonováním
- Třída `Shop` - hlavní logika shopu
- Třída `Predmet` - model položky (rozširuje DbObject)
- Třída `ShopUbytovani` - logika specifická pro ubytování
- Třída `Cenik` - cenotvorba a slevy
- Třída `Finance` - finanční výpočty

#### Přístup k databázi
- Přímé SQL dotazy (žádné ORM pro legacy kód)
- Doctrine entity pro nový kód (integrace Symfony)
- Koexistence obou systémů:
  - Legacy: `shop_nakupy`, `shop_predmety` přes DbObject
  - Moderní: `ShopPurchase`, `ShopItem` Doctrine entity

#### Zpracování formulářů
- Zpracování na základě POST
- Žádná CSRF ochrana v legacy formulářích
- Klíče formulářů:
  - `shopP` - merchandise
  - `shopT` - trička
  - `shopU` - ubytování
  - `shopV` - dobrovolné vstupné
  - `cShopJidlo` - jídla
  - `cShopJidloZmen` - indikátor změny jídel

#### Session a stav
- Žádná session košíku
- Stav nákupů získán z databáze pokaždé
- Aktuální výběry zobrazeny podle existujících nákupů

### 11. Omezení a problémy

#### Chybějící funkce
- Žádná entita košíku
- Žádná entita objednávky/čísla objednávek
- Žádný vícekrokový checkout
- Žádné potvrzovací e-maily objednávek
- Žádná upozornění na sklad/prevence přeprodání
- Žádné vyhledávání produktů
- Žádné filtrování/kategorie produktů
- Žádné obrázky produktů
- Žádné recenze produktů
- Žádný wishlist
- Žádné slevové kupóny/promo kódy
- Žádná doprava (všechny položky vyzvednuty na místě)
- Žádný výpočet daní (ceny jsou finální)
- Žádné refundace (pouze zrušení do archivu)
- Žádná úprava objednávky po nákupu
- Žádné stránkování historie nákupů
- Žádný export dat nákupů

#### Technický dluh
- Smíšený legacy a moderní kód
- Dva vzory přístupu k databázi (DbObject + Doctrine)
- Žádné REST API
- Rozhraní pouze na základě formulářů
- Ruční sledování skladu
- Diff-based aktualizace nákupů (náchylné k chybám)
- Více typů položek v jediné tabulce
- Žádná omezení cizích klíčů v legacy schématu
- Komplexní business logika ve vrstvě prezentace
- Omezené pokrytí testy

#### Rizika integrity dat
- Žádná bezpečnost transakcí pro nákupy více položek
- Žádné zamykání skladu během nákupu
- Nákupní cena může být ručně upravena
- Žádná auditní stopa pro změny cen
- Zrušené nákupy lze obnovit pouze ručně

## Klíčové třídy a soubory

### Hlavní třídy
- `model/Shop/Shop.php` - Hlavní logika shopu (1146 řádků)
- `model/Shop/Predmet.php` - Model položky
- `model/Shop/ShopUbytovani.php` - Logika ubytování
- `model/Uzivatel/Cenik.php` - Cenotvorba a slevy
- `model/Uzivatel/Finance.php` - Finanční výpočty uživatele

### Doctrine Entity (moderní)
- `symfony/src/Entity/ShopPurchase.php`
- `symfony/src/Entity/ShopItem.php`
- `symfony/src/Entity/ShopPurchaseCancelled.php`

### Enumy/konstanty
- `model/Shop/TypPredmetu.php` - Typy položek
- `model/Shop/StavPredmetu.php` - Stavy položek

### Admin rozhraní
- `admin/scripts/modules/finance/shop.php` - Správa shopu

### Šablony
- `model/Shop/templates/shop-jidlo.xtpl` - Výběr jídel
- `model/Shop/templates/shop-predmety.xtpl` - Merchandise/trička
- `model/Shop/templates/shop-vstupne.xtpl` - Dobrovolné vstupné

## Shrnutí

Současný e-shop GameCon je **jednoduchý nákupní systém založený na formulářích** bez tradičních e-commerce konceptů jako jsou košíky nebo objednávky.

**Silné stránky:**
- Řeší komplexní případy použití (vícedenní ubytování, jídla, dobrovolné ceny)
- Podpora multi-year verzí podle roku
- Bohatý systém slev/bonusů pro organizátory
- Integrováno se sledováním financí uživatele
- Zachování historických cen

**Slabé stránky:**
- Žádný košík/checkout proces
- Žádná správa skladu/prevence přeprodání
- Žádná správa objednávek
- Smíšená kódová základna (legacy + moderní)
- Omezené admin funkce
- Žádná historie nákupů pro zákazníky
- Pouze na základě formulářů (žádné API)

**Základní princip:**
Vše končí v tabulce `shop_nakupy` - každý řádek reprezentuje jednu koupenou položku s historickou cenou, propojenou se zákazníkem a rokem.
