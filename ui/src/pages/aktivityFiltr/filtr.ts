import { ApiFiltrAktivitKarta } from "../../api/filtrAktivit";

export type FiltrKaret = {
  text: string,
  štítky: number[],
  dny: string[],
  jenVolné: boolean,
};

export const PRÁZDNÝ_FILTR: FiltrKaret = Object.freeze({
  text: "",
  štítky: [],
  dny: [],
  jenVolné: false,
});

/** karta s textem, ve kterém se hledá (už normalizovaným) */
export type KartaProFiltr = ApiFiltrAktivitKarta & { text: string };

/** malá písmena bez diakritiky, ať „pribeh“ najde „Příběh“ */
export const normalizujText = (text: string): string =>
  text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

export const jeFiltrPrázdný = (filtr: FiltrKaret): boolean =>
  !filtr.text.trim() && !filtr.štítky.length && !filtr.dny.length && !filtr.jenVolné;

/**
 * Všechno kromě štítků. Zvlášť proto, aby šlo u každého štítku v nabídce
 * spočítat, kolik karet by s ním zbylo.
 */
export const filtrujBezŠtítků = <T extends KartaProFiltr>(karty: T[], filtr: FiltrKaret): T[] => {
  const slova = normalizujText(filtr.text).split(/\s+/).filter(Boolean);

  return karty.filter((karta) =>
    (!filtr.jenVolné || karta.volno)
    && (!filtr.dny.length || karta.dny.some((den) => filtr.dny.includes(den)))
    && slova.every((slovo) => karta.text.includes(slovo))
  );
};

/**
 * Stejně jako v programu (filtrujAktivity): z jedné kategorie stačí kterýkoli
 * vybraný štítek, štítky z různých kategorií musí platit všechny.
 */
export const filtrujPodleŠtítků = <T extends KartaProFiltr>(
  karty: T[],
  štítky: number[],
  kategorieŠtítků: Map<number, string>,
): T[] => {
  if (!štítky.length) return karty;

  const štítkyPodleKategorie = new Map<string, number[]>();
  for (const štítek of štítky) {
    const kategorie = kategorieŠtítků.get(štítek) ?? "";
    štítkyPodleKategorie.set(kategorie, (štítkyPodleKategorie.get(kategorie) ?? []).concat(štítek));
  }
  const skupiny = Array.from(štítkyPodleKategorie.values());

  return karty.filter((karta) =>
    skupiny.every((štítkyKategorie) => štítkyKategorie.some((štítek) => karta.tagy.includes(štítek)))
  );
};

export const filtrujKarty = <T extends KartaProFiltr>(
  karty: T[],
  filtr: FiltrKaret,
  kategorieŠtítků: Map<number, string>,
): T[] => filtrujPodleŠtítků(filtrujBezŠtítků(karty, filtr), filtr.štítky, kategorieŠtítků);

const TEXT_QUERY_KEY = "hledat";
const ŠTÍTKY_QUERY_KEY = "stitky";
const DNY_QUERY_KEY = "den";
const JEN_VOLNÉ_QUERY_KEY = "volne";

export const filtrZUrl = (url: string): FiltrKaret => {
  const parametry = new URL(url).searchParams;
  const seznam = (klíč: string): string[] => (parametry.get(klíč) ?? "").split(",").filter(Boolean);

  return {
    text: parametry.get(TEXT_QUERY_KEY) ?? "",
    štítky: seznam(ŠTÍTKY_QUERY_KEY).map(Number).filter(Number.isInteger),
    dny: seznam(DNY_QUERY_KEY),
    jenVolné: parametry.get(JEN_VOLNÉ_QUERY_KEY) === "1",
  };
};

/** zapíše filtr do adresy, ostatní parametry a kotvu nechá být */
export const urlSFiltrem = (url: string, filtr: FiltrKaret): string => {
  const urlObjekt = new URL(url);
  const nastav = (klíč: string, hodnota: string) => {
    if (hodnota) urlObjekt.searchParams.set(klíč, hodnota);
    else urlObjekt.searchParams.delete(klíč);
  };
  nastav(TEXT_QUERY_KEY, filtr.text);
  nastav(ŠTÍTKY_QUERY_KEY, filtr.štítky.join(","));
  nastav(DNY_QUERY_KEY, filtr.dny.join(","));
  nastav(JEN_VOLNÉ_QUERY_KEY, filtr.jenVolné ? "1" : "");
  // čárka do query smí, ?den=patek,sobota se čte líp než patek%2Csobota
  urlObjekt.search = urlObjekt.searchParams.toString().replace(/%2C/g, ",");

  return urlObjekt.toString();
};
