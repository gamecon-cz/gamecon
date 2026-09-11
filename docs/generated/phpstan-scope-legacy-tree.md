# PHPStan analyzuje jen `symfony/` — a co se tím schová

TL;DR: `phpstan.dist.neon` má v `paths:` jen `symfony/config/` a `symfony/src/`, takže cokoli volané z `model/`, `admin/`, `web/` nebo z testů vypadá pro detektor mrtvého kódu jako bez volajícího. Než e-shop dojede do `main`, má se `model/` do analýzy přidat a dočasné potlačení zrušit.

## Vstupní body

- `phpstan.dist.neon:8` — `paths:` (dnes jen `symfony/config/`, `symfony/src/`)
- `phpstan.dist.neon` — `ignoreErrors:` blok `Unused App\(Entity|Discount)\…::__construct` (dočasný, viz níže)
- `phpstan.dist.neon:74` — `shipmonkDeadCode.usageProviders.symfony`, čte zkompilovaný kontejner
- `symfony/config/services.yaml:21` — `exclude:` vyřazuje `../src/Entity/` z DI

## Co je dočasně potlačené

Deset nálezů `Unused …::__construct`:

| Třída | volající |
|---|---|
| `App\Discount\DiscountCalculation` | `model/Uzivatel/Cenik.php:271` |
| `App\Discount\DiscountableItem` | `model/Uzivatel/Cenik.php:306` |
| `App\Discount\DiscountRuleLoader` | `model/Uzivatel/Cenik.php:338` |
| `App\Entity\ProductVariant` | 17 volání mimo `symfony/src` |
| `App\Entity\User` | 10 volání mimo `symfony/src` |
| `App\Entity\ProductBundle`, `ProductDiscount` | po 5 voláních mimo `symfony/src` |
| `App\Entity\Activity`, `CategoryTag`, `Tournament` | testy / legacy |

Všechny jsou **falešně pozitivní** — volající existuje, jen leží mimo analyzovaný strom. Entity navíc nejsou službami (`services.yaml` je z DI vyřazuje), takže je nepotvrdí ani kontejner.

Potlačení je **vyjmenované po třídách**, ne plošné na adresáře: `symfony/src/Entity/` a
`symfony/src/Discount/` dohromady obsahují ~69 tříd a plošné pravidlo by umlčelo i mrtvý
konstruktor přidaný později. Stejný důvod uvádí pravidlo o pár řádků níž u kontrolerů.

## Proč se objevily až teď

Nálezy tam byly celou dobu; schovávaly je dvě věci naráz.

1. **Dokud v `symfony/src/` žilo `Rector/ReorderAttributeArgumentsRector.php`** (než se přesunulo do `tests/Rector/Rules/`). Ten dědil z `Rector\Rector\AbstractRector`, a už jeho reflexe registrovala **Rectorův vlastní autoloader**, což změnilo, co detektor dokáže rozřešit. Ověřeno A/B: s tím souborem 0 nálezů, s inertním placeholderem na stejné cestě 8 nálezů. Nešlo tedy o přítomnost souboru, ale o tu vedlejší reakci — stejná past, kterou popisuje kořenový `CLAUDE.md` u Rectoru a jeho neprefixovaného `php-parser`.

2. **Po rebase na main PHPStan vůbec neběžel.** Baseline držel záznam na cestu, kterou main smazal, a neexistující cesta v `ignoreErrors` je pro PHPStan fatální chyba konfigurace — spadne **před** analýzou prvního souboru. Zelená kontrola tedy neznamenala „bez chyb", ale „nespustilo se". (`reportUnmatchedIgnoredErrors: false` by to utišilo, ale tím by se ztratila i detekce zastaralých baseline záznamů — proto radši odstranit záznam.)

## Co udělat, než e-shop dojede do `main` (záměr)

1. Přidat `model/` do `paths:` (případně i `admin/`, `web/`) — tím zmizí příčina, ne symptom.
2. Smazat dočasný `ignoreErrors` blok pro `__construct` a ověřit, že nálezy opravdu zmizely.
3. Počítat s tím, že rozšíření scope odhalí vlnu nových nálezů v legacy stromu; je to práce na vlastní commit, ne přílepek.

Dokud scope nezahrnuje legacy strom, platí obecné pravidlo z kořenového `CLAUDE.md`: **co tenhle nástroj označí za živé, živé je; co označí za mrtvé, se musí ověřit grepem přes celý strom.**
