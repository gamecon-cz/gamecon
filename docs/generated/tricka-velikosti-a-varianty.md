# Trička, mikiny a velikosti

TL;DR: velikost trička je v legacy modelu **samostatný produkt**, ne varianta — 477 z 486 produktů má velikost zakódovanou v `kod_predmetu`. Nový model umí varianty s vlastní cenou i kapacitou, takže se to dá rozpadnout; dokument popisuje, jak jsou data reálně tvarovaná a kde jsou pasti.

## Rozsah

Produkty, které mají v legacy **jednu variantní dimenzi** rozepsanou do samostatných řádků: tagy `tricko`, `mikina` a ponožky (tag `predmet`). Ubytování má dimenze tři a nejde převést — viz níž.

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
- **Ponožky mají velikost taky, ale jinou konvencí**: `ponozky_2021_vel_38_39` / `ponozky_2021_vel_42_45`, tedy `_vel_<od>_<do>`. Dvě velikosti × 6 ročníků = **12 produktů, 6 skupin, 475 nákupů**. Regex psaný na `_L_`/`_XXL_` je tiše minul — při hledání variantních produktů se nedá spolehnout na jednu konvenci.
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

## Ubytování potřebuje options, ne varianty

Ubytování je taky variantní, ale má **tři nezávislé dimenze**: den (St–Ne), kapacita pokoje (1L/2L/3L) a typ ubytování (kolej / hotel / hotel deluxe / dvojbuňka, se snídaní nebo bez). Kód to nese celé: `Hd-2L-pa` = hotel deluxe, dvoulůžák, pátek.

Data jsou dokonale pravidelná — každý ročník je „počet druhů × 5 dní" bez děr, letos 8 × 5 = 40 produktů, a takhle to jde zpátky až do 2009.

**Nový model to zatím neumí.** `product_variant` má natvrdo sloupec `accommodation_day` (SMALLINT 0–4) — jednu pojmenovanou dimenzi, ne obecný mechanismus. Tabulka pro options/atributy neexistuje. Přitom `NEW_ESHOP.md` má „**Produktové možnosti s více hodnotami** — konfigurovatelné vlastnosti produktů (barva, velikost atd.)" označené ✅, takže je to jedna z odsouhlasených, ale nepostavených věcí. Sloupec `accommodation_day` je vlastně důkaz té mezery — vznikl jako výjimka, protože obecná cesta chyběla.

Pozor na svůdnou zkratku: **kapacita (1L/2L/3L) není „velikost" pokoje.** Matice není pravoúhlá (kolej má 1L/2L/3L, hotely 1L/2L, dvojbuňka jen 1L), ceny se nerozkládají (1L→2L je −500 na koleji, ale −400 v hotelu) a sklad je per dvojice ubytování×kapacita a řádově jiný (kolej 1L = 131 lůžek, hotel deluxe 1L = 3). Jsou to různá fyzická místa, ne velikosti jednoho produktu. Jediná čistě separovatelná dimenze je **den** — v rámci jednoho druhu pokoje mají všech 5 dní totožnou cenu i kapacitu.

**Rozhodnutí:** teď se převádějí jen jednodimenzionální varianty (trička, mikiny, ponožky). Ubytování se převede najednou na multi-options, ne po částech — dělat teď zvlášť „den jako varianta" by byl polovičatý krok, který se stejně zahodí. (záměr — sděleno uživatelem)

## Gotchas

- **Cena i kapacita jsou per-varianta.** `product_variant.price` je `DECIMAL(6,2) NULL`; `NULL` = dědí z produktu. Ověřeno: varianta s `price = NULL` vrátí cenu produktu, sourozenec s vlastní cenou vrátí svou. `OrderItem` si při nákupu ukládá `getEffectivePrice()` do `original_price`, takže se do historie zamrazí cena varianty.
- **Neseskupuj podle názvu.** Viz výše — dvě třetiny by se rozpadly špatně.
- **Neseskupuj bez ročníku.** Splynuly by roky.
- `shop_nakupy` ukazuje na konkrétní produkt-velikost; při případném slučování produktů (mimo rozsah scope A) by se musely přepsat reference u 4 773 nákupů.

## Otevřené otázky

- Sloučit sized + bezvelikostní řádek do jednoho produktu, až bude merch storefront na nové vrstvě? (viz výše — směr je jasný, načasování ne)
- Kde v adminu se velikosti zakládají a jestli to po rozpadu na varianty potřebuje jiné UI.
- Option systém pro ubytování (viz sekce výše) — schéma navíc (`product_option`, `product_option_value`, join na variantu), ne datová migrace.
