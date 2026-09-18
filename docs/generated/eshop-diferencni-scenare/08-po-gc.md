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
| 8.1 | `/prihlaska` | všechno zamčené | | |
| 8.2 | co účastník vidí ze svých nákupů | pořád kompletní | | |
| 8.3 | admin: pokus objednat ubytování | ? porovnat | | |
| 8.4 | admin: pokus objednat jídlo | ? porovnat | | |
| 8.5 | finanční přehled účastníka | stejné částky | | |
| 8.6 | reporty (BFGR, ubytování, stravenky) | stejná čísla | | |

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
