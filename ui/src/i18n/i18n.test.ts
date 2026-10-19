import { describe, expect, it } from "vitest";
import { t } from ".";
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

  it("anglický slovník používá stejné parametry jako český", () => {
    const parametry = (text: string) => [...text.matchAll(/\{(\w+)\}/g)].map(x => x[1]).sort();
    for (const klíč of Object.keys(cs) as (keyof typeof cs)[]) {
      expect(parametry(en[klíč]), klíč).toEqual(parametry(cs[klíč]));
    }
  });
});
