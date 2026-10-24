import { describe, it, expect } from "vitest";
import {
  připravTabulkuAktivit,
  rozložŘádekDoSloupců,
  SeskupováníAktivit,
  SLOUPCŮ_ZA_HODINU,
  PředpřivenáTabulkaAktivit,
  PředpřivenáTabulkaAktivitHierarchie,
} from "./seskupování";
import { GAMECON_KONSTANTY } from "../../../../env";
import { Aktivita } from "../../../../store/program/slices/programDataSlice";

// Regrese: v zobrazení „po místnostech" se aktivita zabírající víc sálů
// zařazovala jen do své hlavní lokace (mistnosti[0]); v ostatních místnostech
// chyběla, takže vypadaly ve svém čase volně. Aktivita teď musí být v každé
// své místnosti.

const lokace = (id: number, nazev: string, poradi: number) =>
  ({ id, nazev, poradi });

const aktivita = (id: number, mistnosti: ReturnType<typeof lokace>[]): Aktivita => {
  const od = GAMECON_KONSTANTY.PROGRAM_OD;
  return { id, linie: "x", cas: { od, do: od + 3_600_000 }, mistnosti } as unknown as Aktivita;
};

describe("připravTabulkuAktivit – seskupení po místnostech", () => {
  const salA = lokace(1, "Sál A", 1);
  const salB = lokace(2, "Sál B", 2);

  const výsledek = připravTabulkuAktivit(
    [
      aktivita(10, [salA, salB]), // ve dvou sálech
      aktivita(11, [salB]),       // jen v jednom
    ],
    SeskupováníAktivit.mistnost,
  ) as PředpřivenáTabulkaAktivitHierarchie;

  // Napříč všemi dny posbírá id aktivit zařazených do dané místnosti.
  const idVMístnosti = (nazev: string): number[] =>
    Object.values(výsledek).flatMap((den) =>
      (den[nazev] ?? []).map((buňka) => buňka.aktivita.id),
    );

  it("aktivitu ve více místnostech zobrazí ve všech", () => {
    expect(idVMístnosti("Sál A")).toContain(10);
    expect(idVMístnosti("Sál B")).toContain(10);
  });

  it("aktivitu v jedné místnosti nechá jen tam", () => {
    expect(idVMístnosti("Sál B")).toContain(11);
    expect(idVMístnosti("Sál A")).not.toContain(11);
  });

  it("řadí místnosti dle poradi, ne dle pořadí polí z API", () => {
    // První (a jediná) aktivita má místnosti v API v opačném pořadí, než je
    // jejich poradi. Skupiny přesto musí vyjít Sál A (poradi 1) → Sál B (2).
    const výsledekOpačně = připravTabulkuAktivit(
      [aktivita(20, [salB, salA])],
      SeskupováníAktivit.mistnost,
    ) as PředpřivenáTabulkaAktivitHierarchie;

    const denSAktivitou = Object.values(výsledekOpačně).find(
      (den) => Object.keys(den).length > 0,
    );
    expect(Object.keys(denSAktivitou ?? {})).toEqual(["Sál A", "Sál B"]);
  });
});

// Aktivity začínají a končí po čtvrthodinách. Tabulka má proto čtyři sloupce
// na hodinu a buňky musí ležet přesně pod hodinami v hlavičce, i když aktivita
// začíná nebo končí uprostřed hodiny.
describe("rozložŘádekDoSloupců – sloupce po čtvrthodinách", () => {
  const HODINA = 3_600_000;
  const MINUTA = 60_000;
  const ZAČÁTEK = GAMECON_KONSTANTY.PROGRAM_ZACATEK;
  const SLOUPCŮ_PROGRAMU = 22 * SLOUPCŮ_ZA_HODINU;

  // PROGRAM_OD je pražská půlnoc prvního dne programu
  const čas = (hodin: number, minut = 0) =>
    GAMECON_KONSTANTY.PROGRAM_OD + hodin * HODINA + minut * MINUTA;

  const aktivitaVČase = (id: number, od: number, časDo: number): Aktivita =>
    ({ id, linie: "x", cas: { od, do: časDo } }) as unknown as Aktivita;

  const rozlož = (aktivity: Aktivita[]) => {
    const { buňky, zbývá } = rozložŘádekDoSloupců(aktivity, SLOUPCŮ_PROGRAMU);
    return {
      buňky: buňky.map(({ aktivita, odsazení, šířka }) => ({ id: aktivita.id, odsazení, šířka })),
      zbývá,
    };
  };

  it("celé hodiny roztáhne přes čtyři sloupce za hodinu", () => {
    expect(rozlož([aktivitaVČase(1, čas(10), čas(12))])).toEqual({
      buňky: [{ id: 1, odsazení: (10 - ZAČÁTEK) * 4, šířka: 8 }],
      zbývá: SLOUPCŮ_PROGRAMU - (12 - ZAČÁTEK) * 4,
    });
  });

  it("aktivity po čtvrthodinách umístí do jejich sloupců", () => {
    expect(rozlož([
      aktivitaVČase(2, čas(12), čas(12, 30)),
      aktivitaVČase(1, čas(10, 15), čas(11, 45)),
    ]).buňky).toEqual([
      { id: 1, odsazení: (10 - ZAČÁTEK) * 4 + 1, šířka: 6 },
      { id: 2, odsazení: 1, šířka: 2 },
    ]);
  });

  it("aktivitu přes půlnoc počítá od začátku programového dne", () => {
    expect(rozlož([aktivitaVČase(1, čas(23, 30), čas(25, 15))]).buňky).toEqual([
      { id: 1, odsazení: (23 - ZAČÁTEK) * 4 + 2, šířka: 7 },
    ]);
  });

  it("necelé čtvrthodiny zarovná ven, aby buňka nebyla prázdná", () => {
    expect(rozlož([aktivitaVČase(1, čas(10, 10), čas(10, 20))]).buňky).toEqual([
      { id: 1, odsazení: (10 - ZAČÁTEK) * 4, šířka: 2 },
    ]);
  });

  it("řádek má vždy tolik sloupců jako hlavička", () => {
    const { buňky, zbývá } = rozlož([
      aktivitaVČase(1, čas(8, 45), čas(9)),
      aktivitaVČase(2, čas(14, 15), čas(17, 30)),
      aktivitaVČase(3, čas(26), čas(29, 45)),
    ]);
    const sloupců = buňky.reduce((součet, { odsazení, šířka }) => součet + odsazení + šířka, zbývá);
    expect(sloupců).toBe(SLOUPCŮ_PROGRAMU);
  });

  it("aktivitu přes konec programu ořízne na konec tabulky", () => {
    // celý programový den 8:00–8:00 je 24 h, program má jen 22
    const { buňky, zbývá } = rozlož([aktivitaVČase(1, čas(ZAČÁTEK), čas(ZAČÁTEK + 24))]);
    expect(buňky).toEqual([{ id: 1, odsazení: 0, šířka: SLOUPCŮ_PROGRAMU }]);
    expect(zbývá).toBe(0);
  });

  it("aktivity sdílející po zarovnání čtvrthodinu dá do různých řádků", () => {
    const výsledek = připravTabulkuAktivit(
      [aktivitaVČase(1, čas(10), čas(10, 10)), aktivitaVČase(2, čas(10, 10), čas(11))],
      SeskupováníAktivit.linie,
    ) as PředpřivenáTabulkaAktivit;

    const řádky = výsledek["x"].map((buňka) => buňka.řádek);
    expect(new Set(řádky).size).toBe(2);
  });
});
