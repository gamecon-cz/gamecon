import { describe, expect, it } from "vitest";
import { t, tSPrvky } from ".";
import { cs } from "./cs";
import { en } from "./en";

describe("t", () => {
  it("vrátí text v požadovaném jazyce", () => {
    expect(t("program.nadpis.muj", {}, "cs")).toBe("Můj program");
    expect(t("program.nadpis.muj", {}, "en")).toBe("My programme");
  });

  it("dosadí parametry a neznámé nechá být", () => {
    expect(t("program.nadpis.den", { den: "pátek" }, "cs")).toBe("Program pátek");
    expect(t("program.nadpis.den", {}, "cs")).toBe("Program {den}");
  });

  it("neznámý klíč vrátí sám sebe jako PHP t(), nespadne", () => {
    expect(t("neni.ve.slovniku" as keyof typeof cs, {}, "en")).toBe("neni.ve.slovniku");
    // názevJazykaFiltru() s neznámým jazykem z URL tak dřív shodil celý program
    expect(() => t(undefined as unknown as keyof typeof cs, {}, "cs")).not.toThrow();
  });

  it("anglický slovník má stejné klíče jako český", () => {
    expect(Object.keys(en).sort()).toEqual(Object.keys(cs).sort());
  });

  it("anglický slovník používá stejné parametry jako český", () => {
    const parametry = (text: string) => [...text.matchAll(/\{(\w+)\}/g)].map(x => x[1]).sort();
    for (const klíč of Object.keys(cs) as (keyof typeof cs)[]) {
      expect(parametry(en[klíč]), klíč).toEqual(parametry(cs[klíč]));
    }
  });
});

describe("tSPrvky", () => {
  const PRVEK = { prvek: "odpočet" };

  it("vrátí části věty s prvkem tam, kde ho má slovník", () => {
    expect(tSPrvky("tym.odpocet.smazan", { cas: PRVEK }, {}, "cs"))
      .toEqual(["Za ", PRVEK, " bude tým automaticky smazán"]);
    expect(tSPrvky("tym.odpocet.smazan", { cas: PRVEK }, {}, "en"))
      .toEqual(["The team will be deleted automatically in ", PRVEK]);
  });

  it("ostatní parametry dosadí jako text", () => {
    expect(tSPrvky("tym.otazka.odebrat", { tym: PRVEK }, { hrac: "Jana", aktivita: "Turnaj" }, "cs"))
      .toEqual(["Opravdu chcete odebrat hráče Jana z týmu ", PRVEK, " na aktivitě Turnaj?"]);
  });

  it("text parametru, který vypadá jako zástupný znak prvku, za prvek nezamění", () => {
    expect(tSPrvky("tym.otazka.odebrat", { aktivita: PRVEK }, { hrac: "{aktivita}", tym: "A" }, "cs"))
      .toEqual(["Opravdu chcete odebrat hráče {aktivita} z týmu A na aktivitě ", PRVEK, "?"]);
  });

  it("neznámý parametr nechá, jak je, jako t()", () => {
    expect(tSPrvky("tym.priprava.smazan_za", {}, {}, "cs")).toEqual(["Tým bude automaticky smazán za {cas}"]);
  });
});
