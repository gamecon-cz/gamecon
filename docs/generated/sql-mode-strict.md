# Cíl: zapnout `STRICT_TRANS_TABLES`

TL;DR: server běží v nestriktním režimu, takže MariaDB místo chyby tiše ořezává data. Chceme striktní režim pro celou aplikaci; zatím to nejde — padají testy. Migrace už si striktní režim zapínají samy.

## Kde to je

- `symfony/src/Command/MigrationsContinueCommand.php` — `zapniPrisnyRezim()`, jediné místo, kde dnes striktní režim platí
- `@@GLOBAL.sql_mode` serveru: `ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION` — žádný `STRICT_*`

## Co nestriktní režim dělá

Není to „jen varování". Příkaz **projde a ohlásí úspěch**, jen data cestou zmizí:

| režim | `DECIMAL(10,2)` → `DECIMAL(4,2)` nad hodnotou `12345.67` |
|---|---|
| dnešní (nestriktní) | `ALTER` projde, `Warning 1264`, uloží se **`99.99`** |
| `STRICT_ALL_TABLES` | `ERROR 1264`, `ALTER` odmítnut, `12345.67` **zůstane** |

Ověřeno na 71 709 řádcích `shop_nakupy`. Totéž platí pro běžný `INSERT`/`UPDATE`: příliš dlouhý řetězec se uřízne, číslo mimo rozsah se přimáčkne na mez, a aplikace se nic nedozví.

## Stav (záměr)

**Migrace** — hotovo. `migrations:continue` si přidá `STRICT_ALL_TABLES` a po doběhnutí vrátí původní hodnotu zpátky (i při selhání). Mění jen svoje spojení, globální nastavení serveru nechává být. `STRICT_ALL_TABLES`, ne `STRICT_TRANS_TABLES`, protože ten druhý se netýká netransakčních tabulek a MyISAM by ořezával dál.

**Aplikace** — chceme, zatím nejde. Při zapnutí `STRICT_TRANS_TABLES` **padají testy**; přesný důvod není zapsaný (nejisté — jde o vzpomínku, ne o změřený závěr). Začít je proto potřeba sesbíráním konkrétních selhání, ne hádáním.

Jeden doložený kandidát: kód na třech místech počítá s nulovým datem `'0000-00-00'` v `datum_narozeni` (`model/uzivatel.php:447`, `:2527`, `:2542`) a `Finance::sumaPlateb()` ho odfiltrovává přes `NULLIF` u `platby.pripsano_na_ucet_banky`. Nulové datum striktní režim odmítá (`NO_ZERO_DATE`), takže cokoli takovou hodnotu zapisuje, v něm spadne. V dnešních datech `platby` žádné nulové datum není, takže to sama o sobě nemusí být ta příčina — ale je to první místo, kam se podívat.

## Proč to chceme

Dokud režim není striktní, každý `INSERT` je potenciálně tichá ztráta — a to je přesně ta třída chyby, kterou testy ani statická analýza nezachytí, protože databáze ohlásí úspěch.
