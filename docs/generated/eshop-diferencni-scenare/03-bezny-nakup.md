# Scénář 3 — běžný účastník, celý nákup

**„Teď": 2026-06-01**

Hlavní průchod: objednat, změnit, zrušit — u každé kategorie zboží.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-06-01
```

## Co projít

| # | krok | A | B |
|---|---|---|---|
| 3.1 | ubytování: objednat 3 noci | | |
| 3.2 | ubytování: ubrat prostřední noc | | |
| 3.3 | ubytování: zrušit všechno | | |
| 3.4 | jídlo: objednat celou matici | | |
| 3.5 | jídlo: odebrat jedno | | |
| 3.6 | tričko: vybrat velikost, změnit ji | | |
| 3.7 | mikina | | |
| 3.8 | merch: víc kusů jedné položky | | |
| 3.9 | vstupné | | |
| 3.10 | spolubydlící: vyplnit, změnit, vymazat | | |

## Na co si dát pozor

- **3.10 — `null` u spolubydlícího** dřív mazal data. Ověřit, že prázdné pole znamená
  „nevyplněno", ne „smazat".
- **3.2** — ubrání prostřední noci vyrobí nenavazující noci; to je scénář 4, ale tady se
  ověří, že se to vůbec dá udělat a co to zapíše.
- **3.8** — u merche je množství; ověřit strop a to, že se nezdvojí.

## Kontrola dat

```bash
bin-diff/porovnej.sh <id_ucastnika>
```

## Zjištěno
