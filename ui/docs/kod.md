
## Složky

Veškerý kód se nachází ve složce *src*. Následně pak pomocný kód pro spouštění lokálního serveru je index.html a ve složce *testing*

- `src/`
  - `api/`: obsahuje rozhraní se serverem gc. Veškerá komunikace by měla jít tudy (všechny **fetch** volání budou v této složce)
  - `pages/`: obsahuje jednotlivé komponenty které jsou pak vložené do stránek
    - `index.tsx` rozhoduje jestli a které stránky se mají vykreslit
    - `<stránka>/`
      - `index.tsx` exportuje komponentu která se má vykreslit
      - `components/` jednotlivé části stránky
  - `store/`
    - `<název>/` obsahuje kompletní store pro konkrétní stránku/y
      - `slices/` slices jsou jednotlié části store které dohromady tvoří store
  - `utils/` univerzální pomocné funkce
  - `env.ts` předané statické hodnoty z GC

## Struktura podle funkcionalit

O vykreslování se stará knihovna Preact. Jedná se o knihovnu která napomáhá deklarativně vykreslovat elementy do DOM a tvořit tak vysoce dynamické prvky stránky. Preact sám o sobě je docela komplikovaný na zprávu dat a jejich předávání napříč jednotlivími komponentami. Tento problém se řeší globálním stavem se stavovou knihovnou Zustand. Zustand umožňuje přístup k libovolným datům z libovolné části aplikace a tak není potřeba skoro žádne data předávat z komponenty na komponentu. Zároveň taky Zustand umožňuje jednodušší debuging pomocí chrome pluginu.

Preact umožňuje přidávat k jednotlivím komponentám less soubory takže je pak stačí jen na začátku tsx dát do importu a při buildu budou součástí zbuildovaného css.

Úpravy URL se dělají přes část stavu v zustand

Komunikace je zrprostředkována dvěmi způsoby
  1) Dynamické data a akce pomocí `fetch` funkce (pouze v `api/` složce)
  2) Neměnné data které jsou dostupné před spuštěním kódu stránky jako jsou Ročník, Cesta k api atd. je v `src/env.ts`

## Pravidla pro psaní kódu:

  - _testing nemusí vždy pravidla dodržovat, jedná se o obejití systému aby se nachystaly demo scénáře._
  - přímé volání funkce `fetch` používat pouze v `api/`
  - hlavní store používat (např. _useProgramStore_) pouze ve `store/`
  - upravovat url pouze přes stav v zustand


## Preact
TODO: 
Preact umožňuje vytvářet html pomocí komponent. Komponenty můžou být interaktivní a obsahovat kód a styly. Ve výsledku je kód dost podobný PHP. Preact je knihovna velice podobná Reactu takže hodně React tutoriálů je platných i pro Preact.




## Zustand

TODO: 
```ts
import { MyStateCreator } from ".";
import { sleep } from "../../utils";

export type ExmampleSlice = {
  example: {
    value: number,
    setValue: (value: number) => Promise<void>,
    increaseValue: () => void,
  }
}

export const createExampleSlice: MyStateCreator<ExmampleSlice> = (set, get) => ({
  example: {
    value: 0,
    async setValue(value) {
      await sleep(200);
      // Provedu změnu na imutabilním objektu kterou propíše immer
      set(s => { s.example.value = value; });
    },
    increaseValue() {
      const newValue = get().example.value+1;
      set(s=>{s.example.value = newValue;});
      // Totožné s set(s=>{s.example.value++;}) ale umožňuje větší kontrolu nad operacemi pomocí použítí get()
    },
  },
});
```


### Všeobecné doporučení

TODO: guidlines nereferencovat nikde useProgramStore přímo místo toho využít selektory.ts a pro změny přímo změny ve slices

