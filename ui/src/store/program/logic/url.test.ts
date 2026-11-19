import { describe, it, expect } from "vitest";
import { parsujUrl } from "./url";

const urlSFiltremStavů = (stavy: string[]) =>
  `http://localhost:3000/?stav=${encodeURIComponent(JSON.stringify(stavy))}`;

describe("parsujUrl – filtr stavů aktivit", () => {
  it("starý stav „nahradnik“ z dřívějšího odkazu čte jako „sledujici“", () => {
    expect(parsujUrl(urlSFiltremStavů(["nahradnik", "prihlasen"])).filtrStavAktivit)
      .toEqual(["sledujici", "prihlasen"]);
  });

  it("ostatní stavy nechá, jak jsou", () => {
    expect(parsujUrl(urlSFiltremStavů(["sledujici", "plno"])).filtrStavAktivit)
      .toEqual(["sledujici", "plno"]);
  });

  it("bez filtru v adrese filtr stavů nenastaví", () => {
    expect(parsujUrl("http://localhost:3000/").filtrStavAktivit).toBeUndefined();
  });
});
