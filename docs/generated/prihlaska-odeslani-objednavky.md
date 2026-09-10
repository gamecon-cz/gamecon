# Přihláška — odeslání objednávky

TL;DR: co se stane po odeslání veřejné přihlášky (`/prihlaska`) — pořadí zpracování, názvy POST polí, kde se hlídá vyprodání a kde ne. Pokrývá zápis objednávky, ne vykreslení formuláře.

## Vstupní body v kódu

- `web/moduly/prihlaska/prihlaska.php` — modul; větev `post('prihlasitNeboUpravit')` je vlastní zpracování
- `model/Shop/Shop.php::zpracujPredmety`, `::zpracujUbytovani`, `::zpracujJidlo`, `::zpracujVstupne`, `::prodat`
- `model/Shop/ShopUbytovani.php::zpracuj`, `::ulozObjednaneUbytovaniUcastnika`, `::validujVybraneNociUbytovani`
- `tests/Shop/AbstractTestPrihlaska.php` — testy jedou stejnou sekvenci jako modul

## Pořadí zpracování je významné

Modul volá v tomhle pořadí a celé to obaluje jednou transakcí:

```
gcPrihlas → zpracujPredmety → zpracujUbytovani → zpracujJidlo → zpracujVstupne → Pomoc::zpracuj → finance()->obnovUdaje
```

Ubytování musí předcházet jídlu: `zpracujJidlo()` se ptá `ubytovani->dnyHotelovychPokoju()`, aby vyhodilo snídaně, které jsou v ceně hotelu. Při přehození by snídaně u hotelových pokojů prošly.

Jakákoli `Chyba` uvnitř shodí `dbRollback()` a celá přihláška se zahodí — účastník neskončí s uloženým ubytováním a neuloženým předmětem.

## Sekce formuláře jsou nezávislé

Každá sekce se zpracuje, jen když v POSTu je její klíč; jinak zůstane beze změny (ne prázdná). Klíče:

| Sekce | POST klíč | Tvar |
|---|---|---|
| Předměty | `shopP[<id>]` | počet kusů |
| Trička | `shopT[<i>]` | hodnota = id předmětu, `0` = žádné |
| Mikiny | `shopM[<i>]` | hodnota = id předmětu, `0` = žádná |
| Jídlo | `cShopJidlo[<id>]` + **`cShopJidloZmen`** | bez `cShopJidloZmen` se jídlo vůbec nezpracuje |
| Ubytování | `shopUbytovaniDny[<den>]` | hodnota = id předmětu, `''` = žádné |
| Nechci ubytování | `shopUbytovaniNechci` | přítomnost |
| Vstupné | `shopV` | částka |

Počet kusů = **počet řádků** v `shop_nakupy`; tabulka nemá unique přes (uživatel, předmět, rok). Proto se objednávka aktualizuje diffem starých a nových řádků, ne přepsáním.

## Kde se hlídá vyprodání — a kde ne

`Shop::prodat()` je jediná cesta zápisu předmětů, triček, mikin a jídla. Zamyká řádek (`FOR UPDATE`) a odmítne:

- předmět z jiného ročníku (`model_rok != rocnik`)
- objednávku přes zásobu, když `kusu_vyrobeno IS NOT NULL` (`kusu_vyrobeno` = NULL znamená neomezeně)

Ubytování jde **mimo `prodat()`** — vlastní cestou v `ShopUbytovani::ulozObjednaneUbytovaniUcastnika()`, která hlídá ročník + typ, kapacitu, a navíc: minimálně dvě noci (pokud uživatel nemá `Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC`) a noci na sebe musí navazovat.

### Rozpor: pozastavený předmět lze koupit ručním POSTem

Příznak `nabizet` (počítaný v konstruktoru `Shop` ze `stav` a `nabizet_do`) řídí **jen vykreslení**. Whitelist v `zpracujPredmety()` staví na všech předmětech ročníku se `stav > MIMO`, tedy včetně `PODPULTOVY` a `POZASTAVENY`, a `prodat()` `stav` ani `nabizet_do` nekontroluje.

Důsledek: předmět stažený z nabídky zmizí z formuláře, ale ručně poskládaný POST ho koupí. Totéž platí pro termíny `*_LZE_OBJEDNAT_A_MENIT_DO_DNE` — konzultují se jen při vykreslování, zpracování je ignoruje.

Chování je zafixované testem `PrihlaskaVyprodanoTest::pozastavenyPredmetJdeKoupitRucnePoskladanymPostem`; až se to opraví, ten test musí spadnout.

## Gotchas při psaní testů

- **Vlastní transakce.** Přihláška si otevírá a commituje vlastní transakci, takže obalující transakce testu by se commitla s ní. Testy proto vypínají `keepTestClassDbChangesInTransaction()` i `keepSingleTestMethodDbChangesInTransaction()` a nechávají resetovat DB po každé metodě.
- **`Uzivatel` je staticky cachovaný.** Po zápisu je potřeba `\Uzivatel::smazCache()`, jinak další čtení vrátí starý objekt.
- **Vykreslení předmětů potřebuje konstanty termínů**, které testovací bootstrap nedefinuje (`PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE` a spol.) — doplní se přes `try_define()` + `dejVychoziHodnotu()`. A protože jejich výchozí hodnoty leží uprostřed ročníku, je potřeba posunout „teď“ na začátek roku, jinak vykreslení hlásí ukončený prodej.
- **XTemplate bez nastavené cache** si odkládá zkompilovanou šablonu vedle zdroje, tedy do gitem sledovaného stromu. Před voláním kteréhokoli `*Html()` je potřeba nastavit `XTemplate::cache()`.
