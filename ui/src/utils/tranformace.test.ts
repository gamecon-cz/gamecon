import { describe, it, expect } from "vitest";
import { volnoTypZObsazenost } from "./tranformace";
import { ApiObsazenost } from "../api/program";

// Regresní krytí pro obarvení zaplněných týmových aktivit. Týmovka se počítá
// na týmy (t/kt), ne na hlavy (m+f / ku+km+kf), takže větev pro kt musí sama
// rozhodnout o zaplnění — jinak zaplněná týmovka zůstane v programu zelená
// (jako volná), přestože ostatní plné aktivity zbělají.

const obsazenost = (vlastnosti: Partial<ApiObsazenost>): ApiObsazenost =>
  ({ m: 0, f: 0, km: 0, kf: 0, ku: 0, ...vlastnosti } as ApiObsazenost);

describe("volnoTypZObsazenost – týmové aktivity", () => {
  it("zaplněnou týmovku označí jako plno", () => {
    expect(volnoTypZObsazenost(obsazenost({ ku: 25, m: 17, f: 4, kt: 5, t: 5 })))
      .toBe("x");
  });

  it("týmovku s volnými týmy nechá jako týmovou (volnou)", () => {
    expect(volnoTypZObsazenost(obsazenost({ ku: 25, m: 12, f: 1, kt: 5, t: 3 })))
      .toBe("t");
  });

  it("prázdnou týmovku nechá jako týmovou (volnou)", () => {
    expect(volnoTypZObsazenost(obsazenost({ ku: 5, kt: 1, t: 0 })))
      .toBe("t");
  });

  it("týmovku s jediným místem označí po prvním týmu jako plno", () => {
    // Reálný případ z programu: kt=1, t=1 se zobrazovalo jako 1/1, ale zeleně.
    expect(volnoTypZObsazenost(obsazenost({ ku: 5, m: 3, f: 1, kt: 1, t: 1 })))
      .toBe("x");
  });

  it("o zaplnění týmovky rozhoduje počet týmů, ne počet hlav", () => {
    // Hlav je málo (4 z 25), ale týmy jsou vyčerpané → plno.
    expect(volnoTypZObsazenost(obsazenost({ ku: 25, m: 3, f: 1, kt: 5, t: 5 })))
      .toBe("x");
  });
});

describe("volnoTypZObsazenost – netýmové aktivity beze změny", () => {
  it("zaplněnou aktivitu označí jako plno", () => {
    expect(volnoTypZObsazenost(obsazenost({ ku: 4, m: 2, f: 2 }))).toBe("x");
  });

  it("aktivitu bez omezení kapacity označí jako volnou", () => {
    expect(volnoTypZObsazenost(obsazenost({ m: 6, f: 2 }))).toBe("u");
  });

  it("volnou aktivitu označí jako volnou", () => {
    expect(volnoTypZObsazenost(obsazenost({ ku: 10, m: 2, f: 2 }))).toBe("u");
  });
});
