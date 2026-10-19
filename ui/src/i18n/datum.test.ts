import { afterEach, describe, expect, it } from "vitest";
import { GAMECON_KONSTANTY } from "../env";
import { formátujDatumProČtenáře, popisekSkupiny } from "./datum";

// pátek 17. 7. 2026 v poledne pražského času
const PÁTEK = new Date("2026-07-17T10:00:00Z");

describe("dny pro čtenáře", () => {
  afterEach(() => {
    GAMECON_KONSTANTY.JAZYK = "cs";
  });

  it("den jako klíč skupiny (český název s háčky) přeloží do jazyka stránky", () => {
    expect(popisekSkupiny("pátek")).toBe("pátek");
    GAMECON_KONSTANTY.JAZYK = "en";
    expect(popisekSkupiny("pátek")).toBe("Friday");
  });

  it("linii nebo místnost nechá, jak je", () => {
    GAMECON_KONSTANTY.JAZYK = "en";
    expect(popisekSkupiny("larpy")).toBe("larpy");
    expect(popisekSkupiny("Klubovna")).toBe("Klubovna");
  });

  it("datum záložky dne", () => {
    expect(formátujDatumProČtenáře(PÁTEK)).toBe("pátek 17.7.");
    GAMECON_KONSTANTY.JAZYK = "en";
    expect(formátujDatumProČtenáře(PÁTEK)).toBe("Friday 17/7");
    expect(formátujDatumProČtenáře(PÁTEK, true)).toBe("Friday");
  });
});
