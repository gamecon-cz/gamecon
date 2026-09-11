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
- **Testovací DB je prázdná** — migrace `shop_predmety` neplní, takže v ní jsou jen předměty, které si test sám vloží. Dvě pasti z toho plynoucí:
  - Když jsou *všechny* předměty pozastavené, `predmetyHtml()` zamkne celou sekci. Test s jediným staženým předmětem tedy projde, i kdyby se na jednotlivé předměty vůbec nehledělo — je potřeba vedle něj vytvořit i nabízený předmět.
  - Nenabízený předmět, který už má účastník koupený, se stejně vykreslí (blok `fixniPocet`) se stejným `name="shopP[<id>]"`. Nabídku odliší až `data-max`, které nese jen nákupní varianta.
- **Rollback celé přihlášky se dá otestovat jen selháním, které přijde po nějakém zápisu.** Vyprodaný *předmět* padá jako první, takže se do té doby nic neuložilo a test by prošel i bez rollbacku; vyprodané *jídlo* se zpracovává až po ubytování, takže shodí přihlášku s už zapsanými nocemi.

## Chybějící pokrytí (nice to have)

Při přechodu merche na košíkové API zmizely s `shopP` testy i tři věci, které s merchem
nesouvisí. Nejsou nahrazené jinde — stojí za to je vrátit, až na ně bude čas.

| Co | Kde to bylo | Proč to stojí za návrat |
|---|---|---|
| Přihlášení na GC | `PrihlaskaBeznyPripadTest::beznyUzivatelSePrihlasiNaGc` | ověřovalo `gcPrihlasen()` a že se role `PRIHLASEN_NA_LETOSNI_GC` zapíše **právě jednou**; `odesliPrihlasku()` tuhle cestu pořád projde při každém odeslání |
| Dvojí odeslání nezduplikuje | `PrihlaskaBeznyPripadTest::opakovaneOdeslaniPrihlaskySeStejnymObsahemNicNezdvoji` | hlídalo ubytování (4 řádky, ne 2) — tedy přesně tu cestu, kterou tahle větev přepisuje |
| Vykreslení nabídky | `PrihlaskaVyprodanoTest::nabidkaNeobsahujePozastavenyPredmet` + `…PoUplynutiNabizetDo` + `…ObsahujeBeznyPredmet` | `Shop::predmetyHtml()` je pořád volaný z `web/moduly/prihlaska/prihlaska.php:310`, ale od smazání `jeNabizenKProdeji()` ho nerenderuje žádný test; třetí test tam byl schválně, aby první dva nemohly projít naprázdno, když se zamkne celá sekce |

Vyprodanost a překročení zásoby pokryté zůstávají (`ShopProdejPrekroceniZasobTest`,
`CartServiceStockTest`), tady jde jen o výše uvedené.

**Pozor na `PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE` a spol.** Testovací bootstrap
je nedefinuje, ale `SystemoveNastaveni::prodejPredmetuBezTricekDo()` je čte natvrdo, takže
každá cesta, která dojde na merch, spadne na „Undefined constant". Smazaný helper
`jeNabizenKProdeji()` je proto `try_define`oval — kdo bude pokrytí vracet, musí to udělat taky.

### Pozastavený předmět koupitelný ručním POSTem

Níže popsaný rozpor (formulář pozastavený předmět skryje, ale ručně poskládaný POST ho
koupí) hlídal charakterizační test `pozastavenyPredmetJdeKoupitRucnePoskladanymPostem`.
Ten je smazaný spolu s `shopP` — a protože `zpracujPredmety()` `shopP` už vůbec nečte,
**touhle cestou už díra nejspíš není dosažitelná**. Neověřeno: než se to potvrdí, ber
popis níže jako možná neaktuální. Ekvivalentní kontrola na straně košíku (`stav` =
pozastavený → `addItem()` musí odmítnout) zatím nikde není.
