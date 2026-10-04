# Scénář 5 — role a ceny v e-shopu

**„Teď": 2026-06-01** (registrace otevřená, do konce prodeje daleko)

Role mění **cenu i práva**, a to je nejhustší zdroj rozdílů: slevy se v nové vrstvě počítají
v `DiscountCalculator`, v legacy v `Cenik`. Stejný účastník musí na obou větvích zaplatit totéž.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-06-01
```

## Matice rolí

Role se přiřazují v adminu (`/admin/uzivatel`), na **obou** větvích stejně. Právo 1037
(„může si objednat jenom jednu noc") drží podle DB tyto role — proto se scénář 4 a 5 překrývají.

| role (kód) | co ověřit | pozn. |
|---|---|---|
| *bez role* | plná cena všeho | referenční řádek |
| `GC2026_VYPRAVEC` | sleva na ubytování/jídlo, právo 1037 | |
| `ORGANIZATOR_ZDARMA` | ubytování zdarma, práva 100+101 | **i admin přístup** |
| `GC2026_PARTNER` | partnerské ceny, právo 1037 | |
| `GC2026_BRIGADNIK` | právo 1037 | |
| `PUL_ORG_UBYTKO` | sleva jen na ubytování, práva 100+101 | půlorg |
| `PUL_ORG_TRICKO` | sleva jen na tričko, práva 100+101 | půlorg |
| `CESTNY_ORGANIZATOR` | právo 1037 | |
| `MINI_ORG` | právo 1037 | |

## Co projít u každé role

| # | krok | A | B |
|---|---|---|---|
| 5.1 | cena ubytování za noc | | |
| 5.2 | cena jídla (snídaně/oběd/večeře) | | |
| 5.3 | cena trička a mikiny | | |
| 5.4 | cena merche | | |
| 5.5 | vstupné | | |
| 5.6 | finanční přehled účastníka v adminu | | |

## Na co si dát pozor

- **Sleva se počítá z práv, ne z role** — role je jen nosič. Když se cena liší, ptát se
  „které právo to má řídit" a ověřit ho v `prava_role`, ne hádat podle názvu role.
- **Kombinace rolí.** Půlorg + vypravěč není teoretická: účastník může mít víc rolí a slevy
  se nesmí sčítat dvakrát. Otestovat aspoň jednu dvojici.
- **Zmrazení slev.** Podle karty se slevy mají zmrazit na konci GC; do té doby jsou dynamické.
  Tady se ověřuje jen dynamická část, zmrazení řeší scénář 8.
- Nová vrstva má `DiscountCalculator` s vlastními testy, ale ty ověřují **náš záměr**, ne
  shodu s legacy — proto tenhle scénář.

## Kontrola dat

```bash
bin-diff/porovnej.sh <id_uzivatele>     # cena_nakupni musí sedět na haléř
```

## Zjištěno

<!-- co se lišilo, s klasifikací -->
