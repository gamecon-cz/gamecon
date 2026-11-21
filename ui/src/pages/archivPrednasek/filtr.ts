import { ApiArchivPrednasek, ApiArchivPrednaska } from "../../api/archivPrednasek";
import { obsahujeVšechnaSlova, slovaHledání } from "../filtrStranky/text";
import { číslaZParametru, nastavParametr, sČárkami, seznamZParametru } from "../filtrStranky/url";

/*
 * Filtr archivu přednášek: mezi kategoriemi (rok, přednášející, štítky, záznam, hledání) platí A ZÁROVEŇ,
 * uvnitř kategorie NEBO (záměr). Štítky jsou klíčová slova i štítky z programu dohromady, podle klíče názvu.
 */

export type FiltrArchivu = {
  text: string,
  roky: number[],
  přednášející: number[],
  štítky: string[],
  jenSeZáznamem: boolean,
};

export const PRÁZDNÝ_FILTR_ARCHIVU: FiltrArchivu = Object.freeze({
  text: "",
  roky: [],
  přednášející: [],
  štítky: [],
  jenSeZáznamem: false,
});

/** přednáška s textem, ve kterém se hledá (název, krátká a dlouhá anotace), už normalizovaným */
export type PřednáškaProFiltr = ApiArchivPrednaska & { text: string };

/** kategorie s výběrem z možností, u každé možnosti se počítá, kolik přednášek by s ní zbylo */
export type KategorieVýběru = "roky" | "přednášející" | "štítky";

type HodnotaKategorie<K extends KategorieVýběru> = FiltrArchivu[K][number];

export const jeFiltrArchivuPrázdný = (filtr: FiltrArchivu): boolean =>
  !filtr.text.trim() && !filtr.roky.length && !filtr.přednášející.length && !filtr.štítky.length && !filtr.jenSeZáznamem;

const odpovídáKategorii = <K extends KategorieVýběru>(
  přednáška: PřednáškaProFiltr,
  kategorie: K,
  vybrané: readonly HodnotaKategorie<K>[],
): boolean => {
  if (!vybrané.length) return true;
  switch (kategorie) {
    case "roky":
      return (vybrané as readonly number[]).includes(přednáška.rok);
    case "přednášející":
      return přednáška.prednasejici.some((id) => (vybrané as readonly number[]).includes(id));
    default:
      return přednáška.stitky.some((klíč) => (vybrané as readonly string[]).includes(klíč));
  }
};

/**
 * @param bez kategorie, která se nefiltruje (kvůli počtům u jejích možností)
 */
export const filtrujPřednášky = <T extends PřednáškaProFiltr>(
  přednášky: T[],
  filtr: FiltrArchivu,
  bez?: KategorieVýběru,
): T[] => {
  const slova = slovaHledání(filtr.text);

  return přednášky.filter((přednáška) =>
    (!filtr.jenSeZáznamem || přednáška.zaznam)
    && obsahujeVšechnaSlova(přednáška.text, slova)
    && (bez === "roky" || odpovídáKategorii(přednáška, "roky", filtr.roky))
    && (bez === "přednášející" || odpovídáKategorii(přednáška, "přednášející", filtr.přednášející))
    && (bez === "štítky" || odpovídáKategorii(přednáška, "štítky", filtr.štítky))
  );
};

export type PočetMožnosti = {
  počet: number,
  /** počet značí, kolik přednášek výběrem přibude (v kategorii už něco vybrané je) */
  navíc: boolean,
};

/**
 * Jako na stránce linie: bez výběru v kategorii, kolik přednášek s možností zbude; s výběrem, kolik jich přibude.
 */
export const počtyMožností = <K extends KategorieVýběru>(
  přednášky: PřednáškaProFiltr[],
  filtr: FiltrArchivu,
  kategorie: K,
  možnosti: HodnotaKategorie<K>[],
): Map<HodnotaKategorie<K>, PočetMožnosti> => {
  const bezKategorie = filtrujPřednášky(přednášky, filtr, kategorie);
  const vybrané = filtr[kategorie] as HodnotaKategorie<K>[];
  const zobrazeno = bezKategorie.filter((přednáška) => odpovídáKategorii(přednáška, kategorie, vybrané)).length;

  return new Map(možnosti.map((možnost) => {
    const sMožností = bezKategorie.filter((přednáška) =>
      odpovídáKategorii(přednáška, kategorie, vybrané.length ? [...vybrané, možnost] : [možnost])
    ).length;

    return [možnost, vybrané.length
      ? { počet: sMožností - zobrazeno, navíc: true }
      : { počet: sMožností, navíc: false }];
  }));
};

const TEXT_QUERY_KEY = "hledat";
const ROKY_QUERY_KEY = "rok";
const PŘEDNÁŠEJÍCÍ_QUERY_KEY = "prednasejici";
const ŠTÍTKY_QUERY_KEY = "stitky";
const ZÁZNAM_QUERY_KEY = "zaznam";

export const filtrArchivuZUrl = (url: string): FiltrArchivu => {
  const parametry = new URL(url).searchParams;

  return {
    text: parametry.get(TEXT_QUERY_KEY) ?? "",
    roky: číslaZParametru(parametry, ROKY_QUERY_KEY),
    přednášející: číslaZParametru(parametry, PŘEDNÁŠEJÍCÍ_QUERY_KEY),
    štítky: seznamZParametru(parametry, ŠTÍTKY_QUERY_KEY),
    jenSeZáznamem: parametry.get(ZÁZNAM_QUERY_KEY) === "1",
  };
};

/** zapíše filtr do adresy, ostatní parametry a kotvu nechá být */
export const urlSFiltremArchivu = (url: string, filtr: FiltrArchivu): string => {
  const urlObjekt = new URL(url);
  nastavParametr(urlObjekt, TEXT_QUERY_KEY, filtr.text);
  nastavParametr(urlObjekt, ROKY_QUERY_KEY, filtr.roky.join(","));
  nastavParametr(urlObjekt, PŘEDNÁŠEJÍCÍ_QUERY_KEY, filtr.přednášející.join(","));
  nastavParametr(urlObjekt, ŠTÍTKY_QUERY_KEY, filtr.štítky.join(","));
  nastavParametr(urlObjekt, ZÁZNAM_QUERY_KEY, filtr.jenSeZáznamem ? "1" : "");

  return sČárkami(urlObjekt);
};

/** co v archivu není (smazaný přednášející, překlep v odkazu), by v liště nešlo vypnout */
export const filtrJenSeZnámýmiHodnotami = (filtr: FiltrArchivu, data: ApiArchivPrednasek): FiltrArchivu => ({
  ...filtr,
  roky: filtr.roky.filter((rok) => data.roky.includes(rok)),
  přednášející: filtr.přednášející.filter((id) => data.prednasejici.some((známý) => známý.id === id)),
  štítky: filtr.štítky.filter((klíč) => data.stitky.some((známý) => známý.klic === klíč)),
});

/** přidá přednášejícího do filtru (klik na jméno na kartě), už vybraného nechá */
export const sPřednášejícím = (filtr: FiltrArchivu, id: number): FiltrArchivu =>
  filtr.přednášející.includes(id)
    ? filtr
    : { ...filtr, přednášející: [...filtr.přednášející, id] };

/** 1 přednáška, 2 přednášky, 5 přednášek */
export const počtemPřednášek = (počet: number): string => {
  if (počet === 1) return "1 přednáška";
  if (počet >= 2 && počet <= 4) return `${počet} přednášky`;

  return `${počet} přednášek`;
};
