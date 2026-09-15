# Přihláška — odeslání objednávky

TL;DR: co se stane po odeslání veřejné přihlášky (`/prihlaska`) — pořadí zpracování, názvy POST polí, kde se hlídá vyprodání a kde ne. Pokrývá zápis objednávky, ne vykreslení formuláře.

## Vstupní body v kódu

- `web/moduly/prihlaska/prihlaska.php` — modul; větev `post('prihlasitNeboUpravit')` je vlastní zpracování
- `model/Shop/Shop.php::zpracujUbytovani`, `::zpracujJidlo`, `::prodat`
- `model/Shop/ShopUbytovani.php::zpracuj`, `::ulozObjednaneUbytovaniUcastnika`, `::validujVybraneNociUbytovani`
- `tests/Shop/AbstractTestPrihlaska.php` — testy jedou stejnou sekvenci jako modul

## Pořadí zpracování je významné

Modul volá v tomhle pořadí a celé to obaluje jednou transakcí:

```
gcPrihlas → zpracujUbytovani → zpracujJidlo → Pomoc::zpracuj → finance()->obnovUdaje
```

Ubytování musí předcházet jídlu: `zpracujJidlo()` se ptá `ubytovani->dnyHotelovychPokoju()`, aby vyhodilo snídaně, které jsou v ceně hotelu. Při přehození by snídaně u hotelových pokojů prošly.

Jakákoli `Chyba` uvnitř shodí `dbRollback()` a celá přihláška se zahodí — účastník neskončí s uloženým ubytováním a neuloženým předmětem.

## Sekce formuláře jsou nezávislé

Každá sekce se zpracuje, jen když v POSTu je její klíč; jinak zůstane beze změny (ne prázdná). Klíče:

| Sekce | POST klíč | Tvar |
|---|---|---|
| Jídlo | `cShopJidlo[<id>]` + **`cShopJidloZmen`** | bez `cShopJidloZmen` se jídlo vůbec nezpracuje |
| Ubytování | `shopUbytovaniDny[<den>]` | hodnota = id předmětu, `''` = žádné |
| Nechci ubytování | `shopUbytovaniNechci` | přítomnost |

Předměty, trička, mikiny a vstupné už formulářem nechodí vůbec — kupují se košíkovým API
(`/cart/*`, `/cart/entry-fee`). Jejich sekce se sice pořád vykreslují jako noscript
fallback, ale `prihlaskaPreactSekceHtml()` je balí do `<fieldset disabled>`, a zakázaná
pole prohlížeč neodesílá. Zpracování na straně přihlášky proto neexistuje.

Počet kusů = **počet řádků** v `shop_nakupy`; tabulka nemá unique přes (uživatel, předmět, rok). Proto se objednávka aktualizuje diffem starých a nových řádků, ne přepsáním.

## Kde se hlídá vyprodání — a kde ne

Z přihlášky volá `Shop::prodat()` už jen jídlo (přes `zmenObjednavku()`). Mimo přihlášku ji
používá ještě ruční prodej v adminu — `admin/scripts/modules/_shop.php` a
`_uzivatel_ovladac.php`. Zamyká řádek (`FOR UPDATE`) a odmítne:

- předmět z jiného ročníku (`model_rok != rocnik`)
- objednávku přes zásobu, když `kusu_vyrobeno IS NOT NULL` (`kusu_vyrobeno` = NULL znamená neomezeně)

Ubytování jde **mimo `prodat()`** — vlastní cestou v `ShopUbytovani::ulozObjednaneUbytovaniUcastnika()`, která hlídá ročník + typ, kapacitu, a navíc: minimálně dvě noci (pokud uživatel nemá `Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC`) a noci na sebe musí navazovat.

### Příznak `nabizet` řídí jen vykreslení

`nabizet` (počítaný v konstruktoru `Shop` ze `stav` a `nabizet_do`) rozhoduje o tom, co se
vykreslí; `prodat()` `stav` ani `nabizet_do` nekontroluje. Totéž platí pro termíny
`*_LZE_OBJEDNAT_A_MENIT_DO_DNE`.

Dřív z toho plynula díra — ručně poskládaný POST koupil i stažený předmět. Ta je pryč
spolu se `zpracujPredmety()`: sekce, které by se daly takhle podstrčit, už žádný zápis
nemají. Jídlo, které jako jediné `prodat()` ještě používá, si termín ani stav samo
nehlídá, takže **na něj se to pořád vztahuje**.

## Gotchas při psaní testů

- **Vlastní transakce.** Přihláška si otevírá a commituje vlastní transakci, takže obalující transakce testu by se commitla s ní. Testy proto vypínají `keepTestClassDbChangesInTransaction()` i `keepSingleTestMethodDbChangesInTransaction()` a nechávají resetovat DB po každé metodě.
- **`Uzivatel` je staticky cachovaný.** Po zápisu je potřeba `\Uzivatel::smazCache()`, jinak další čtení vrátí starý objekt.
- **Vykreslení předmětů potřebuje konstanty termínů**, které testovací bootstrap nedefinuje (`PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE` a spol.) — doplní se přes `try_define()` + `dejVychoziHodnotu()`. A protože jejich výchozí hodnoty leží uprostřed ročníku, je potřeba posunout „teď“ na začátek roku, jinak vykreslení hlásí ukončený prodej.
- **XTemplate bez nastavené cache** si odkládá zkompilovanou šablonu vedle zdroje, tedy do gitem sledovaného stromu. Před voláním kteréhokoli `*Html()` je potřeba nastavit `XTemplate::cache()`.
- **Testovací DB je prázdná** — migrace `shop_predmety` neplní, takže v ní jsou jen předměty, které si test sám vloží. Dvě pasti z toho plynoucí:
  - Když jsou *všechny* předměty pozastavené, `predmetyHtml()` zamkne celou sekci. Test s jediným staženým předmětem tedy projde, i kdyby se na jednotlivé předměty vůbec nehledělo — je potřeba vedle něj vytvořit i nabízený předmět.
  - Nenabízený předmět, který už má účastník koupený, se stejně vykreslí (`|| $predmet['kusu_uzivatele']`
    v `renderPredmet()`) a **markupem se od nabízeného nijak neliší** — `data-max` nese jen blok
    `nakup`, který se od přechodu na košík nerenderuje vůbec. Test, který se ptá na nabídku,
    proto nesmí nic koupit; helper se jmenuje `jeVidetVNabidce()`, ne „jde koupit".
- **Rollback celé přihlášky se dá otestovat jen selháním, které přijde po nějakém zápisu.** Vyprodaný *předmět* padá jako první, takže se do té doby nic neuložilo a test by prošel i bez rollbacku; vyprodané *jídlo* se zpracovává až po ubytování, takže shodí přihlášku s už zapsanými nocemi.

## Co pokrývají testy nabídky

`Shop::predmetyHtml()` je pořád volaný z `web/moduly/prihlaska/prihlaska.php`, i když se
merch kupuje přes košíkové API — `PrihlaskaNabidkaPredmetuTest` proto hlídá, že nabídka
nevykreslí pozastavený předmět ani předmět po `nabizet_do`. Každý test k tomu zakládá
i běžný předmět: kdyby byl ten nenabízený jediný, zamkla by se celá sekce a test by prošel
naprázdno.

Helper `jeVidetVNabidce()` hledá `name="shopP[<id>]"` a schválně **ne** `data-max` — viz
gotcha výše: `data-max` nese jen blok `nakup`, který se nerenderuje, takže matcher psaný
na něj by nenašel nikdy nic.
