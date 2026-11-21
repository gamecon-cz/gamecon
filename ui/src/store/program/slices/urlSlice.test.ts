import { describe, expect, it, vi } from "vitest";
import { nastavFiltrJazyků } from "./urlSlice";

// Skutečný store stojí na zustandu, který importuje "react"; ten vitest bez
// preact presetu nenajde. Změnu stavu proto provede rovnou na objektu, jako immer.
const stav = vi.hoisted(() => ({ urlStav: {} as { filtrJazyk?: string[] } }));
vi.mock("..", () => ({
  useProgramStore: {
    setState: (změna: (s: typeof stav) => void) => { změna(stav); },
  },
}));

describe("nastavFiltrJazyků", () => {
  it("nechá vybranou část jazyků", () => {
    nastavFiltrJazyků(["en", "nezavisla"]);
    expect(stav.urlStav.filtrJazyk).toEqual(["en", "nezavisla"]);
  });

  it("všechny vybrané jazyky = žádný filtr", () => {
    nastavFiltrJazyků(["en", "nezavisla"]);
    nastavFiltrJazyků(["cs", "en", "nezavisla"]);
    expect(stav.urlStav.filtrJazyk).toBeUndefined();
  });

  it("žádný vybraný jazyk = žádný filtr", () => {
    nastavFiltrJazyků(["en"]);
    nastavFiltrJazyků([]);
    expect(stav.urlStav.filtrJazyk).toBeUndefined();
  });
});
