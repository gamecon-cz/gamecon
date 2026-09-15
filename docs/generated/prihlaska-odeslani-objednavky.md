# Přihláška — odeslání objednávky

TL;DR: **veřejná přihláška (`/prihlaska`) už žádnou objednávku nezapisuje** — všechno jde
přes košíkové API. Zbylo z ní jen přihlášení na GC. Dokument drží, co se tam děje dnes, kde
zůstal legacy zápis (admin) a jaké pasti po převodu zbyly.

## Vstupní body v kódu

- `web/moduly/prihlaska/prihlaska.php` — modul; větev `post('prihlasitNeboUpravit')` je vlastní zpracování
- `symfony/src/Service/AccommodationWriter.php`, `CartService.php`, `EntryFeeService.php` — kam se zápis přesunul
- `model/Shop/Shop.php::zpracujUbytovani`, `::zpracujJidlo`, `::prodat` — legacy zápis, dnes už **jen z adminu**
- `admin/scripts/modules/_uzivatel_ovladac.php`, `admin/scripts/modules/infopult/_infopult_ovladac.php` — jeho volající
- `tests/Shop/AbstractTestPrihlaska.php` — testy jedou stejnou sekvenci jako modul

## Co po odeslání zbylo

Modul dělá v transakci tohle a nic víc:

```
gcPrihlas → Pomoc::zpracuj → finance()->obnovUdaje
```

**Žádná sekce se ze `$_POST` nezapisuje.** Všechny — předměty, trička, mikiny, vstupné,
ubytování i jídlo — chodí přes košíkové API (`/cart/*`, `/cart/entry-fee`). Sekce se sice
pořád vykreslují jako noscript fallback, ale `prihlaskaPreactSekceHtml()` je balí do
`<fieldset disabled>`, a zakázaná pole prohlížeč neodesílá.

Tím zmizely dvě vlastnosti, na které se dřív dalo spolehnout:

- **Pořadí už nic neřeší.** Dřív muselo ubytování předcházet jídlu, protože `zpracujJidlo()`
  ruší snídaně v ceně hotelu. Dnes to řeší `AccommodationWriter` sám při zápisu nocí.
- **Není transakce přes celou přihlášku.** Košík zapisuje po requestech, takže neexistuje
  stav „ubytování uloženo, jídlo selhalo, zahoď obojí". Každá sekce stojí sama za sebe.

**Pozor na degradovanou větev.** `prihlaskaPreactSekceHtml()` má dvě místa, kde vrací
`$legacyHtml` holý — když uživatel není v Doctrine a když selže příprava Symfony kontextu
(kernel, kontejner, Doctrine i JWT, všechno pod jedním `catch (\Throwable)`). Tam se sekce
vykreslí **zapnutá** a zároveň se nenačte Preact bundle, takže uživatel dostane formulář,
který vypadá editovatelně, nemá za sebou košík a jehož POST nikdo nezpracuje. Tiše se
neuloží nic. Past je to hlavně pro toho, kdo by sem zápis vracel zpátky.

Počet kusů = **počet řádků** v `shop_nakupy`; tabulka nemá unique přes (uživatel, předmět, rok). Proto se objednávka aktualizuje diffem starých a nových řádků, ne přepsáním.

## Kde se hlídá vyprodání — a kde ne

`Shop::prodat()` už z přihlášky nevolá nic — zbyl jen ruční prodej v adminu
(`admin/scripts/modules/_shop.php`, `_uzivatel_ovladac.php`) a jídlo přes
`zmenObjednavku()`, které tamtéž volá `zpracujJidlo()`. Zamyká řádek (`FOR UPDATE`) a odmítne:

- předmět z jiného ročníku (`model_rok != rocnik`)
- objednávku přes zásobu, když `kusu_vyrobeno IS NOT NULL` (`kusu_vyrobeno` = NULL znamená neomezeně)

Ubytování jde **mimo `prodat()`** — vlastní cestou v `ShopUbytovani::ulozObjednaneUbytovaniUcastnika()`, která hlídá ročník + typ, kapacitu, a navíc: minimálně dvě noci (pokud uživatel nemá `Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC`) a noci na sebe musí navazovat.

### Příznak `nabizet` řídí jen vykreslení

`nabizet` (počítaný v konstruktoru `Shop` ze `stav` a `nabizet_do`) rozhoduje o tom, co se
vykreslí; `prodat()` `stav` ani `nabizet_do` nekontroluje. Totéž platí pro termíny
`*_LZE_OBJEDNAT_A_MENIT_DO_DNE`.

Dřív z toho na přihlášce plynula díra — ručně poskládaný POST koupil i stažený předmět.
Ta je pryč s posledním formulářovým zápisem. **V adminu se to ale pořád vztahuje na jídlo
i ruční prodej**, které `prodat()` volají dál a termín ani stav si samy nehlídají.

## Gotchas při psaní testů

- **Vlastní transakce.** Přihláška si pořád otevírá a commituje vlastní transakci (kvůli `gcPrihlas`), takže obalující transakce testu by se commitla s ní. Testy proto vypínají `keepTestClassDbChangesInTransaction()` i `keepSingleTestMethodDbChangesInTransaction()` a nechávají resetovat DB po každé metodě.
- **`Uzivatel` je staticky cachovaný.** Po zápisu je potřeba `\Uzivatel::smazCache()`, jinak další čtení vrátí starý objekt.
- **Vykreslení předmětů potřebuje konstanty termínů**, které testovací bootstrap nedefinuje (`PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE` a spol.) — doplní se přes `try_define()` + `dejVychoziHodnotu()`. A protože jejich výchozí hodnoty leží uprostřed ročníku, je potřeba posunout „teď“ na začátek roku, jinak vykreslení hlásí ukončený prodej.
- **XTemplate bez nastavené cache** si odkládá zkompilovanou šablonu vedle zdroje, tedy do gitem sledovaného stromu. Před voláním kteréhokoli `*Html()` je potřeba nastavit `XTemplate::cache()`.
- **Testovací DB je prázdná** — migrace `shop_predmety` neplní, takže v ní jsou jen předměty, které si test sám vloží. Dvě pasti z toho plynoucí:
  - Když jsou *všechny* předměty pozastavené, `predmetyHtml()` zamkne celou sekci. Test s jediným staženým předmětem tedy projde, i kdyby se na jednotlivé předměty vůbec nehledělo — je potřeba vedle něj vytvořit i nabízený předmět.
  - Nenabízený předmět, který už má účastník koupený, se stejně vykreslí (`|| $predmet['kusu_uzivatele']`
    v `renderPredmet()`) a **markupem se od nabízeného nijak neliší** — `data-max` nese jen blok
    `nakup`, který se od přechodu na košík nerenderuje vůbec. Test, který se ptá na nabídku,
    proto nesmí nic koupit; helper se jmenuje `jeVidetVNabidce()`, ne „jde koupit".
- **Rollback celé přihlášky se testovat nedá, protože už neexistuje.** Košík zapisuje po
  requestech. Test, který takovou atomicitu ověřoval, se proto smazal bez náhrady — kdyby
  ji někdo chtěl zpátky, musela by se nejdřív zavést na straně košíku.

## Co pokrývají testy nabídky

`Shop::predmetyHtml()` je pořád volaný z `web/moduly/prihlaska/prihlaska.php`, i když se
merch kupuje přes košíkové API — `PrihlaskaNabidkaPredmetuTest` proto hlídá, že nabídka
nevykreslí pozastavený předmět ani předmět po `nabizet_do`. Každý test k tomu zakládá
i běžný předmět: kdyby byl ten nenabízený jediný, zamkla by se celá sekce a test by prošel
naprázdno.

Helper `jeVidetVNabidce()` hledá `name="shopP[<id>]"` a schválně **ne** `data-max` — viz
gotcha výše: `data-max` nese jen blok `nakup`, který se nerenderuje, takže matcher psaný
na něj by nenašel nikdy nic.
