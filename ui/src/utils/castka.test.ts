import { describe, expect, it } from "vitest";
import { castkaProFormular, formatCastkaZApi, formatCena, formatujCastku } from "./castka";

/**
 * Stejné případy jako tests/Model/Uzivatel/FinanceFormatujCastkuTest.php, ať obě strany píšou částku stejně.
 */
describe("formatujCastku", () => {
  it.each([
    ["celé koruny bez desetin", 250, "250"],
    ["záporné celé koruny", -150, "-150"],
    ["nula", 0, "0"],
    ["záporná nula po zaokrouhlení", -0.001, "0"],
    ["haléře s čárkou", 250.6, "250,60"],
    ["záporné haléře", -12.34, "-12,34"],
    ["dvě desetinná místa nahoru", 0.125, "0,13"],
    ["plovoucí čárka nevadí", 0.1 + 0.2, "0,30"],
    ["zaokrouhlení na celou korunu", 99.999, "100"],
    ["tisíce s pevnou mezerou", 1234.5, "1\u00a0234,50"],
    ["celé tisíce", 1000, "1\u00a0000"],
    ["miliony záporně", -1234567.89, "-1\u00a0234\u00a0567,89"],
  ])("%s", (_popis, castka, ocekavano) => {
    expect(formatujCastku(castka)).toBe(ocekavano);
  });
});

describe("formatCastkaZApi", () => {
  it("desetinný zápis z API zkrátí na celé koruny", () => {
    expect(formatCastkaZApi("120.00")).toBe("120");
  });

  it("haléře nechá s čárkou", () => {
    expect(formatCastkaZApi("80.50")).toBe("80,50");
  });

  it("nečíselný text vrátí, jak přišel", () => {
    expect(formatCastkaZApi("zdarma")).toBe("zdarma");
  });
});

describe("formatCena", () => {
  it("připojí Kč přes tenkou mezeru", () => {
    expect(formatCena("120.00")).toBe("120\u2009Kč");
    expect(formatCena("80.50")).toBe("80,50\u2009Kč");
  });

  it("tisíce odděluje pevnou mezerou jako Finance::formatujCastku()", () => {
    expect(formatCena("1300.00")).toBe("1\u00a0300\u2009Kč");
    expect(formatCena("1300.50")).toBe("1\u00a0300,50\u2009Kč");
  });
});

describe("castkaProFormular", () => {
  it("odřízne nulové haléře a nechá tečku", () => {
    expect(castkaProFormular("120.00")).toBe("120");
    expect(castkaProFormular("80.50")).toBe("80.5");
    expect(castkaProFormular("0.00")).toBe("0");
  });

  it("nečíselný text vrátí, jak přišel", () => {
    expect(castkaProFormular("")).toBe("");
    expect(castkaProFormular("abc")).toBe("abc");
  });
});
