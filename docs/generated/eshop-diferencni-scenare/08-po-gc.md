# Scénář 8 — po skončení GameConu

**„Teď": 2026-08-02** (GC skončil 2026-07-26 23:59:59)

Data mají být zmrazená. Nic se už nesmí měnit — ani přes admin.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-08-02
```

## Co projít

| # | krok | očekávání | A | B |
|---|---|---|---|---|
| 8.1 | `/prihlaska` | všechno zamčené | ✓ | ✓ |
| 8.2 | co účastník vidí ze svých nákupů | pořád kompletní | ✓ | ✓ |
| 8.3 | admin: pokus objednat ubytování | ? porovnat | ✓ zapíše | ✓ zapíše |
| 8.4 | admin: pokus objednat jídlo | ? porovnat | ✓ zapíše | ✓ zapíše |
| 8.5 | finanční přehled účastníka | stejné částky | ✓ | ✓ |
| 8.6 | reporty (BFGR, ubytování, stravenky) | stejná čísla | ✓ | ✓ |

## Na co si dát pozor

- **Zmrazení slev.** Podle karty se slevy mají po GC zmrazit, ale v legacy se přepočítávaly
  pořád (jen v PHP, ne v DB). Jestli se nová vrstva chová jinak, je to **záměrná změna** —
  ale musí se vědět a napsat na kartu.
- **8.3/8.4** — jestli admin po GC ještě smí zapisovat, je produktová otázka, ne technická.
  Porovnat obě větve a rozdíl eskalovat, ne rozhodnout sám.
- **8.6** — reporty jsou to, co lidi po GC reálně čtou. Rozdíl v číslech je vždycky nález.

## Kontrola dat

```bash
bin-diff/porovnej.sh
```

## Zjištěno

Proklikáno 2026-09-18 na „teď" 2026-08-02, obě větve po `reset.sh` ze společného snapshotu.
Účastník Youda (65), operátor Gandalf.

### Účastník má zmrazeno, čísla sedí

`/prihlaska` na obou větvích: **0 klikatelných prvků**, tatáž hláška „Děkujeme za tvou účast
na GameConu!". Finanční přehled je **řádek po řádku shodný**:

| | |
|---|---|
| Předměty | 1470 |
| Ubytování | 1200 (3 × 400) |
| **Celková cena** | **2870** |
| Připsané platby | 3346 |
| **Stav financí** | **500** |

Tím padá i obava ze zmrazení slev — ceny po GC nikam neutekly.

### 8.3/8.4 — admin píše po GC na OBOU větvích

Produktová otázka z hlavičky scénáře má odpověď: **žádný rozdíl mezi větvemi není**, obě
zápis dovolí. Legacy má po GC 68 povolených prvků ve formuláři včetně
`shopUbytovaniDny[0..4]` a `cShopJidlo[...]`; zkušební zápis přes její formulář prošel a
propsal se do `shop_nakupy`. Nová větev dovolí totéž (18 nocí + 11 jídel klikatelných).

Jestli to tak **má** být, je pořád otázka na produkt — ale není to regrese přepisu.

### 8.6 — reporty shodné

| report | výsledek |
|---|---|
| `bfgr-report` | **bajt po bajtu shodný** |
| `stravenky` | **bajt po bajtu shodný** |
| `finance-report-ubytovani` (xlsx) | 12 částí, **11 shodných**; liší se jen `docProps/core.xml` |

Ten jediný rozdíl je čas generování (21:36:40 vs 21:36:42) — list s daty má 515 řádků a je
identický.

### Pasti na měření

- **Reporty nejdou volat přímo přes `/admin/scripts/zvlastni/reporty/…`.** Spadnou na
  `Call to a member function maPravo() on null` — čekají `$u` z admin bootstrapu. Správná
  cesta je `/admin/reporty/<jméno-bez-přípony>`.
- **`fetch().text()` zničí xlsx.** Binárka projde UTF-8 dekódováním a délka nesedí
  (120 070 místo 66 055 bajtů). Použít `context.request.get()` a `body()`.
- **Legacy formulář ubytování posílá celý výběr.** Zkušební zápis na jeden den přepsal
  všechny noci z „3L koleji" na „2L koleji" a přidal neděli. Stejná sémantika jako
  `AccommodationWriter`. Proto `reset.sh` mezi pokusy, ne jen mezi scénáři.
