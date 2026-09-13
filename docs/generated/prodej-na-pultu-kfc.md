# Prodej na pultu (KFC) — anonymní nákup a jeho zaúčtování

TL;DR: KFC je pokladna na infopultu (`/kfc/*`, jen `ROLE_ADMIN`). Kdo přijde bez účtu, nakupuje na sdílený účet `ANONYM` a hotovost se mu musí **připsat** do `platby`, jinak na něm narůstá fiktivní dluh. Tenhle dokument drží pravidla, která z kódu nejsou vidět.

## Vstupní body

- `symfony/src/ApiResource/KfcResource.php` — `/kfc/products`, `/kfc/grids`, `/kfc/sales`
- `symfony/src/State/Kfc/KfcSaleProcessor.php` — zápis prodeje
- `model/Shop/Shop.php:1504` — legacy ekvivalent (`prodat()`), podle kterého se KFC srovnává
- `model/Uzivatel/Finance.php:321` — `pripis()`, jediný `INSERT` do `platby`
- `model/Uzivatel/Finance.php:694` — `sumaPlateb()`, kde se `poznamka` zobrazuje

## Pravidla

**Kupujícím je sdílený účet `ANONYM`, ne `SYSTEM`** (záměr). Návštěvník bez účtu koupí na
pultu tričko a nemá se to k čemu přiřadit; NULL se zkoušelo a dělalo problémy. Dřív se
používal `SYSTEM`, jenže ten je jinde v aplikaci *vykonavatelem* operací (import plateb,
promlčení), takže se mu ve finančním přehledu míchaly dvě role — a kdyby dostal roli,
pult by anonymnímu zákazníkovi tiše počítal slevu. `ANONYM` je proto **bez rolí** a
sdílený přes všechny ročníky. KFC neumí prodat na konkrétního účastníka — DTO nese jen
`items[]` — a zatím to tak má zůstat.

**Platba zná svou objednávku** (`platby.order_id`). Bez té vazby držel protizápis
u prodeje jen shodný čas a částka: v datech je z toho jedna osiřelá platba (400 Kč,
2026-07-23), kterou po sobě nechal prodej přepnutý na konkrétního účastníka o 52 vteřin
později. Zrušení ani změna prodeje se do platby dřív nepromítly vůbec. `ON DELETE SET NULL`,
ne kaskáda — mazat finanční řádek kvůli smazané objednávce by bylo příliš tiché.

**Anonymní prodej se musí připsat do `platby`.** Legacy to dělá hned za zápisem nákupu:

```php
if ($this->zakaznik->id() === Uzivatel::SYSTEM) {
    $this->zakaznik->finance()->pripis($cena * $kusu, $this->objednatel, 'anonymní prodej');
}
```

Bez toho sedí v `shop_nakupy` pohledávka za SYSTEM, kterou nikdy nikdo nezaplatil. `pripis()`
je přitom jen jeden `INSERT` do `platby` — žádný přepočet zůstatku, žádný stav objektu
`Finance`. `stav()` si platby sčítá až při čtení, takže se nic neinvaliduje.

**`poznamka` není příznak, je to popisek řádku.** `sumaPlateb()` vykreslí buď
`'Platba na účet'` (když `provedl = SYSTEM`), nebo `poznamka`, jinak `'(bez poznámky)'`.
Řetězec `'anonymní prodej'` se **nikde nečte** a na ničem se nevětví — je napsaný na jednom
místě a jen se zobrazuje.

## V čem se pult od legacy odchyluje

Přechod na `CartService` nezachoval chování 1:1. Dvě odchylky jsou vědomé, ale znát je
potřeba, protože dokument jinde tvrdí, že obě cesty mají psát srovnatelný řádek:

| | legacy `prodat()` | pult přes `CartService` |
|---|---|---|
| cena | vždy syrová `cena_aktualni` | prochází slevovým enginem podle rolí kupujícího |
| kapacita | jen `kusu_vyrobeno` vs `COUNT(*)` | `CapacityManager`; rezervaci pro organizátory pult obejde, celkovou zásobu ne |
| termín prodeje merche | neřešil vůbec | platí, po termínu jen přes obejití (logované) |

Slevy dnes nic nespustí — `SYSTEM` nemá žádnou roli a `product_discount` je prázdná —
ale jakmile by roli dostal, prodával by pult anonymnímu zákazníkovi se slevou. Vlastní
anonymní účet bez rolí to řeší konstrukcí, ne výjimkou.

Rezervaci pro organizátory pult **obchází záměrně** (`GUARD_ORGANIZER_STOCK`): komu kus
vydá, rozhoduje obsluha, a legacy tenhle pojem stejně neznalo. Celkovou zásobu obejít
nejde — prodat neexistující kus nesmí nikdo.

## Co se ví a zatím neudělalo

**Příznak místo `poznamka`** (záměr): klasifikovat „prodej na pultu" textem je křehké —
nedá se dotazovat jinak než `LIKE`, nejde přeložit, uklepnutí se nepozná. Chce to typovaný
sloupec na `platby` (druh platby) a `sumaPlateb()` z něj vyrábět popisek, jako už dnes
zvlášť řeší `provedl = SYSTEM`.

Vědomě to **není** součástí přechodu KFC na `CartService`: dokud píšou obě cesty (legacy
`prodat()` i KFC), musí produkovat **stejný řádek**. Kdyby příznak dostala jen jedna,
vznikly by dva popisky pro tutéž událost. Až se zavede, má pokrýt oba zapisovatele naráz
a `NULL` musí dál znamenat „jako dosud" kvůli historii.
