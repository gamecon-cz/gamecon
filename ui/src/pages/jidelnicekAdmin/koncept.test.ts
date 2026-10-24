import { describe, expect, it } from "vitest";
import { ApiAdminMealMenuSlot } from "../../api/symfony/types";
import { doZapisu, jeVychozi, jeZmeneno, konceptZeSlotu, nastavVychozi, noveJidlo, posun, pridejKopii } from "./koncept";

const slot: ApiAdminMealMenuSlot = {
  variantId: 11,
  day: 2,
  dayName: "pátek",
  name: "Oběd pátek",
  portionCount: 3,
  unchosenCount: 1,
  dishes: [
    { id: 1, name: "Guláš", description: "lepek", vegetarian: false, default: true, chosenCount: 2 },
    { id: 2, name: "Rizoto", description: null, vegetarian: true, default: false, chosenCount: 0 },
  ],
};

describe("koncept jídelníčku", () => {
  it("neupravený slot není změněný", () => {
    expect(jeZmeneno(konceptZeSlotu(slot), slot)).toBe(false);
  });

  it("prázdný nový řádek se neposílá ani nepočítá jako změna", () => {
    const koncept = [...konceptZeSlotu(slot), noveJidlo()];

    expect(doZapisu(koncept)).toHaveLength(2);
    expect(jeZmeneno(koncept, slot)).toBe(false);
  });

  it("posílá ořezané hodnoty a prázdný popis jako null", () => {
    const koncept = [{ ...noveJidlo(), name: "  Pizza ", description: "  " }];

    expect(doZapisu(koncept)).toEqual([{ name: "Pizza", description: null, vegetarian: false, default: false }]);
  });

  it("výchozí je jen jedno", () => {
    const koncept = konceptZeSlotu(slot);

    const zmeneny = nastavVychozi(koncept, koncept[1].klic);

    expect(zmeneny.map((jidlo) => jidlo.default)).toEqual([false, true]);
  });

  it("bez označeného výchozího je výchozí první, stejně jako na serveru", () => {
    const koncept = konceptZeSlotu(slot).map((jidlo) => ({ ...jidlo, default: false }));

    expect(koncept.map((jidlo) => jeVychozi(koncept, jidlo))).toEqual([true, false]);
  });

  it("kopie obědu vznikne jako nová jídla a nepřidá druhé výchozí", () => {
    const vecere = [{ ...noveJidlo(), name: "Pizza", default: true }];

    const s = pridejKopii(vecere, konceptZeSlotu(slot));

    expect(s.map((jidlo) => [jidlo.name, jidlo.id, jidlo.default, jidlo.chosenCount])).toEqual([
      ["Pizza", undefined, true, 0],
      ["Guláš", undefined, false, 0],
      ["Rizoto", undefined, false, 0],
    ]);
  });

  it("kopie do prázdné večeře převezme výchozí jídlo obědu", () => {
    expect(pridejKopii([], konceptZeSlotu(slot)).map((jidlo) => jidlo.default)).toEqual([true, false]);
  });

  it("posun za okraj nic nezmění", () => {
    const koncept = konceptZeSlotu(slot);

    expect(posun(koncept, 0, -1)).toBe(koncept);
    expect(posun(koncept, 0, 1).map((jidlo) => jidlo.name)).toEqual(["Rizoto", "Guláš"]);
  });
});
