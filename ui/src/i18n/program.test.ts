import { describe, expect, it } from "vitest";
import { cenaAktivity, cenyNáhledu, popisekPřihlášen, zkratkaDne } from "./program";

describe("legenda programu", () => {
  it("přihlášení česky podle pohlaví, bez pohlaví v mužském tvaru", () => {
    expect(popisekPřihlášen("f", "cs")).toBe("Přihlášena");
    expect(popisekPřihlášen("m", "cs")).toBe("Přihlášen");
    expect(popisekPřihlášen(undefined, "cs")).toBe("Přihlášen");
  });

  it("anglicky rod nemá", () => {
    expect(popisekPřihlášen("f", "en")).toBe("Signed up");
    expect(popisekPřihlášen("m", "en")).toBe("Signed up");
  });
});

describe("cena v náhledu aktivity", () => {
  it("česky bez měny jako dřív, anglicky s CZK", () => {
    expect(cenaAktivity(150, "cs")).toBe("150");
    expect(cenaAktivity(150, "en")).toBe("CZK 150");
    expect(cenaAktivity(1500, "en")).toBe("CZK 1,500");
    expect(cenaAktivity(1500, "cs")).toBe("1500");
  });

  it("nulová cena je zdarma", () => {
    expect(cenaAktivity(0, "cs")).toBe("zdarma");
    expect(cenaAktivity(0, "en")).toBe("free");
  });

  it("plná cena bez porovnání se základní", () => {
    expect(cenyNáhledu({ cenaZaklad: 150, slevaNasobic: 1 }, "cs")).toEqual({ cena: "150", základní: undefined });
  });

  it("se slevou ukáže i základní cenu s hvězdičkou", () => {
    expect(cenyNáhledu({ cenaZaklad: 150, slevaNasobic: 0.5 }, "cs")).toEqual({ cena: "75", základní: "*150" });
    expect(cenyNáhledu({ cenaZaklad: 150, slevaNasobic: 0.5 }, "en")).toEqual({ cena: "CZK 75", základní: "*CZK 150" });
  });

  it("násobič 0 je zdarma, základní cena zůstane", () => {
    expect(cenyNáhledu({ cenaZaklad: 150, slevaNasobic: 0 }, "cs")).toEqual({ cena: "zdarma", základní: "*150" });
    expect(cenyNáhledu({ cenaZaklad: 0, slevaNasobic: 0 }, "en")).toEqual({ cena: "free", základní: "*free" });
  });

  it("bez násobiče (nepřihlášený) plná cena a základní k porovnání jako dřív", () => {
    expect(cenyNáhledu({ cenaZaklad: 150 }, "cs")).toEqual({ cena: "150", základní: "*150" });
    expect(cenyNáhledu({ cenaZaklad: 0 }, "cs")).toEqual({ cena: "zdarma", základní: "*zdarma" });
  });
});

describe("zkratka dne", () => {
  // pátek 17. 7. 2026 v poledne
  const PÁTEK = new Date("2026-07-17T10:00:00Z");

  it("v jazyce stránky", () => {
    expect(zkratkaDne(PÁTEK, "cs")).toBe("pá");
    expect(zkratkaDne(PÁTEK, "en")).toBe("Fri");
  });
});
