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

/**
 * Data nezávislé na tom jestli je uživatel přihlášený
 */
export type ApiAktivitaNepřihlášen = {
  id: number,
  nazev: string,
  kratkyPopis: string,
  popis: string,
  obrazek: string,
  vypraveci: string[],
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
export type ApiAktivitaPřihlášen = {
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

export type ApiAktivita = ApiAktivitaNepřihlášen & ApiAktivitaPřihlášen;

export type ApiŠtítek = {
  id: number,
  nazev: string,
  nazevKategorie: string,
  // nazevHlavniKategorie: string,
  // idKategorieTagu: string,
  // poznamka: string,
};

export const fetchAktivity = async (rok: number): Promise<ApiAktivita[]> => {
  if (GAMECON_KONSTANTY.IS_DEV_SERVER) {
    return fetchTestovacíAktivity(rok);
  }
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityProgram?${rok ? `rok=${rok}` : ""}`;
  return fetch(url, { method: "POST" }).then(async x => x.json());
};

export const fetchŠtítky = async (): Promise<ApiŠtítek[]> =>{
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}stitky`;
  return fetch(url, { method: "GET" }).then(async x => x.json());
};

export type ApiAktivitaAkce =
  | "prihlasit"
  | "odhlasit"
  | "prihlasSledujiciho"
  | "odhlasSledujiciho";

type ApiAktivitaAkceResponse = {
  úspěch: boolean,
  chyba?: {hláška:string},
}

export const fetchAktivitaAkce = async (aktivitaId: number, typ: ApiAktivitaAkce): Promise<ApiAktivitaAkceResponse> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivitaAkce`;
  const formdata = new FormData();
  formdata.set(typ, aktivitaId.toString(10));
  return fetch(url, {method: "POST", body: formdata}).then(async x => x.json());
};
