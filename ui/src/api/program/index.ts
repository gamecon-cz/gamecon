import { GAMECON_KONSTANTY } from "../../env";
import { fetchTestovacíAktivity, fetchTestovacíAktivityPřihlášen } from "../../testing/fakeAPI";

export type AktivitaStatus =
  | "vDalsiVlne"
  | "vBudoucnu"
  | "plno"
  | "prihlasen"
  | "nahradnik"
  | "organizator"
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
  stitky: string[],
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
  zamcena?: boolean,
  prihlasovatelna?: boolean,
}

export const fetchAktivity = async (rok: number): Promise<APIAktivita[]> => {
  if (GAMECON_KONSTANTY.IS_DEV_SERVER) {
    return fetchTestovacíAktivity(rok);
  }
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityProgram?${rok ? `rok=${rok}` : ""}`;
  return fetch(url, { method: "POST" }).then(async x => x.json());
};


export const fetchAktivityPřihlášen = async (rok: number): Promise<APIAktivitaPřihlášen[]> => {
  if (GAMECON_KONSTANTY.IS_DEV_SERVER) {
    return fetchTestovacíAktivityPřihlášen(rok);
  }
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityProgramPrihlasen?${rok ? `rok=${rok}` : ""}`;
  return fetch(url, { method: "POST" }).then(async x => x.json());
};

