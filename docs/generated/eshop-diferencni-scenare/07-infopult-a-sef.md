# Scénář 7 — infopult a šéf infopultu

**„Teď": 2026-07-24** (GC běží, prodej ubytování i jídla po termínu)

Nejdůležitější scénář pro tenhle přepis: **obě admin obrazovky jsou nově Preact** a nikdo
je ještě neviděl v prohlížeči. Objednává se tu **za účastníka**, po termínu prodeje, a
odsud chodí fyzické stravenky.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-07-24
```

## Obsluha

Testuje se **ze tří stran** — právo rozhoduje, ne role:

| kdo | práva | očekávání |
|---|---|---|
| účastník bez práv | — | do adminu se vůbec nedostane |
| obsluha pultu | 100 nebo 101 (`GC2026_INFOPULT`, `ORGANIZATOR_ZDARMA`, `PUL_ORG_*`) | smí objednávat za účastníka |
| šéf infopultu | 100/101 + právo na přeplnění | smí i přeplnit plnou noc |

**Pozor:** `ROLE_ADMIN` v nové vrstvě tohle neřeší — `User::getRoles()` porovnává kódy rolí
proti pevnému seznamu, kdežto reálné kódy jsou po ročnících (`gc2026_infopult`). Kontrola
proto sedí uvnitř v `CustomerDeskRights` na právech 100/101. Ověřit, že to platí i v GUI.

## Co projít

### Uživatel (`/admin/uzivatel`)

| # | krok | A | B |
|---|---|---|---|
| 7.1 | najít účastníka, otevřít jeho ubytování | ✓ | ✓ |
| 7.2 | objednat noc **po termínu prodeje** — musí jít | n/a | ✓ (prohlížeč) |
| 7.3 | zrušit noc | n/a | ✓ (prohlížeč) |
| 7.4 | objednat jídlo (matice) | n/a | ✓ (prohlížeč) |
| 7.5 | **snídaně krytá hotelovou nocí** — zaškrtnout | n/a | ✓ (kód) |
| 7.6 | dvě rychlá kliknutí na různá jídla | n/a | ✓ (prohlížeč) |
| 7.7 | přiřadit spolubydlícího | n/a | ✓ (prohlížeč) |

### Infopult (`/admin/infopult`)

| # | krok | A | B |
|---|---|---|---|
| 7.8 | totéž co 7.1–7.7 na obrazovce infopultu | n/a | ✓ (prohlížeč) |
| 7.9 | prodej na pultu (KFC mřížka) | n/a | ✓ (načte se) |

### Šéf infopultu

| # | krok | A | B |
|---|---|---|---|
| 7.10 | přeplnit **plnou** noc | n/a | ✓ |
| 7.11 | totéž jako běžná obsluha — musí **odmítnout** | n/a | ✓ |

## Na co si dát pozor

- **7.5 je ta nejcennější kontrola.** Nová vrstva snídani zapíše a `BreakfastCanceller` ji
  vzápětí zruší; legacy ji z requestu odfiltrovala. Výsledek nákupu má být stejný, ale nová
  verze si ji **navíc zapamatuje** a nabídne zpátky, až krycí noc zmizí. Ověřit, že
  zaškrtávátko po uložení odpovídá skutečnosti — obsluha podle něj vydává stravenku.
- **7.6** — dřív se dvě rychlá kliknutí přepisovala. Teď se ukládání řetězí; ověřit, že se
  propíšou obě.
- **7.10/7.11** — přeplnění kapacity je nově hlídané na serveru (dřív o tom rozhodovalo jen
  to, komu se zobrazí tlačítko). Ověřit, že běžná obsluha dostane odmítnutí, ne 500.
- **Chyba se musí ukázat jako hláška, ne jako 500.** Že je vyprodáno nebo že na přeplnění
  není právo, patří do GUI srozumitelně.

## Kontrola dat

Po každé objednávce:

```bash
bin-diff/porovnej.sh <id_ucastnika>
```

A v adminu reporty: přehled ubytování, stravenky, finanční přehled účastníka.

## Zjištěno (2026-09-18)

Ověřeno **v kódu, ne v prohlížeči** — GUI část (7.1–7.4, 7.6–7.9) pořád čeká na proklikání,
viz níže.

### 7.10 / 7.11 — přeplnění funguje a hlásí srozumitelně

Plná noc, tentýž zápis, jen jiný operátor:

```
běžná obsluha | mayOverbook=false | ODMÍTNUTO: Ubytování „Postel na "1L" koleji" na středa
                                     je plné; přeplnit ho smí jen šéf infopultu.
šéf infopultu | mayOverbook=true  | PROŠLO
```

Splněno i to, co scénář žádal zvlášť: **je to hláška, ne 500**, a rozlišuje „je plno" od
„na přeplnění nemáš právo", takže obsluha nejde hledat postel, která existuje.

### 7.5 — snídaně krytá hotelovou nocí: celý cyklus sedí

```
koupit snídani         → drží 1 jídel
přidat hotelovou noc   → drží 0 jídel, k obnově 0   (zrušena, protože ji noc kryje)
zrušit hotelovou noc   → drží 0 jídel, k obnově 1   (nabídne se zpět)
```

Přesně to chování, které legacy nemá — ta snídani z requestu jen odfiltrovala. Nová vrstva
si ji pamatuje a po zmizení krycí noci ji vrátí do nabídky.

### Kapacitu hlídá `kusu_vyrobeno`, ne `remaining_quantity`

Past při testování: `product_variant.remaining_quantity` vypadá jako zásoba, ale
`AccommodationWriter::addNight()` porovnává **`shop_predmety.kusu_vyrobeno`** proti počtu
řádků v `shop_nakupy`. Vynulovat `remaining_quantity` tedy noc neudělá plnou a test
přeplnění tiše projde oběma stranami. Plnou noc udělá `UPDATE shop_predmety SET
kusu_vyrobeno = 0 WHERE kod_predmetu = <kód varianty>`.

### `SEF_INFOPULTU` sám o sobě do adminu nepustí

Role 24 nese jen práva **111 a 1038** — ani 100, ani 101. `CustomerDeskRights` přitom
vyžaduje 100 nebo 101, takže držitel *jen* téhle role by dostal „Na objednávání za
účastníky nemáš právo" dřív, než by se vůbec došlo na přeplnění.

**Není to chyba v produkci:** všichni reální držitelé mají 30+ dalších rolí, odkud 100/101
dostanou. Je to add-on role, ne samostatná. Důsledek je testovací: **7.11 nejde ověřit
přidělením téhle jediné role**, musí se kombinovat s `GC2026_INFOPULT` nebo
`ORGANIZATOR_ZDARMA`.

Pozor i na nesourodost: do adminu pouští **právo**, přeplnění povoluje **role**
(`jeSefInfopultu()` → `maRoli`). Scénář varuje „právo rozhoduje, ne role" — u přeplnění to
neplatí.

## Průchod prohlížečem (2026-09-18)

Poprvé pouštěno přes Playwright, viz `bin-diff/playwright/README.md`.

### 7.8 — obrazovka infopultu běží

Obě Preact mřížky se vykreslí uvnitř legacy adminu s reálnými daty: ubytování 33
checkboxů, jídlo 12, žádná chyba v konzoli.

### 7.6 — dvě rychlá kliknutí se obě propíšou

Tohle je ta regrese, před kterou scénář varoval, a **je opravená**. Odchycené požadavky:

```
→ POST {"customerId":5246,"variantIds":[1113]}
→ POST {"customerId":5246,"variantIds":[1113,1115]}
```

Podstatný je **obsah druhého požadavku**, ne počet požadavků: nese `[1113, 1115]`, tedy
stávající výběr plus nové jídlo. Kdyby poslal jen `[1115]`, znamenalo by to „nech mu jen
tohle" a první snídaně by zmizela — `save()` totiž bere celý výběr a zdiffuje ho.

V databázi po kliknutích:

| variant_id | název | řádků |
|---|---|---|
| 1113 | Snídaně čtvrtek | 1 |
| 1115 | Snídaně sobota | 1 |

Dvě jídla, každé jednou — `1113` se poslalo dvakrát a nevznikl z toho duplikát.

> **Pozor na `201` jako důkaz.** `POST` na kolekci vrací `201 Created` vždycky, je to jen
> HTTP status. Neříká nic o tom, kolik položek se objednalo, ani že se objednalo správně —
> dva `201` znamenají dva požadavky, nic víc. Důkazem je až tělo požadavku a stav v DB.

### 7.9 — KFC mřížka se načte

`GET 200 kfc/grids` a `GET 200 kfc/products`. Samotný prodej na pultu neproklikán.

### Zbývá proklikat

7.1–7.4 (najít účastníka, objednat/zrušit noc, matice jídel) a 7.7 (spolubydlící).

---

## 7.1–7.4 a 7.7 proklikané (2026-09-18)

„Teď" 2026-07-22: prodej ubytování i jídla **po termínu**, GC ještě neběží (`gcBezi=false`),
takže přihláška není zamčená přes `GC_BEZI_DO`. Přesně to okno, ve kterém má pult prodávat
a účastník ne. Účastník Youda (65) — letos přihlášený, bez orgovských rolí.

| krok | výsledek |
|---|---|
| 7.1 | obě větve zobrazí jeho stránku, jméno i ubytování |
| 7.2 | 3 noci → **4**, po termínu, bez chyby |
| 7.3 | 4 → **3**, zpět na výchozí |
| 7.4 | 0 jídel → **1**, 11 buněk klikatelných |
| 7.7 | pole „Na pokoji s:" jde vyplnit a hodnota přežije reload |

Databáze po 7.2+7.3 souhlasí s GUI: **3 noci**, tedy přidání i zrušení opravdu dojelo do
`shop_nakupy`, ne jen do obrazovky. Žádné HTTP ≥ 400.

### Dvě pasti na měření

- **Počítat checkboxy napříč větvemi nejde.** Nová větev jich má na stránce 45, legacy 15 —
  ne proto, že by něco chybělo, ale protože nová kreslí mřížku noc×pokoj, kdežto legacy
  řádek na typ pokoje. Legacy navíc zobrazuje 4 zaškrtnuté boxy, které **nejsou v žádném
  formuláři a nemají `name`** — jsou jen dekorace. Porovnávat se musí to, co má zákazník
  v `shop_nakupy`, ne DOM.
- **Selektor podle `name` spolubydlícího nenajde.** Je to `<input type="text">` bez `name`
  uvnitř `.ubytovani-mrizka--spolubydlici`; „pole tam není" byl artefakt selektoru, ne nález.
