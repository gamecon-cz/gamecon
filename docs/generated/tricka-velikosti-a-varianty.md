# Trička, mikiny a velikosti

TL;DR: velikost trička je v legacy modelu **samostatný produkt**, ne varianta — 477 z 486 produktů má velikost zakódovanou v `kod_predmetu`. Nový model umí varianty s vlastní cenou i kapacitou, takže se to dá rozpadnout; dokument popisuje, jak jsou data reálně tvarovaná a kde jsou pasti.

## Rozsah

Tagy `tricko` a `mikina`. Netýká se ubytování (to má vlastní logiku dní) ani jídla.

## Vstupní body v kódu

- `model/Shop/Shop.php::predmetyHtml()` — legacy render; sekce MIKINY a TRIČKA jedou přes `renderOpakovanyVyberPredmetu()`. **Legacy nemá pojem velikosti vůbec** — dostane seznam produktů a vyrenderuje ho.
- `symfony/src/Entity/ProductVariant::getEffectivePrice()` — `$this->price ?? $this->product->getCurrentPrice()`; `NULL` znamená „dědí se z produktu", ne „zdarma". Stejný vzorec u `reservedForOrganizers`.
- `symfony/migrations/structures/2026-09-02-100010_default-variant-from-legacy-product.php` — dnešní stav: každý produkt dostal jednu výchozí variantu pojmenovanou po produktu.

## Jak jsou data tvarovaná

Velikost je v `kod_predmetu`, ne spolehlivě v názvu:

```
tricko_panske_organizatorske_L_2026        <base>_<SIZE>_<rok>
tricko_panske_ucastnicke_XXL_2024_1467     <base>_<SIZE>_<rok>_<id>   (id rozlišuje duplicitní kódy)
mikina_2026_verne_xs                       velikost malými písmeny
```

- **486 produktů** s tagem `tricko`/`mikina`, z toho **477** má velikost v kódu.
- Podle názvu by jich bylo jen 132 — **název je nespolehlivý, kód je signál**.
- Seskupení musí být podle `<base kód> + ročník`, ne jen podle base kódu: bez ročníku splynou všechny roky dohromady (`tricko_panske_organizatorske` by mělo 82 „velikostí"). Se správným klíčem vznikne **112 skupin po 6–7 velikostech**, žádná osamocená, žádná se dvěma cenami.
- Velikosti v datech: S, M, L, XL, XXL, XXXL, jedno `xs`.
- **Identifikátor je `kod_predmetu`, ne `nazev`.** `kod_predmetu` je UNIQUE a nese ročník; `nazev` je jen štítek pro zákazníka a **smí se opakovat napříč ročníky** — „Tričko červené pánské L" existuje pro 2016–2025 a jsou to různé produkty.
- Migrace `100001` původně zaváděla i `UNIQUE(nazev)`, což si vynutilo umělé suffixy `(#1464)` u **785 z 1135 produktů**. Constraint i suffixy jsou pryč (nic na jedinečnosti názvu nestálo: `ProductRepository` má jen `findByCode()`, importér páruje přes `kod_predmetu`, `Predmet::jeToModre()` dělá substring match, reporty grupují per uživatel a ročník).

## Dva prodejní kanály téhož trička

Pro ročník existují **obě** podoby zároveň (příklad 2026, obojí za 400 Kč):

| produkt | stav | nákupů | kdy se kupovalo |
|---|---|---|---|
| `tricko_panske_ucastnicke_{S..XXXL}_2026` (6 produktů) | 1 = VEŘEJNÝ | 190 | 13. 5. – 15. 7. (přihláška) |
| `tricko_tilko_ucastnicke_2026` (bez velikosti) | 3 = POZASTAVENÝ | 27 | 22. – 26. 7. (festival) |

Okna se **nepřekrývají ani o den**. Bezvelikostní řádek je skrytý z e-shopu (`stav = 3`) a používá se pro interní/pultový prodej během festivalu.

**Není to ale samostatný doménový koncept.** Zákazník si i na pultu vybere velikost — je to *totéž* tričko a *tytéž* varianty, jen prodané interně. Bezvelikostní produkt je obcházení systému kvůli zjednodušení (přesný důvod neznámý), ne druhý druh zboží. (záměr — sděleno uživatelem)

**Cílový stav:** jeden produkt s variantami podle velikosti; interní prodej se řeší skrytím z e-shopu, ne vlastním produktem. Neřeší se teď — vyžaduje to nejdřív překlopení merch sekce storefrontu.

Takových bezvelikostních řádků je celkem **9** (`tricko_tilko_*`, `tricko_stare*`). Do rozpadu na velikosti nevstupují a zůstávají jednovariantní.

## Gotchas

- **Cena i kapacita jsou per-varianta.** `product_variant.price` je `DECIMAL(6,2) NULL`; `NULL` = dědí z produktu. Ověřeno: varianta s `price = NULL` vrátí cenu produktu, sourozenec s vlastní cenou vrátí svou. `OrderItem` si při nákupu ukládá `getEffectivePrice()` do `original_price`, takže se do historie zamrazí cena varianty.
- **Neseskupuj podle názvu.** Viz výše — dvě třetiny by se rozpadly špatně.
- **Neseskupuj bez ročníku.** Splynuly by roky.
- `shop_nakupy` ukazuje na konkrétní produkt-velikost; při případném slučování produktů (mimo rozsah scope A) by se musely přepsat reference u 4 773 nákupů.

## Otevřené otázky

- Sloučit sized + bezvelikostní řádek do jednoho produktu, až bude merch storefront na nové vrstvě? (viz výše — směr je jasný, načasování ne)
- Kde v adminu se velikosti zakládají a jestli to po rozpadu na varianty potřebuje jiné UI.
