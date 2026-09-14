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

## Zaokrouhlování na celé koruny

Pult bere celé koruny, takže se zaokrouhluje **každý kus**, ne až součet — jinak by řádky
nákupu nesouhlasily s připsanou platbou a na účtu by po každém prodeji zůstal haléřový
nedoplatek. Důsledek, který je potřeba znát: `42,40 × 2` je **84**, ne 85 ze zaokrouhleného
součtu. Dnes to nic nespustí (žádný produkt nemá haléře), spustí to až procentní sleva.

**Nulová cena je v pořádku, ale nesmí se počítat mezi ubytování zdarma pro orgy** (záměr).
`BfsrReport` dělí noci na placené a zdarma jen podle ceny (`$polozka->castka > 0.0`,
`model/Report/BfsrReport.php:199`), takže noc zaokrouhlená na nulu by spadla do
`$zdarmaNoci` — mezi noci *poskytnuté* zdarma, se kterými nemá nic společného. Trička to
mají odolnější: `jeZdarma()` vedle nulové ceny vyžaduje i nenulovou slevu, takže „nula
protože zaokrouhleno" se u nich od „nula protože zdarma" pozná.

Dnes to nehrozí — nejlevnější ubytování nabízené pro ročník 2026 stojí 400 Kč, žádný
produkt nemá cenu pod 0,50 Kč a v roce 2026 není ani jeden nákup ubytování za nulu. Muselo
by to spustit sleva, po které cena klesne pod 0,50 Kč — u nejlevnějšího ubytování (400 Kč)
je to přes 99,875 %, u levnějšího zboží stačí sleva menší. Kdyby k tomu někdy došlo, oprava patří do `BfsrReport`:
rozlišit podle slevy (jako u triček) nebo podle role, ne podle výsledné ceny.

Dvě známé nedotažené věci (obojí zatím bez následku):

- `original_price` a `discount_amount` na nákupu popisují cenu **před** zaokrouhlením, takže
  `OrderItem::getSavings()` a `Order::getTotalSavings()` se o ten rozdíl rozejdou. Ani jeden
  getter dnes nemá volajícího. Zaokrouhluje se nahoru od poloviny, takže u 42,60 pult účtuje
  43 — tedy **víc** než cena na pultu a `getSavings()` vyjde záporně. Kdyby to někdy vadilo,
  je to rozhodnutí o ceníku (účtovat vždy max. cenu z pultu), ne o zaokrouhlovací funkci.
- **Legacy `Shop::prodat()` nezaokrouhluje.** Tentýž předmět za 42,40 se přes admin mřížku
  zaúčtuje za 42,40 a přes pult za 42. Každá cesta je sama v sobě vyrovnaná, ale liší se —
  a zrovna zaokrouhlení je poslední osa, ve které si obě cesty neodpovídají. (Legacy mřížka
  navíc už dnes *zobrazuje* `round($cena)`, zatímco účtuje nezaokrouhleno.)

## Neúspěšný prodej musí nechat pult použitelný

Obchodní chyba (vyprodáno, po termínu) není výjimečný stav — na pultu nastane běžně a
obsluha na ni musí dostat hlášku, ne chybu 500. `EntityManager::wrapInTransaction()` to
neumí: na jakékoli výjimce volá `close()` (`vendor/doctrine/orm/src/EntityManager.php:195`),
takže se prodej řídí ručně a rollbackuje sám.

**`clear()` v rollbacku je past.** Odpojí totiž i přihlášeného operátora, kterého drží
bezpečnostní token — a *další* prodej pak spadne na `A new entity was found through
OrderItem#orderer`. Zahazuje se proto jen rozepsaný prodej (`getScheduledEntityInsertions()`
a `getScheduledEntityUpdates()`), ne celá identity mapa.

**Rollback nevrátí, co si Doctrine mezitím načetlo.** `CapacityManager::purchase()` odepíše
kus syrovým SQL a hned si variantu `refresh()`ne, takže je v identity mapě čistá a v žádném
seznamu rozepsaných změn. Po rollbacku pak databáze hlásí původní zásobu, ale varianta
v paměti tu sníženou — proto se dotčené varianty na konci ještě jednou refreshnou.

Pozor na to při psaní testů: `isOpen() === true` tuhle chybu **nechytí**, protože manager
otevřený je, a assert přes syrové SQL taky ne, protože databáze je po rollbacku v pořádku.
Prokáže ji až čtení přes entitu a další prodej ve stejném testu.

## Co se ví a zatím neudělalo

**Příznak místo `poznamka`** (záměr): klasifikovat „prodej na pultu" textem je křehké —
nedá se dotazovat jinak než `LIKE`, nejde přeložit, uklepnutí se nepozná. Chce to typovaný
sloupec na `platby` (druh platby) a `sumaPlateb()` z něj vyrábět popisek, jako už dnes
zvlášť řeší `provedl = SYSTEM`.

Obě cesty už píšou **stejný tvar**: legacy `prodat()` i pokladna KFC zakládají vlastní
objednávku na každý prodej, zapisují nákup na `Uzivatel::ANONYM` a platbu s vazbou na tu
objednávku. Zavedení příznaku proto musí pokrýt obě naráz. Kdyby příznak dostala jen jedna,
vznikly by dva popisky pro tutéž událost. Až se zavede, má pokrýt oba zapisovatele naráz
a `NULL` musí dál znamenat „jako dosud" kvůli historii.
