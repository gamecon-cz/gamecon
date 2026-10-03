import { describe, expect, it } from "vitest";
import { RazenyProdukt, seraditProdukty } from "./razeni";

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
