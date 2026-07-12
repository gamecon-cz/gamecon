import { describe, it, expect } from "vitest";
import {
  připravTabulkuAktivit,
  SeskupováníAktivit,
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
});
