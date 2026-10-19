# Scénář 1 — den před otevřením e-shopu

**„Teď": 2026-05-12** (registrace se otevírá 2026-05-13 20:26)

Přihlášený účastník přijde na `/prihlaska` a e-shop je ještě zavřený. Musí se dozvědět
**kdy se má vrátit** — ne jen že to nejde.

## Příprava

```bash
bin-diff/reset.sh
bin-diff/cas.sh 2026-05-12
```

## Testovaná osoba

Běžný účastník bez rolí organizátora, přihlášený na letošní GC.
Heslo `admin`, login viz `bin-diff/porovnej.sh` nebo admin.

## Co projít

| # | krok | A — legacy :18040 | B — nový :18020 |
|---|---|---|---|
| 1.1 | `/prihlaska` jako přihlášený účastník | | |
| 1.2 | je vidět **datum otevření** registrace? | | |
| 1.3 | jde odeslat objednávka (ubytování, jídlo, merch)? musí **ne** | | |
| 1.4 | co přesně je v sekcích vidět — ceník, nebo nic? | | |
| 1.5 | `/prihlaska` jako **nepřihlášený** | | |

## Na co si dát pozor

- **Tohle je hlavní otázka scénáře:** vidí uživatel termín, kdy se má vrátit? Pokud nová
  verze jen skryje formulář a legacy ukazovala „registrace začíná 13. 5. ve 20:26",
  je to **regrese v informovanosti**, i když se ani na jedné straně nedá nic koupit.
- Nová vrstva má vlastní datové brány (`saleClosed` v DTO ubytování); legacy to řešila
  v šabloně. Rozhodnutí „nedá se koupit" musí padnout **stejně**, ale text kolem se lišit může.
- Nezapomenout na **nepřihlášeného** návštěvníka — ten na `/prihlaska` uvidí něco jiného.

## Kontrola dat

Nic se nesmí zapsat:

```bash
bin-diff/porovnej.sh        # musí hlásit shodu v obou sekcích
```

## Zjištěno

<!-- co se lišilo, s klasifikací: záměrné / chyba / nezjištěno -->
