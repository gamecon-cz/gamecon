import { describe, it, expect, vi, beforeEach } from "vitest";
import { otevřiUpozorněníNaVěk, zavřiUpozorněníNaVěk } from "./všeobecnéSlice";

// Upozornění před přihlášením na aktivitu pro starší (UpozorneniNaVekModal): přihlásí až potvrzení,
// zavření přihlášení zahodí. Stejné funkce volá i odkaz „přihlásit" na stránkách linií (preactMost).

// Skutečný store (zustand) potřebuje v testech React, tady stačí jeho setState s immerem a getState.
const store = vi.hoisted(() => {
  const stav: { všeobecné: { modalUpozorněníNaVěk?: { nadpis: string, text: string } } } = {
    všeobecné: {},
  };
  return {
    stav,
    useProgramStore: {
      setState: (změna: (s: typeof stav) => void) => { změna(stav); },
      getState: () => stav,
    },
  };
});
vi.mock("..", () => ({ useProgramStore: store.useProgramStore }));

const modal = () => store.stav.všeobecné.modalUpozorněníNaVěk;

// text skládá PHP (Aktivita::getUpozorneniNaVek()), tady na něm nezáleží
const upozornění = (nazevAktivity: string, minimalniVek: number) => ({
  nadpis: `Aktivita ${nazevAktivity} je od ${minimalniVek} let`,
  text: `V den aktivity ti ještě nebude ${minimalniVek}.`,
});

describe("upozornění na věk", () => {
  beforeEach(() => {
    zavřiUpozorněníNaVěk(false);
  });

  it("otevře modal s textem z PHP a nepřihlásí", () => {
    const přihlaš = vi.fn();

    otevřiUpozorněníNaVěk(upozornění("Larp pro starší", 18), přihlaš);

    expect(modal()).toEqual(upozornění("Larp pro starší", 18));
    expect(přihlaš).not.toHaveBeenCalled();
  });

  it("potvrzení zavře modal a přihlásí právě jednou", () => {
    const přihlaš = vi.fn();
    otevřiUpozorněníNaVěk(upozornění("Larp pro starší", 18), přihlaš);

    zavřiUpozorněníNaVěk(true);
    zavřiUpozorněníNaVěk(true);

    expect(modal()).toBeUndefined();
    expect(přihlaš).toHaveBeenCalledTimes(1);
  });

  it("zrušení zavře modal bez přihlášení a pozdější potvrzení ho už nevyvolá", () => {
    const přihlaš = vi.fn();
    otevřiUpozorněníNaVěk(upozornění("Larp pro starší", 18), přihlaš);

    zavřiUpozorněníNaVěk(false);
    zavřiUpozorněníNaVěk(true);

    expect(modal()).toBeUndefined();
    expect(přihlaš).not.toHaveBeenCalled();
  });

  it("nové upozornění nahradí přihlášení z předchozího", () => {
    const první = vi.fn();
    const druhé = vi.fn();
    otevřiUpozorněníNaVěk(upozornění("První larp", 15), první);
    otevřiUpozorněníNaVěk(upozornění("Druhý larp", 18), druhé);

    zavřiUpozorněníNaVěk(true);

    expect(první).not.toHaveBeenCalled();
    expect(druhé).toHaveBeenCalledTimes(1);
  });
});
