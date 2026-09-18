# Scénář 4 — hraniční případy ubytování

**„Teď": 2026-06-01**

Ubytování má nejvíc pravidel a v přepisu se ho dotklo nejvíc změn.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-06-01
```

## Co projít

| # | krok | očekávání | A | B |
|---|---|---|---|---|
| 4.1 | jedna noc **bez** práva 1037 | odmítnout (minimum 2 noci) | | |
| 4.2 | jedna noc **s** právem 1037 | povolit | | |
| 4.3 | nenavazující noci (čt + so, bez pá) | ? porovnat chování | | |
| 4.4 | plná noc — objednat | odmítnout, srozumitelně | | |
| 4.5 | hotelová noc se snídaní | snídaně se nesmí dát objednat zvlášť | | |
| 4.6 | spacák | vlastní pravidla, viz `AccommodationRules` | | |
| 4.7 | „nechci ubytování" | zapsat `nechce_ubytovani` | | |
| 4.8 | ubytování z **minulého ročníku** | nesmí jít objednat | | |

## Na co si dát pozor

- **4.1/4.2** — `minimumNights` je v novém DTO `$muzeJednuNoc ? 1 : 2`. Do teď to nekrylo
  nic než smazaný legacy test; teď to má vlastní test, ale GUI chování se musí ověřit.
- **4.4** — kapacitu nově hlídá server. Dřív o tom rozhodovalo jen zobrazení tlačítka.
- **4.5** — snídaně krytá hotelem se ruší **po** zápisu (viz scénář 7.5).
- **4.8** — `nabizet_do` se u ubytování **záměrně ignoruje**; noc zamyká jen stav
  POZASTAVENY. Ověřeno testem `ProductRepositoryNabizetDoTest`, ale ověřit i v GUI.

## Kontrola dat

```bash
bin-diff/porovnej.sh <id_ucastnika>
```

## Zjištěno
