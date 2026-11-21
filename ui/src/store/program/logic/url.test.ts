import { afterEach, describe, expect, it, vi } from "vitest";
import { GAMECON_KONSTANTY } from "../../../env";
import { generujUrl, parsujUrl, URL_STATE_VÝCHOZÍ_STAV } from "./url";

const urlSJazykemAktivity = (hodnota: string) =>
  `${GAMECON_KONSTANTY.BASE_PATH_PAGE}?jazykAktivity=${encodeURIComponent(hodnota)}`;

describe("URL filtru jazyka aktivit", () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it("filtr přežije cestu do URL a zpět", () => {
    const url = generujUrl({ ...URL_STATE_VÝCHOZÍ_STAV, filtrJazyk: ["en", "nezavisla"] });

    expect(url).toContain("jazykAktivity=");
    expect(parsujUrl(url ?? "").filtrJazyk).toEqual(["en", "nezavisla"]);
  });

  it("bez filtru parametr do URL nedá", () => {
    expect(generujUrl({ ...URL_STATE_VÝCHOZÍ_STAV })).not.toContain("jazykAktivity");
    expect(parsujUrl(GAMECON_KONSTANTY.BASE_PATH_PAGE).filtrJazyk).toBeUndefined();
  });

  it("neznámé jazyky z URL zahodí, zbytek nechá", () => {
    expect(parsujUrl(urlSJazykemAktivity(JSON.stringify(["cs-en", "en"]))).filtrJazyk).toEqual(["en"]);
  });

  it("bez známého jazyka nefiltruje", () => {
    expect(parsujUrl(urlSJazykemAktivity(JSON.stringify(["cs-en"]))).filtrJazyk).toBeUndefined();
    expect(parsujUrl(urlSJazykemAktivity(JSON.stringify([]))).filtrJazyk).toBeUndefined();
  });

  it("hodnotu, která není pole, nevezme", () => {
    expect(parsujUrl(urlSJazykemAktivity(JSON.stringify("en"))).filtrJazyk).toBeUndefined();
    expect(parsujUrl(urlSJazykemAktivity(JSON.stringify({ 0: "en" }))).filtrJazyk).toBeUndefined();
  });

  it("nesmysl, který není JSON, nevezme", () => {
    vi.spyOn(console, "error").mockImplementation(() => undefined);
    expect(parsujUrl(urlSJazykemAktivity("en")).filtrJazyk).toBeUndefined();
  });
});
