import { ApiFiltrAktivitKarta } from "../../api/filtrAktivit";

export type FiltrKaret = {
  text: string,
  štítky: number[],
  vypravěči: number[],
  dny: string[],
  jenVolné: boolean,
};

export const PRÁZDNÝ_FILTR: FiltrKaret = Object.freeze({
  text: "",
  štítky: [],
  vypravěči: [],
  dny: [],
  jenVolné: false,
});

/** karta s textem, ve kterém se hledá (už normalizovaným) */
export type KartaProFiltr = ApiFiltrAktivitKarta & { text: string };

/** malá písmena bez diakritiky, ať „pribeh“ najde „Příběh“ */
export const normalizujText = (text: string): string =>
  text.normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase();

export const jeFiltrPrázdný = (filtr: FiltrKaret): boolean =>
  !filtr.text.trim() && !filtr.štítky.length && !filtr.vypravěči.length && !filtr.dny.length && !filtr.jenVolné;

/** hledání, dny a volná místa */
const filtrujZáklad = <T extends KartaProFiltr>(karty: T[], filtr: FiltrKaret): T[] => {
  const slova = normalizujText(filtr.text).split(/\s+/).filter(Boolean);

  return karty.filter((karta) =>
    (!filtr.jenVolné || karta.volno)
    && (!filtr.dny.length || karta.dny.some((den) => filtr.dny.includes(den)))
    && slova.every((slovo) => karta.text.includes(slovo))
  );
};

/** stačí kterýkoli z vybraných vypravěčů */
export const filtrujPodleVypravěčů = <T extends KartaProFiltr>(karty: T[], vypravěči: number[]): T[] =>
  vypravěči.length
    ? karty.filter((karta) => karta.vypraveci.some((vypravěč) => vypravěči.includes(vypravěč)))
    : karty;

/**
 * Všechno kromě štítků. Zvlášť proto, aby šlo u každého štítku v nabídce
 * spočítat, kolik karet by s ním zbylo.
 */
export const filtrujBezŠtítků = <T extends KartaProFiltr>(karty: T[], filtr: FiltrKaret): T[] =>
  filtrujPodleVypravěčů(filtrujZáklad(karty, filtr), filtr.vypravěči);

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

/** všechno kromě vypravěčů, ze stejného důvodu jako filtrujBezŠtítků */
export const filtrujBezVypravěčů = <T extends KartaProFiltr>(
  karty: T[],
  filtr: FiltrKaret,
  kategorieŠtítků: Map<number, string>,
): T[] => filtrujPodleŠtítků(filtrujZáklad(karty, filtr), filtr.štítky, kategorieŠtítků);

export const filtrujKarty = <T extends KartaProFiltr>(
  karty: T[],
  filtr: FiltrKaret,
  kategorieŠtítků: Map<number, string>,
): T[] => filtrujPodleŠtítků(filtrujBezŠtítků(karty, filtr), filtr.štítky, kategorieŠtítků);

const TEXT_QUERY_KEY = "hledat";
const ŠTÍTKY_QUERY_KEY = "stitky";
const VYPRAVĚČI_QUERY_KEY = "vypravec";
const DNY_QUERY_KEY = "den";
const JEN_VOLNÉ_QUERY_KEY = "volne";

export const filtrZUrl = (url: string): FiltrKaret => {
  const parametry = new URL(url).searchParams;
  const seznam = (klíč: string): string[] => (parametry.get(klíč) ?? "").split(",").filter(Boolean);
  const čísla = (klíč: string): number[] => seznam(klíč).map(Number).filter(Number.isInteger);

  return {
    text: parametry.get(TEXT_QUERY_KEY) ?? "",
    štítky: čísla(ŠTÍTKY_QUERY_KEY),
    vypravěči: čísla(VYPRAVĚČI_QUERY_KEY),
    dny: seznam(DNY_QUERY_KEY),
    jenVolné: parametry.get(JEN_VOLNÉ_QUERY_KEY) === "1",
  };
};

// čárka do query smí, ?den=patek,sobota se čte líp než patek%2Csobota
const sČárkami = (urlObjekt: URL): string => {
  urlObjekt.search = urlObjekt.searchParams.toString().replace(/%2C/g, ",");

  return urlObjekt.toString();
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
  nastav(VYPRAVĚČI_QUERY_KEY, filtr.vypravěči.join(","));
  nastav(DNY_QUERY_KEY, filtr.dny.join(","));
  nastav(JEN_VOLNÉ_QUERY_KEY, filtr.jenVolné ? "1" : "");

  return sČárkami(urlObjekt);
};

const ROČNÍK_QUERY_KEY = "rocnik";

/**
 * Adresa stejné stránky s jiným ročníkem (aktivity načte PHP), letošní je bez parametru.
 * Filtr zůstane, co v tom ročníku není (štítek, vypravěč, den), se při načtení zahodí.
 * Kotva zmizí, aktivita z jednoho ročníku v jiném není.
 */
export const urlSRočníkem = (url: string, ročník: number, letošní: number): string => {
  const urlObjekt = new URL(url);
  if (ročník === letošní) urlObjekt.searchParams.delete(ROČNÍK_QUERY_KEY);
  else urlObjekt.searchParams.set(ROČNÍK_QUERY_KEY, ročník.toString());
  urlObjekt.hash = "";

  return sČárkami(urlObjekt);
};
