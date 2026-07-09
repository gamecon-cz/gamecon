import { describe, it, expect } from "vitest";
import { filtrujAktivity, FiltrAktivit, MapováníTagů } from "./aktivity";
import { Aktivita } from "../slices/programDataSlice";

// Regresní krytí pro filtr interních (nepublikovaných technických/brigádnických)
// aktivit v `filtrujAktivity`. Filtr už dvakrát regredoval (nejdřív mizely
// interní aktivity po přihlášení, pak zase org přišel o svou vedenou interní
// aktivitu), viz docs/generated/prihlasovani-na-skryte-technicke-aktivity.md.

const prázdnéMapováníTagů: MapováníTagů = { idDoKategorie: {} };

/**
 * Minimální aktivita – filtr čte jen interni/stavPrihlaseni/vedu (+ id).
 * `vlastnosti` je záměrně volné (Record), protože stavPrihlaseni má runtime
 * hodnotu `null` (viz StavPrihlaseni::frontendKod → buildUzivatelMap), kterou
 * ale typ Aktivita nepřipouští.
 */
const aktivita = (id: number, vlastnosti: Record<string, unknown>): Aktivita =>
  ({ id, ...vlastnosti } as unknown as Aktivita);

const idčka = (aktivity: Aktivita[], filtr: FiltrAktivit): number[] =>
  filtrujAktivity(aktivity, filtr, prázdnéMapováníTagů).map(a => a.id);

describe("filtrujAktivity – interní aktivity, filtr Interní vypnutý", () => {
  const aktivity: Aktivita[] = [
    aktivita(1, { interni: false, stavPrihlaseni: null, vedu: false }), // veřejná
    aktivita(2, { interni: true, stavPrihlaseni: "prihlasen", vedu: false }), // interní, přihlášen
    aktivita(3, { interni: true, stavPrihlaseni: null, vedu: true }), // interní, vede (nepřihlášen)
    aktivita(4, { interni: true, stavPrihlaseni: null, vedu: false }), // interní, bez vztahu
  ];

  it("veřejnou aktivitu ponechá vždy", () => {
    expect(idčka(aktivity, { filtrInterni: false })).toContain(1);
  });

  it("ponechá interní aktivitu, na kterou je účastník přihlášen", () => {
    expect(idčka(aktivity, { filtrInterni: false })).toContain(2);
  });

  it("ponechá interní aktivitu, kterou účastník vede (i bez přihlášení)", () => {
    // Regrese: dřív mizela, přestože můj program vede přes vedu.
    expect(idčka(aktivity, { filtrInterni: false })).toContain(3);
  });

  it("schová interní aktivitu, ke které účastník nemá vztah", () => {
    expect(idčka(aktivity, { filtrInterni: false })).not.toContain(4);
  });

  it("se zapnutým filtrem Interní ukáže i interní aktivitu bez vztahu", () => {
    expect(idčka(aktivity, { filtrInterni: true })).toEqual([1, 2, 3, 4]);
  });
});

describe("filtrujAktivity – muj program nekoliduje s filtrem interních", () => {
  const aktivity: Aktivita[] = [
    aktivita(2, { interni: true, stavPrihlaseni: "prihlasen", vedu: false }),
    aktivita(3, { interni: true, stavPrihlaseni: null, vedu: true }),
    aktivita(4, { interni: true, stavPrihlaseni: null, vedu: false }),
  ];

  it("v muj program zůstane přihlášená i vedená interní aktivita, bez vztahu ne", () => {
    const výsledek = idčka(aktivity, { výběr: { typ: "můj" }, filtrInterni: false });
    expect(výsledek).toContain(2);
    expect(výsledek).toContain(3);
    expect(výsledek).not.toContain(4);
  });
});
