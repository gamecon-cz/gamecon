import { describe, it, expect } from "vitest";
import {
  filtrujKarty, filtrZUrl, FiltrKaret, jeFiltrPrázdný, KartaProFiltr, normalizujText, PRÁZDNÝ_FILTR, urlSFiltrem,
} from "./filtr";

const karta = (htmlId: string, vlastnosti: Partial<KartaProFiltr>): KartaProFiltr => ({
  htmlId,
  id: 0,
  tagy: [],
  dny: [],
  volno: false,
  text: "",
  ...vlastnosti,
});

const ŽÁNR_FANTASY = 1;
const ŽÁNR_HOROR = 2;
const VĚK_15 = 3;

const kategorieŠtítků = new Map<number, string>([
  [ŽÁNR_FANTASY, "žánr"],
  [ŽÁNR_HOROR, "žánr"],
  [VĚK_15, "omezení Věk"],
]);

const karty: KartaProFiltr[] = [
  karta("draci-doupe", { tagy: [ŽÁNR_FANTASY], dny: ["patek"], volno: true, text: normalizujText("Dračí doupě, příběh") }),
  karta("hrbitov", { tagy: [ŽÁNR_HOROR, VĚK_15], dny: ["sobota"], text: normalizujText("Hřbitov o půlnoci") }),
  karta("les", { tagy: [ŽÁNR_FANTASY, VĚK_15], dny: ["patek", "nedele"], volno: true, text: normalizujText("Temný les") }),
];

const htmlIdčka = (filtr: Partial<FiltrKaret>): string[] =>
  filtrujKarty(karty, { ...PRÁZDNÝ_FILTR, ...filtr }, kategorieŠtítků).map((k) => k.htmlId);

describe("filtrujKarty", () => {
  it("bez filtru nechá všechny karty", () => {
    expect(htmlIdčka({})).toEqual(["draci-doupe", "hrbitov", "les"]);
  });

  it("hledá bez ohledu na diakritiku a velikost písmen", () => {
    expect(htmlIdčka({ text: "PRIBEH" })).toEqual(["draci-doupe"]);
    expect(htmlIdčka({ text: "hřbitov" })).toEqual(["hrbitov"]);
  });

  it("u víc slov musí karta obsahovat všechna", () => {
    expect(htmlIdčka({ text: "temny les" })).toEqual(["les"]);
    expect(htmlIdčka({ text: "temny pribeh" })).toEqual([]);
  });

  it("nechá kartu, která má termín v kterémkoli vybraném dni", () => {
    expect(htmlIdčka({ dny: ["nedele"] })).toEqual(["les"]);
    expect(htmlIdčka({ dny: ["sobota", "nedele"] })).toEqual(["hrbitov", "les"]);
  });

  it("jen volné vyřadí karty bez volného místa", () => {
    expect(htmlIdčka({ jenVolné: true })).toEqual(["draci-doupe", "les"]);
  });

  it("štítky z jedné kategorie stačí kterýkoli", () => {
    expect(htmlIdčka({ štítky: [ŽÁNR_FANTASY, ŽÁNR_HOROR] })).toEqual(["draci-doupe", "hrbitov", "les"]);
  });

  it("štítky z různých kategorií musí platit všechny", () => {
    expect(htmlIdčka({ štítky: [ŽÁNR_FANTASY, VĚK_15] })).toEqual(["les"]);
  });

  it("filtry se sčítají", () => {
    expect(htmlIdčka({ štítky: [VĚK_15], dny: ["sobota"], jenVolné: true })).toEqual([]);
  });
});

describe("filtr v adrese", () => {
  const filtr: FiltrKaret = { text: "temný les", štítky: [1, 3], dny: ["patek", "sobota"], jenVolné: true };

  it("přežije cestu do adresy a zpátky", () => {
    expect(filtrZUrl(urlSFiltrem("https://gamecon.cz/rpg", filtr))).toEqual(filtr);
  });

  it("dny a štítky píše s čárkami, ne %2C", () => {
    expect(urlSFiltrem("https://gamecon.cz/rpg", filtr)).toContain("den=patek,sobota");
  });

  it("nechá cizí parametry i kotvu a prázdný filtr z adresy zmizí", () => {
    const url = urlSFiltrem("https://gamecon.cz/rpg?neco=1&den=patek#draci-doupe", PRÁZDNÝ_FILTR);
    expect(url).toBe("https://gamecon.cz/rpg?neco=1#draci-doupe");
  });

  it("bez parametrů je filtr prázdný a nesmysly ve štítcích zahodí", () => {
    expect(filtrZUrl("https://gamecon.cz/rpg")).toEqual(PRÁZDNÝ_FILTR);
    expect(filtrZUrl("https://gamecon.cz/rpg?stitky=1,abc,2").štítky).toEqual([1, 2]);
  });
});

describe("jeFiltrPrázdný", () => {
  it("mezery v hledání se nepočítají", () => {
    expect(jeFiltrPrázdný({ ...PRÁZDNÝ_FILTR, text: "  " })).toBe(true);
    expect(jeFiltrPrázdný({ ...PRÁZDNÝ_FILTR, dny: ["patek"] })).toBe(false);
  });
});
