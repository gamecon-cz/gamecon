# Scénář 2 — okamžik otevření registrace

**„Teď": 2026-05-13 20:30** (registrace otevřená pár minut)

První nákup ročníku. Zajímá nás přechod ze zavřeného do otevřeného stavu a první zápis do DB.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-05-13
```

## Co projít

| # | krok | A | B |
|---|---|---|---|
| 2.1 | `/prihlaska` — sekce jsou nově otevřené | | |
| 2.2 | objednat ubytování (2 navazující noci) | | |
| 2.3 | objednat jídlo | | |
| 2.4 | objednat tričko a merch | | |
| 2.5 | odeslat, ověřit zápis | | |
| 2.6 | vrátit se na `/prihlaska` — je vidět, co má koupené? | | |

## Na co si dát pozor

- **Zápis je tu poprvé** — tady se pozná, jestli se do `shop_nakupy` dostanou stejné řádky.
  Nová vrstva navíc plní `variant_id`, `order_id` a snapshoty; to je v pořádku, porovnává se
  sémantický průmět.
- **Cena se musí zmrazit** s objednávkou. Ověřit `cena_nakupni`, ne aktuální ceník.

## Kontrola dat

```bash
bin-diff/porovnej.sh <id_ucastnika>
```

## Zjištěno
