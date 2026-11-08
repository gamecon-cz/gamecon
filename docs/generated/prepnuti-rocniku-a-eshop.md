# Přepnutí ročníku a e-shop

TL;DR: ročník se přepíná po skončení GC, ale katalog nového ročníku se importuje až před otevřením e-shopu
(kolem května). Pravidla slev se při přepnutí neřeší: platí v každém ročníku, jen placku a kostku zdarma pojmenovává
každý ročník zvlášť a to dělá právě květnový import. Celé přepnutí vede
[Issue #1308](https://github.com/gamecon-cz/gamecon/issues/1308), postup dnes popisuje `PREKLOPENI_ROCNIKU_NAVOD.md`.

## Vstupní body

- `admin/scripts/modules/dev/update-zustatku.php` — SQL pro uzavření ročníku (zůstatky, osobní pole, místnosti)
- `symfony/src/Discount/DiscountRuleLoader.php` — čte pravidla slev pro ročník: řádky ročníku a řádky `year = -1`
- `model/Shop/LetosniPredmetyZdarma.php` — založí řádek ročníku pro placku a kostku zdarma z obecné šablony
- `migrace/9999_01-…` (role) a `migrace/9999_03-…` (nastavení) — přepnutí ročníku u rolí a nastavení, které se na rozdíl
  od pravidel slev kopírují
- `model/Shop/EshopImporter.php::archivujChybejici()` — import archivuje produkty, které v listu chybí, s `NOW()`

## Časová osa (záměr)

1. Po skončení GC se uzavírá ročník. Pořadí: dokončit rozpracované objednávky, zapsat zůstatky a vynulovat osobní
   pole, přepnout `ROCNIK` (deploy).
2. O měsíce později, pár dní před otevřením e-shopu, se importuje katalog nového ročníku.

Import tedy nejde vzít jako krok přepnutí a přepnutí musí obstát bez nového katalogu.

## Pravidla

- **Katalog se při přepnutí nearchivuje, stačí květnový import** (záměr, rozhodnutí 2026-11-08: žádný krok navíc).
  Ročník produktu je `COALESCE(YEAR(archived_at), ROCNIK)`, takže do importu patří produkty minulého ročníku na skladě
  k novému. E-shop je v té době zavřený a import před otevřením archivuje všechno, co v listu chybí, s `NOW()`.
  - **Cena tohoto rozhodnutí (ověřeno čtením dotazu, ne na datech):** statistika „Prodané předměty"
    (`Statistiky`) počítá prodané placky, kostky a trička jako `shop_nakupy.rok = produkt.model_rok`. Loňské produkty
    mají po přepnutí `model_rok` nového roku a po květnovém importu s `NOW()` ho mají natrvalo, takže řádek minulého
    ročníku v ní ukáže u placek, kostek a triček nuly. Stejným výrazem čtou ročník produktu i další dotazy (`Finance`,
    `Shop.php`, `BfgrReport::letosniJidla()`, `BfsrReport::letosniDruhyUbytovani()`, report stravenek, import
    ubytování), u nich se dopad nezkoumal.
  - Kdyby to vadilo, stačí v `archivujChybejici()` datovat zbytky koncem minulého roku místo `NOW()`. Pozor: produkt
    nemá datum vzniku, takže druhý import téhož roku, který vyřadí produkt založený prvním importem, by ho datoval do
    minulého roku. Mezi přepnutím a importem by statistika zůstala špatně tak jako tak.
- **Pravidla slev (`discount_rule`) nezávisí na ročníku, kromě dvou** (rozhodnutí 2026-11-10). Jedenáct z třinácti
  (jídlo, trička, ubytování, jednotlivé noci) drží jediný řádek s `year = -1`, který platí v každém ročníku, stejně jako
  u rolí a nastavení, takže nový ročník je má hned a nic se nekopíruje. Placka a kostka zdarma mají řádek pro každý
  ročník, protože jmenují letošní předmět (`productCode`): obecná šablona `year = -1` žádný nejmenuje a řádek ročníku
  založí první květnový import, ale jen když list obsahuje sloupec `je_letosni_hlavni` a je v něm právě jeden předmět
  na druh; jinak import vypíše varování a pravidlo zůstane bez kódu. Do té doby tato dvě pravidla nic nedají, ostatní
  fungují hned.
  - Loader čte řádky ročníku i `year = -1`. Řádek ročníku přebíjí obecný řádek téhož kódu, i neaktivní: tak by ročník
    pravidlo vypnul. Pozor: ročník, který chce jedno z jedenácti pravidel změnit, si ho zakládá jako řádek ročníku
    (`UNIQUE (code, year)`), zbytek ročníků zůstane na šabloně.
  - Proč ne kopie při přepnutí (původní návrh): vyžadovala by další endless migraci, test na prázdný ročník a hlídání, že
    se zkopírovalo právě jednou, a nic z toho pravidla, která ročník nenesou, nepotřebují.
- **Dokončení rozpracovaných objednávek musí být úplně první krok uzavření** (záměr), před jakoukoli změnou rolí a slev
  a před generováním zůstatků: do přepnutí totiž změna role zapsaná přes entitu `UserRole`
  (`UserRoleChangedListener::onUserRoleChanged`) přeceňuje rozpracovanou objednávku aktuálního roku. Hromadné dokončení
  zatím není postaveno. Objednávku z košíku dokončí jen tlačítko „Odjel z GC" („Odjela z GC") na infopultu, a to u přítomného
  účastníka. Prodej na pultu a ruční prodej ji dokončují hned a endpoint `POST /api/cart/checkout` ji dokončí také,
  ale storefront ho nevolá. Rozpracované tedy zůstávají objednávky těch, kdo nepřijeli nebo odjezd nezapsali.

## Co přepnutí e-shopu nezmění

- Zůstatky jsou snímek: `Finance` cení podle živých rolí, takže minulý ročník se po přepnutí přepočítat nedá.
- `ROCNIK` je konstanta v souboru (beta a preview čtou `cache/private/rocnik_override`), přepnutí proto vyžaduje deploy.
