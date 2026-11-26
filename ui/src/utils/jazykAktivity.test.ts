import { describe, expect, it } from "vitest";
import { jazykyFiltruZUrl, krátkýŠtítekJazyka } from "./jazykAktivity";

describe("krátkýŠtítekJazyka", () => {
  it("na české stránce označí jen aktivity, které nejsou jen česky", () => {
    expect(krátkýŠtítekJazyka("cs", "cs")).toBeUndefined();
    expect(krátkýŠtítekJazyka("en", "cs")).toBe("EN");
    expect(krátkýŠtítekJazyka("cs-en", "cs")).toBe("CZ/EN");
    expect(krátkýŠtítekJazyka("nezavisla", "cs")).toBeUndefined();
  });

  it("na anglické stránce označí i českou, aby se nepletla s jazykově nezávislou", () => {
    expect(krátkýŠtítekJazyka("cs", "en")).toBe("CZ");
    expect(krátkýŠtítekJazyka("en", "en")).toBe("EN");
    expect(krátkýŠtítekJazyka("cs-en", "en")).toBe("CZ/EN");
    expect(krátkýŠtítekJazyka("nezavisla", "en")).toBeUndefined();
  });
});

describe("jazykyFiltruZUrl", () => {
  it("vezme pole známých jazyků", () => {
    expect(jazykyFiltruZUrl(["en", "nezavisla"])).toEqual(["en", "nezavisla"]);
  });

  it("neznámé jazyky vynechá", () => {
    expect(jazykyFiltruZUrl(["cs-en", "en", "xx", 1, null])).toEqual(["en"]);
  });

  it("bez známého jazyka nefiltruje", () => {
    expect(jazykyFiltruZUrl([])).toBeUndefined();
    expect(jazykyFiltruZUrl(["cs-en"])).toBeUndefined();
  });

  it("hodnotu, která není pole, nevezme", () => {
    expect(jazykyFiltruZUrl("en")).toBeUndefined();
    expect(jazykyFiltruZUrl({ 0: "en" })).toBeUndefined();
    expect(jazykyFiltruZUrl(null)).toBeUndefined();
    expect(jazykyFiltruZUrl(1)).toBeUndefined();
  });
});
