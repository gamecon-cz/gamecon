# Scénář 6 — po termínu prodeje

**„Teď": 2026-07-20** (ubytování i jídlo se prodávaly do 2026-07-19)

Účastník už nesmí, obsluha v adminu musí. To je celý smysl admin obrazovek.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-07-20
```

## Co projít

| # | krok | očekávání | A | B |
|---|---|---|---|---|
| 6.1 | `/prihlaska`: ubytování | zamčené | ✓ | ✓ |
| 6.2 | `/prihlaska`: jídlo | zamčené | ✓ | ✓ |
| 6.3 | `/prihlaska`: merch (do 15. 7.) | zamčené | ✓ | ✓ |
| 6.4 | `/prihlaska`: tričko (do 1. 8.) | **ještě jde** | ✓ | ✓ |
| 6.5 | už koupené položky | pořád vidět | | ✓ |
| 6.6 | admin: objednat ubytování za účastníka | **musí jít** | n/a | ✓ |
| 6.7 | admin: objednat jídlo za účastníka | **musí jít** | n/a | ✓ |

## Na co si dát pozor

- **6.4** — tričko má jiný termín než zbytek; snadno se přehlédne a zamkne všechno naráz.
- **6.5** — zamčená sekce musí pořád ukazovat, co účastník má. Prázdná sekce je regrese.
- **6.6/6.7** — v nové vrstvě se termín v admin cestě **záměrně nekontroluje**
  (`SetCustomerMealsProcessor`, `SetCustomerAccommodationProcessor`). To je hlavní důvod,
  proč admin endpointy existují.

## Kontrola dat

```bash
bin-diff/porovnej.sh <id_ucastnika>
```

## Zjištěno

## Zjištěno (2026-09-17, po rebase na main)

Ověřeno programově, ne klikáním — obě větve resetovány a posunuty na 2026-07-20.

### 6.1–6.4: termíny se shodují

Stavy sekcí čtené přes `SystemoveNastaveni` jsou na obou větvích **identické**:

| sekce | ukončeno |
|---|---|
| ubytování | ANO |
| jídlo | ANO |
| merch | ANO |
| mikiny | ANO |
| **trička** | **ne** (termín 1. 8.) |

Trička jako jediná pořád jdou — to je ta past z „na co si dát pozor", a nešlápne se do ní.

### 6.5: zamčená sekce pořád ukazuje, co účastník má

`MealWriter::heldMeals()` vrací po zamčení pořád 3 jídla. Sekce tedy není prázdná.

### 6.7: asymetrie účastník vs. admin funguje

Totéž jídlo, tentýž okamžik (po termínu), dvě cesty:

```
účastník (CartService::addItem):  ODMÍTNUT — Produkt "Oběd čtvrtek" není dostupný.
admin    (MealWriter::save):      PROŠEL   — jídel 3 → 4
```

Přesně to, kvůli čemu admin endpointy existují. Termín se v admin cestě záměrně
nekontroluje (`SetCustomerMealsProcessor`).

### Past, na kterou jsem narazil: `save()` není „přidej"

`MealWriter::save()` bere **celý výběr** a zdiffuje ho proti stávajícímu stavu. Poslat
jedno jídlo tedy znamená „nech mu jen tohle" — ze tří jídel zbude jedno. Vypadalo to jako
zamítnutý zápis, byl to ale korektní diff.

Pro test přidání se musí poslat **stávající + nové**; `heldMeals()` vrací plochý seznam
`variant_id`, ne pole polí.

### 6.6: ubytování se chová stejně jako jídlo

Doověřeno. Tentýž okamžik po termínu, uživatel 316:

```
účastník (CartService::addItem):        ODMÍTNUT — Produkt "Postel na "1L" koleji" není dostupný.
admin    (AccommodationWriter::save):   PROŠEL   — nocí 0 → 1
```

Komentář v kódu tedy nelže — termín se v admin cestě nekontroluje ani u ubytování.

**Dvě věci, které to komplikují a nejsou chyba:**

- **Všechny letošní varianty ubytování jsou vyprodané** (`remaining_quantity = 0` u všech
  16 aktivních). Admin na to má příznak `mayOverbook`, bez něj zápis neprojde. Test ho
  posílá — přesně jak by to udělal infopult.
- **Kandidát se musí brát z `findByTag()`**, ne vlastním SQL. `loadVariants()` rozhoduje
  o „je v nabídce" právě odtud, takže varianta vybraná jinak spadne na „Noc N není
  nabízeným ubytováním", i když v DB vypadá dostupně. Stejná past jako u jídla.

Legacy stranu (sloupec A) jsem u admin kroků neměřil — admin obrazovky jsou nová vrstva,
legacy protějšek nemá smysl porovnávat.


### 6.7: termín jídla se nekontroloval vůbec (N13) — opraveno

Pochází z 6.5, kde jídlo po termínu prošlo, a vypadalo to na správné chování `nabizet_do`.
Není: `JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE` nová vrstva nečetla, jídlo zastavoval jen
`nabizet_do` produktu. Mezi těmi dvěma daty je díra — podrobně v [N13](nalezy.md#n13).

Rozdíl proti ubytování je v tom, **co se zamyká**: u jídla i to, co už účastník má. Po
termínu jsou počty nahlášené v jídelně, takže zrušená porce se stejně uvaří a zaplatí.
Žádná výjimka `! $koupeno`, kterou má ubytování.

```
před termínem  → nákup PROŠEL,   zrušení PROŠLO
po termínu     → nákup ODMÍTNUT, zrušení ODMÍTNUTO
pult po termínu→ 12 klikatelných jídel (objednává dál)
```

**Zámek v UI sám o sobě nestačil a málem to prošlo jako hotové.** Matice byla zamčená
(0 klikatelných checkboxů), ale přímý `POST cart/items` se stejnou session jídlo koupil —
`locked` je jen nápověda pro prohlížeč. Drží to až kontrola v `CartService`, kam proto míří
i testy (`CartServiceJidloTest`, 4 testy).

**Past při ověřování v prohlížeči:** `bin-diff/cas.sh` posouvá „teď" v `SystemoveNastaveni`,
ale `web/moduly/prihlaska/prihlaska.php:109` se ptá `po(GC_BEZI_DO)` — tedy **konstanty**,
kterou posun neovlivní. Přihláška proto na každém posunutém datu hlásí „GameCon už proběhl"
a matice se vůbec nevykreslí. Naměřených „0 klikatelných jídel" tak nejdřív neznamenalo
zamčeno, ale prázdnou stránku. Účastnická strana je proto ověřená testy nad reálnou
databází; v prohlížeči je ověřený pult.
