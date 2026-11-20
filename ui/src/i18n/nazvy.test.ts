import { afterEach, describe, expect, it } from "vitest";
import { GAMECON_KONSTANTY } from "../env";
import type { ApiTag } from "../api/program";
import { tagyZId } from "../utils";
import { názevLinie, názevŠtítku, názvyLinií, štítkyPodleKategorie, textVJazyce } from "./nazvy";

const tag = (id: number, nazev: string, nazevEn: string | undefined, nazevKategorie: string, nazevKategorieEn?: string): ApiTag =>
  ({ id, nazev, nazevEn, nazevKategorie, nazevKategorieEn });

// pořadí ze serveru: pořadí kategorie, pak český název
const TAGY: ApiTag[] = [
  tag(1, "Horor", "Horror", "žánr", "genre"),
  tag(2, "Komedie", "Comedy", "žánr", "genre"),
  tag(3, "Věk: 10+", "Age: 10+", "omezení Věk", "age limit"),
  tag(4, "Věk: 8+", "Age: 8+", "omezení Věk", "age limit"),
  // JSON vygenerovaný před zavedením anglických názvů
  tag(5, "Bez překladu", undefined, "starší kategorie"),
];

describe("názvy z DB v jazyce stránky", () => {
  afterEach(() => {
    GAMECON_KONSTANTY.JAZYK = "cs";
  });

  it("na anglické stránce anglický text, jinak český", () => {
    expect(textVJazyce("Horor", "Horror", "cs")).toBe("Horor");
    expect(textVJazyce("Horor", "Horror", "en")).toBe("Horror");
    expect(textVJazyce("Horor", undefined, "en")).toBe("Horor");
    expect(textVJazyce("Horor", "", "en")).toBe("Horor");
    expect(textVJazyce("Horor", null, "en")).toBe("Horor");
  });

  it("bez předaného jazyka platí jazyk stránky", () => {
    expect(názevŠtítku(TAGY[0])).toBe("Horor");
    GAMECON_KONSTANTY.JAZYK = "en";
    expect(názevŠtítku(TAGY[0])).toBe("Horror");
    expect(názevŠtítku(TAGY[4])).toBe("Bez překladu");
  });

  it("linie: klíčem zůstává český název", () => {
    const aktivity = [
      { linie: "larpy", linieEn: "larps" },
      { linie: "deskoherna", linieEn: "board game library" },
      { linie: "starší linie" },
    ];
    expect(názevLinie(aktivity[0], "en")).toBe("larps");
    expect(názvyLinií(aktivity, "en")).toEqual({
      "larpy": "larps",
      "deskoherna": "board game library",
      "starší linie": "starší linie",
    });
    expect(názvyLinií(aktivity, "cs")).toEqual({
      "larpy": "larpy",
      "deskoherna": "deskoherna",
      "starší linie": "starší linie",
    });
  });

  it("štítky aktivity v jazyce stránky", () => {
    GAMECON_KONSTANTY.JAZYK = "en";
    expect(tagyZId([2, 99, 5], TAGY)).toEqual(["Comedy", "Bez překladu"]);
    GAMECON_KONSTANTY.JAZYK = "cs";
    expect(tagyZId([2], TAGY)).toEqual(["Komedie"]);
  });
});

describe("štítky po kategoriích", () => {
  it("česky drží pořadí ze serveru a český název kategorie", () => {
    const skupiny = štítkyPodleKategorie(TAGY, "cs");
    expect(skupiny.map((x) => x.kategorie)).toEqual(["žánr", "omezení Věk", "starší kategorie"]);
    expect(skupiny.map((x) => x.názevKategorie)).toEqual(["žánr", "omezení Věk", "starší kategorie"]);
    expect(skupiny[1].tagy.map((x) => x.id)).toEqual([3, 4]);
  });

  it("anglicky ukáže anglický název kategorie, klíč nechá český a štítky seřadí podle anglického názvu", () => {
    const skupiny = štítkyPodleKategorie(TAGY, "en");
    expect(skupiny.map((x) => x.kategorie)).toEqual(["žánr", "omezení Věk", "starší kategorie"]);
    expect(skupiny.map((x) => x.názevKategorie)).toEqual(["genre", "age limit", "starší kategorie"]);
    // čísla podle hodnoty, ne podle znaků
    expect(skupiny[1].tagy.map((x) => x.id)).toEqual([4, 3]);
  });
});
