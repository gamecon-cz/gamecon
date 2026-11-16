import { describe, expect, it } from "vitest";
import { ApiAccommodationType } from "../../api/symfony/types";
import { dnyVyberu, stejnaMnozina, zkontrolujVyber, zkontrolujVyberVMřížce } from "./vyberNoci";

const typ = (noci: Record<number, number>): ApiAccommodationType => ({
  productId: 1,
  name: "Kolej",
  description: "",
  price: "100.00",
  discountedPrice: "100.00",
  nights: Object.fromEntries(
    Object.entries(noci).map(([den, variantId]) => [
      den,
      { variantId, selected: false, remaining: 5, soldOut: false, reservedForOrganizers: null, locked: false },
    ]),
  ),
});

describe("dnyVyberu", () => {
  it("vrátí dny vybraných variant napříč typy, každý jednou a podle pořadí", () => {
    const typy = [typ({ 0: 11, 1: 12, 2: 13 }), typ({ 0: 21, 1: 22 })];

    expect(dnyVyberu(typy, [13, 21, 12, 22])).toEqual([0, 1, 2]);
  });

  it("variantu, kterou mřížka nezná, přeskočí", () => {
    expect(dnyVyberu([typ({ 0: 11 })], [99])).toEqual([]);
  });
});

describe("zkontrolujVyber", () => {
  it("prázdný výběr je v pořádku, ať je minimum jakékoli", () => {
    expect(zkontrolujVyber([], 2)).toEqual({ platny: true });
  });

  it("jedna noc stačí jen tomu, kdo na ni má právo", () => {
    expect(zkontrolujVyber([1], 1)).toEqual({ platny: true });
    expect(zkontrolujVyber([1], 2)).toEqual({
      platny: false,
      duvod: "Vyber ještě 1 navazující noc ubytování.",
    });
  });

  it.each([
    [[0, 1], 5, "Vyber ještě 3 navazující noci ubytování."],
    [[0], 6, "Vyber ještě 5 navazující nocí ubytování."],
  ])("řekne, kolik nocí chybí a skloňuje (%j z %i)", (dny, minimum, duvod) => {
    expect(zkontrolujVyber(dny, minimum)).toEqual({ platny: false, duvod });
  });

  it("noci s mezerou na sebe nenavazují", () => {
    expect(zkontrolujVyber([0, 2], 2)).toEqual({
      platny: false,
      duvod: "Vybrané noci ubytování na sebe musí navazovat.",
    });
  });

  it("navazující noci projdou", () => {
    expect(zkontrolujVyber([1, 2, 3], 2)).toEqual({ platny: true });
  });

  it("počet nocí se kontroluje dřív než návaznost, jako to řekl formulář", () => {
    expect(zkontrolujVyber([0], 3)).toMatchObject({ duvod: "Vyber ještě 2 navazující noci ubytování." });
  });
});

describe("zkontrolujVyberVMřížce", () => {
  it("posoudí výběr z nocí, které mřížka ukazuje", () => {
    const typy = [typ({ 0: 11, 1: 12 })];

    expect(zkontrolujVyberVMřížce(typy, [11], 2)).toEqual({
      platny: false,
      duvod: "Vyber ještě 1 navazující noc ubytování.",
    });
    expect(zkontrolujVyberVMřížce(typy, [11, 12], 2)).toEqual({ platny: true });
  });

  it("výběr s nocí, kterou mřížka neukazuje, nezdržuje: den té noci zná jen server", () => {
    expect(zkontrolujVyberVMřížce([typ({ 0: 11, 1: 12 })], [99, 11], 2)).toEqual({ platny: true });
  });
});

describe("stejnaMnozina", () => {
  it("nehledí na pořadí", () => {
    expect(stejnaMnozina([1, 2], [2, 1])).toBe(true);
  });

  it("pozná jinou množinu stejné velikosti i jiné velikosti", () => {
    expect(stejnaMnozina([1, 2], [1, 3])).toBe(false);
    expect(stejnaMnozina([1], [1, 2])).toBe(false);
  });
});
