# Ukládání kliknutím v Preact obrazovkách

TL;DR: obrazovka, kde každé zaškrtnutí uloží přes API samo, říká uživateli, v jakém stavu jeho změna je, jedním způsobem: `useSaveState()` drží stav a `<SaveStatus />` ho vypíše na jeden řádek. Dnes ho má jen mřížka ubytování; jídlo, merch a vstupné ukazují jen zašednutí dlaždice, dokud se ukládá.

## Vstupní body v kódu

- `ui/src/components/SaveStatus/useSaveState.ts` — stav a časovač potvrzení
- `ui/src/components/SaveStatus/SaveStatus.tsx` — řádek se stavem (`role="status"`, v stránce je vždy, aby rozložení neposkakovalo)
- `ui/src/pages/ubytovani/UbytovaniMřížka.tsx` — jediné použití, vzor
- `ui/src/pages/ubytovani/vyberNoci.ts` — pravidla výběru, které mřížka ověří dřív, než cokoli pošle

## Stavy

| stav | text | kdy |
|---|---|---|
| `idle` | (nic) | nic se neděje, nebo potvrzení dozní |
| `pending` | „● Neuloženo — důvod" | změna čeká, až půjde odeslat (např. „Vyber ještě 1 navazující noc ubytování.") |
| `saving` | „Ukládám…" | požadavek běží |
| `saved` | „✓ Uloženo" | server změnu přijal; po 3 s zmizí (`SAVED_VISIBLE_MS`) |
| `failed` | „Uložení se nepodařilo" | server odmítl; důvod stojí v chybové hlášce obrazovky a mřížka se vrátí k uloženému stavu |

Nová zpráva zruší doznívající potvrzení, takže staré „Uloženo" nepřemaže čerstvé „Ukládám…".

## Jak to použít v další obrazovce

1. `const stav = useSaveState();` a `<SaveStatus state={stav.state} />` nad nebo pod mřížkou.
2. Před odesláním `stav.markSaving()`, po odpovědi `stav.markSaved()`, při chybě `stav.markFailed()`.
3. Změna, která se zatím poslat nedá, jde do `stav.markPending(důvod)`, a návrat k uloženému stavu do `stav.markIdle()`.
4. Dlaždici s neuloženou volbou označ třídou čárkovaného orámování (v mřížce ubytování `ubytovani-mrizka--neulozeno`), ať je vidět, co přesně čeká.

## Gotchas

- Čekající výběr je jen v prohlížeči. Zavřená stránka ho zahodí; proto řádek říká „Neuloženo" a nechává dlaždici označenou.
- Odpověď serveru je pravda: po odmítnutí se mřížka znovu načte, aby neukazovala volbu, kterou server nemá.
- Chování mřížky ubytování a proč se výběr drží, dokud není platný: [eshop-zamerne-rozdily-proti-main](eshop-zamerne-rozdily-proti-main.md), řádek Z28.
