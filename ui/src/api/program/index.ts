import { GAMECON_KONSTANTY } from "../../env";
import { fetchTestovacíAktivity, fetchTestovacíAktivityPřihlášen } from "../../testing/fakeAPI";


export const AktivitaStavyVšechny = [
  "vDalsiVlne",
  "vBudoucnu",
  "plno",
  "prihlasen",
  "nahradnik",
  "organizator",
  "volno",
] as const;

export type AktivitaStav =
  (typeof AktivitaStavyVšechny)[number]
  ;


export type StavPřihlášení =
  | "prihlasen"
  | "prihlasenADorazil"
  | "dorazilJakoNahradnik"
  | "prihlasenAleNedorazil"
  | "pozdeZrusil"
  | "sledujici"
  ;

export type Obsazenost = {
  m: number,
  f: number,
  km: number,
  kf: number,
  ku: number,
}

export type OdDo = {
  od: number,
  do: number,
};

export type APIAktivita = {
  id: number,
  nazev: string,
  kratkyPopis: string,
  popis: string,
  obrazek: string,
  vypraveci: string[],
  // stitky: string[],
  stitkyId: number[],
  cenaZaklad: number,
  casText: string,
  cas: OdDo,
  linie: string,
  vBudoucnu?: boolean,
  vdalsiVlne?: boolean,
  probehnuta?: boolean,
  jeBrigadnicka?: boolean,
  /** idčka */
  dite?: number[],
  tymova?: boolean,
}

/**
 * Pro jednodušší práci musí být všechny parametry optional
 */
export type APIAktivitaPřihlášen = {
  id: number,
  obsazenost?: Obsazenost,
  /** V jakém stavu je pokud je přihlášen */
  stavPrihlaseni?: StavPřihlášení,
  /** uživatelská vlastnost */
  slevaNasobic?: number,
  // nahradnik?: boolean,
  /** orgovská vlastnost */
  mistnost?: string,
  /** orgovská vlastnost */
  vedu?: boolean,
  /** pokud je aktivita zamčená, tak do kdy */
  zamcenaDo?: number,
  /** aktivita zamčená přihlášeným užviatelem */
  zamcenaMnou?: boolean,
  prihlasovatelna?: boolean,
}

export type APIŠtítek = {
  id: number,
  nazev: string,
  nazevKategorie: string,
  // nazevHlavniKategorie: string,
  // idKategorieTagu: string,
  // poznamka: string,
};

export const fetchAktivity = async (rok: number): Promise<APIAktivita[]> => {
  if (GAMECON_KONSTANTY.IS_DEV_SERVER) {
    return fetchTestovacíAktivity(rok);
  }
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityProgram?${rok ? `rok=${rok}` : ""}`;
  return fetch(url, { method: "POST" }).then(async x => x.json());
};


export const fetchŠtítky = async (): Promise<APIŠtítek[]> =>{
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}stitky`;
  return fetch(url, { method: "GET" }).then(async x => x.json());
};
