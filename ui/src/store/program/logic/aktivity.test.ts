import { describe, it, expect } from "vitest";
import { aktivitaStatusZAktivity, filtrujAktivity, FiltrAktivit, MapováníTagů } from "./aktivity";
import { Aktivita } from "../slices/programDataSlice";
import { ApiObsazenost } from "../../../api/program";
import { Pohlavi } from "../../../api/přihlášenýUživatel";

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

describe("filtrujAktivity – jazyk aktivity", () => {
  const aktivity: Aktivita[] = [
    aktivita(1, { jazyk: "cs" }),
    aktivita(2, { jazyk: "en" }),
    aktivita(3, { jazyk: "cs-en" }),
    aktivita(4, { jazyk: "nezavisla" }),
    aktivita(5, {}), // data z doby před jazykem aktivity
  ];

  it("bez filtru jazyka nechá všechny aktivity", () => {
    expect(idčka(aktivity, { filtrInterni: true })).toEqual([1, 2, 3, 4, 5]);
  });

  it("angličtině vyhoví i aktivita vedená česky i anglicky", () => {
    expect(idčka(aktivity, { filtrInterni: true, filtrJazyk: ["en"] })).toEqual([2, 3]);
  });

  it("češtině vyhoví i aktivita bez uvedeného jazyka", () => {
    expect(idčka(aktivity, { filtrInterni: true, filtrJazyk: ["cs"] })).toEqual([1, 3, 5]);
  });

  it("víc vybraných jazyků se sčítá", () => {
    expect(idčka(aktivity, { filtrInterni: true, filtrJazyk: ["en", "nezavisla"] })).toEqual([2, 3, 4]);
  });
});

describe("neúčast (nedorazil, pozdě se odhlásil)", () => {
  const aktivity: Aktivita[] = [
    aktivita(1, { interni: false, stavPrihlaseni: "prihlasen", vedu: false }),
    aktivita(2, { interni: false, stavPrihlaseni: "prihlasenADorazil", vedu: false }),
    aktivita(3, { interni: false, stavPrihlaseni: "prihlasenAleNedorazil", vedu: false }),
    aktivita(4, { interni: false, stavPrihlaseni: "pozdeZrusil", vedu: false }),
    aktivita(5, { interni: false, stavPrihlaseni: null, vedu: false }),
  ];

  it("má vlastní stav, ne přihlášen", () => {
    expect(aktivity.map(a => aktivitaStatusZAktivity(a))).toEqual(
      ["prihlasen", "prihlasen", "neucast", "neucast", "volno"],
    );
  });

  it("v můj program zůstane", () => {
    expect(idčka(aktivity, { výběr: { typ: "můj" }, filtrInterni: true })).toEqual([1, 2, 3, 4]);
  });

  it("filtr legendy ji odliší od přihlášení", () => {
    expect(idčka(aktivity, { filtrStavAktivit: ["prihlasen"], filtrInterni: true })).toEqual([1, 2]);
    expect(idčka(aktivity, { filtrStavAktivit: ["neucast"], filtrInterni: true })).toEqual([3, 4]);
  });
});

describe("filtr Volná místa", () => {
  const obsazenost = (vlastnosti: Partial<ApiObsazenost>): ApiObsazenost =>
    ({ m: 0, f: 0, km: 0, kf: 0, ku: 0, ...vlastnosti });

  const veřejná = (id: number, vlastnosti: Record<string, unknown>): Aktivita =>
    aktivita(id, { interni: false, stavPrihlaseni: null, vedu: false, ...vlastnosti });

  const aktivity: Aktivita[] = [
    veřejná(1, { obsazenost: obsazenost({ ku: 4, m: 2 }), prihlasovatelna: true }), // volno
    veřejná(2, { obsazenost: obsazenost({ ku: 4, m: 3, f: 2 }), prihlasovatelna: true }), // přeplněná (5/4)
    // po GC: nepřihlašovatelná, proběhlá, ale nezaplněná, právě ta má zůstat
    veřejná(3, { obsazenost: obsazenost({ ku: 6, f: 2 }), prihlasovatelna: false, probehnuta: true }),
    veřejná(4, { obsazenost: obsazenost({ km: 1, kf: 1, f: 1 }), prihlasovatelna: true }), // zbývá jen mužské místo
    veřejná(5, { obsazenost: obsazenost({ ku: 25, m: 17, f: 4, kt: 5, t: 5 }), prihlasovatelna: true }), // plná týmovka
    veřejná(6, { obsazenost: obsazenost({}), prihlasovatelna: true }), // bez omezení
    // po GC, zbyla jen ženská místa: nezaplnila se, i když se dívá muž
    veřejná(7, { obsazenost: obsazenost({ km: 3, kf: 3, m: 3, f: 1 }), prihlasovatelna: false, probehnuta: true }),
  ];

  const volné = (pohlavi?: Pohlavi, filtr: FiltrAktivit = {}): number[] =>
    filtrujAktivity(aktivity, { filtrVolné: true, filtrInterni: true, ...filtr }, prázdnéMapováníTagů, pohlavi)
      .map(a => a.id);

  it("nechá aktivity s místem i mimo registraci a po GC", () => {
    expect(volné("m")).toEqual([1, 3, 4, 6, 7]);
  });

  it("kam se dá přihlásit, tam přihlášenému počítá jen místa pro jeho pohlaví, nepřihlášenému jakékoli", () => {
    expect(volné("f")).toEqual([1, 3, 6, 7]);
    expect(volné(undefined)).toEqual([1, 3, 4, 6, 7]);
  });

  it("co se nezaplnilo, ukáže bez ohledu na pohlaví toho, kdo se dívá", () => {
    for (const pohlavi of ["m", "f", undefined] as const) {
      expect(volné(pohlavi)).toContain(7);
    }
  });

  it("s Přihlašovatelné jen to, kam se dá jít", () => {
    expect(volné("m", { filtrPřihlašovatelné: true })).toEqual([1, 4, 6]);
  });

  it("filtr legendy Plno počítá s pohlavím přihlášeného jako obarvení", () => {
    expect(idčka(aktivity, { filtrStavAktivit: ["plno"], filtrInterni: true })).toEqual([2, 4, 5, 7]);
    expect(
      filtrujAktivity(aktivity, { filtrStavAktivit: ["plno"], filtrInterni: true }, prázdnéMapováníTagů, "m")
        .map(a => a.id),
    ).toEqual([2, 5, 7]);
  });
});
