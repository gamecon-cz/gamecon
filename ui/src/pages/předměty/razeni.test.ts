import { describe, expect, it } from "vitest";
import { ApiProductTag } from "../../api/symfony/types";
import { RazenyProdukt, seraditProdukty, tagyKUlozeni } from "./razeni";

const produkt = (
  name: string,
  kategorie: string | null,
  { accommodationDay = null, archivedAt }: { accommodationDay?: number | null; archivedAt?: string } = {},
): RazenyProdukt => ({
  name,
  accommodationDay,
  // The API leaves out a null archivedAt, as it does every null field.
  ...(archivedAt === undefined ? {} : { archivedAt }),
  tags: kategorie === null ? [] : [{ code: "nesouvisejici", name: "Jiný štítek" }, { code: kategorie, name: kategorie }],
});

const nazvy = (produkty: RazenyProdukt[]) => seraditProdukty(produkty).map((serazeny) => serazeny.name);

describe("seraditProdukty", () => {
  it("řadí podle kategorie v pořadí legacy typů, bez kategorie nakonec", () => {
    expect(nazvy([
      produkt("Bez kategorie", null),
      produkt("Vstupné", "vstupne"),
      produkt("Oběd", "jidlo"),
      produkt("Tričko", "tricko"),
      produkt("Postel", "ubytovani"),
      produkt("Kostka", "predmet"),
    ])).toEqual(["Kostka", "Postel", "Tričko", "Oběd", "Vstupné", "Bez kategorie"]);
  });

  it("v kategorii řadí podle názvu česky, bez okrajových mezer", () => {
    expect(nazvy([
      produkt("Chléb", "predmet"),
      produkt(" Hrnek", "predmet"),
      produkt("Čaj", "predmet"),
      produkt("Cukr", "predmet"),
    ])).toEqual(["Cukr", "Čaj", " Hrnek", "Chléb"]);
  });

  it("ubytování řadí podle typu pokoje z prvního slova a pak podle noci", () => {
    expect(nazvy([
      produkt("Dvojlůžko pátek", "ubytovani", { accommodationDay: 2 }),
      produkt("Jednolůžko středa", "ubytovani", { accommodationDay: 0 }),
      produkt("Dvojlůžko čtvrtek", "ubytovani", { accommodationDay: 1 }),
      produkt("Dvojlůžko středa", "ubytovani", { accommodationDay: 0 }),
    ])).toEqual(["Dvojlůžko středa", "Dvojlůžko čtvrtek", "Dvojlůžko pátek", "Jednolůžko středa"]);
  });

  it("archivované dává za letošní, novější ročník dřív", () => {
    expect(nazvy([
      produkt("Kostka 2023", "predmet", { archivedAt: "2023-12-31T23:59:59+01:00" }),
      produkt("Tričko 2025", "tricko", { archivedAt: "2025-12-31T23:59:59+01:00" }),
      produkt("Kostka 2025", "predmet", { archivedAt: "2025-12-31T23:59:59+01:00" }),
      produkt("Vstupné", "vstupne"),
    ])).toEqual(["Vstupné", "Kostka 2025", "Tričko 2025", "Kostka 2023"]);
  });
});

describe("tagyKUlozeni", () => {
  const tag = (code: string): ApiProductTag => ({ "@id": `/symfony/api/product_tags/${code}`, code, name: code });
  const iri = (code: string) => `/symfony/api/product_tags/${code}`;

  it("vedle vybrané kategorie pošle štítky, které editor neupravuje", () => {
    expect(tagyKUlozeni(iri("jidlo"), [tag("jidlo"), tag("snidane")])).toEqual([iri("jidlo"), iri("snidane")]);
  });

  it("dosavadní kategorii nahradí vybranou, ostatní štítky nechá", () => {
    expect(tagyKUlozeni(iri("vstupne"), [tag("predmet"), tag("kostka")])).toEqual([iri("vstupne"), iri("kostka")]);
  });

  it("u produktu bez podštítků pošle jen kategorii", () => {
    expect(tagyKUlozeni(iri("predmet"), [tag("predmet")])).toEqual([iri("predmet")]);
  });

  it("u nového produktu bez dosavadních štítků pošle jen kategorii", () => {
    expect(tagyKUlozeni(iri("predmet"), [])).toEqual([iri("predmet")]);
  });
});
