import { ApiProduct } from "../../api/symfony/types";

/** Category tag codes that classify a product (mutually exclusive), in legacy's type order. */
export const KATEGORIE_TAG_KODY = [
  "predmet",
  "ubytovani",
  "tricko",
  "jidlo",
  "vstupne",
  "parcon",
  "proplaceni_bonusu",
] as const;

export type RazenyProdukt = Pick<ApiProduct, "name" | "tags" | "accommodationDay" | "archivedAt">;

const kategorie = (produkt: RazenyProdukt): number => {
  const poradi = produkt.tags.map((tag) => KATEGORIE_TAG_KODY.indexOf(tag.code as typeof KATEGORIE_TAG_KODY[number]))
    .find((index) => index !== -1);
  return poradi ?? KATEGORIE_TAG_KODY.length;
};

// Archived on the last day of their year; this year's catalog has none and goes first.
const rocnik = (produkt: RazenyProdukt): number =>
  produkt.archivedAt ? Number(produkt.archivedAt.slice(0, 4)) : Number.MAX_SAFE_INTEGER;

// Legacy keys accommodation by the room type in the name's first word, then by the night.
const typPokoje = (nazev: string): string => nazev.split(" ")[0];

const porovnatNazvy = (prvni: string, druhy: string): number => prvni.localeCompare(druhy, "cs");

/** The order legacy's product admin listed them in, past years' archived products after this year's. */
export const seraditProdukty = <Produkt extends RazenyProdukt>(produkty: Produkt[]): Produkt[] =>
  [...produkty].sort((prvni, druhy) => {
    const nazevPrvniho = prvni.name.trim();
    const nazevDruheho = druhy.name.trim();
    const jeUbytovani = kategorie(prvni) === KATEGORIE_TAG_KODY.indexOf("ubytovani");

    return rocnik(druhy) - rocnik(prvni)
      || kategorie(prvni) - kategorie(druhy)
      || (jeUbytovani ? porovnatNazvy(typPokoje(nazevPrvniho), typPokoje(nazevDruheho)) : 0)
      || (jeUbytovani ? (prvni.accommodationDay ?? -1) - (druhy.accommodationDay ?? -1) : 0)
      || porovnatNazvy(nazevPrvniho, nazevDruheho);
  });
