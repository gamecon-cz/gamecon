import { GAMECON_KONSTANTY } from "../../env";


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
  popisId: string,
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
  /** přihlašovatelná na základě stavu (ne kapacity) */
  prihlasovatelna?: boolean,
}

/**
 * Pro jednodušší práci musí být všechny parametry optional
 */
export type ApiAktivitaUživatel = {
  id: number,
  /** V jakém stavu je pokud je přihlášen */
  stavPrihlaseni?: StavPřihlášení,
  /** uživatelská vlastnost */
  slevaNasobic?: number,
  /** orgovská vlastnost */
  mistnost?: string,
  vedu?: boolean,
  /** pokud je aktivita zamčená, tak do kdy */
  zamcenaDo?: number,
  /** aktivita zamčená přihlášeným užviatelem */
  zamcenaMnou?: boolean,
  /** není skutečná vlastnost. tohle vynucuje že kde má byt ApiAKtivitaUživatel, tak se minimálně alespoň pokusí aby tam bylo */
  __TS_STRUKTURALNI_KONTROLA__: true,
}

export type ApiAktivita = ApiAktivitaNepřihlášen & ApiAktivitaUživatel;

export type ApiAktivitaPopis = {
  /** id popisu */
  id: string;
  popis: string;
};

export type ApiAktivitaObsazenost = {
  idAktivity: number;
  obsazenost: Obsazenost,
};

export type ApiTag = {
  id: number,
  nazev: string,
  nazevKategorie: string,
};

// --- Static file manifest ---

export type ProgramManifest = {
  aktivity: string,
  popisy: string,
  obsazenosti: string,
  tagy: string,
};

// --- Static file fetching ---

export type StaticProgramData = {
  aktivity: ApiAktivitaNepřihlášen[],
  popisy: ApiAktivitaPopis[],
  obsazenosti: ApiAktivitaObsazenost[],
  tagy: ApiTag[],
};

async function fetchManifest(rok: number): Promise<ProgramManifest> {
  // Cache-bust manifest requests — manifest has no content hash in filename
  const url = `${GAMECON_KONSTANTY.URL_PROGRAM_CACHE}/manifest-${rok}.json?t=${Date.now()}`;
  return fetch(url).then(r => r.json());
}

async function fetchJsonFile<T>(filename: string): Promise<T> {
  const url = `${GAMECON_KONSTANTY.URL_PROGRAM_CACHE}/${filename}`;
  return fetch(url).then(r => r.json());
}

export const fetchStaticProgramData = async (rok: number): Promise<StaticProgramData> => {
  const manifest: ProgramManifest = GAMECON_KONSTANTY.programManifest
    ?? await fetchManifest(rok);

  const [aktivity, popisy, obsazenosti, tagy] = await Promise.all([
    fetchJsonFile<ApiAktivitaNepřihlášen[]>(manifest.aktivity),
    fetchJsonFile<ApiAktivitaPopis[]>(manifest.popisy),
    fetchJsonFile<ApiAktivitaObsazenost[]>(manifest.obsazenosti),
    fetchJsonFile<ApiTag[]>(manifest.tagy),
  ]);

  return { aktivity, popisy, obsazenosti, tagy };
};

export const fetchManifestFresh = async (rok: number): Promise<ProgramManifest> => {
  return fetchManifest(rok);
};

// --- User data API ---

export type UserDataResponse = {
  hash: string,
  data?: {
    aktivityUzivatel: ApiAktivitaUživatel[],
    aktivitySkryte: ApiAktivitaNepřihlášen[],
  },
};

let lastUserDataHash = '';

export const fetchUserData = async (rok: number): Promise<UserDataResponse> => {
  const hashParam = lastUserDataHash ? `&hash=${encodeURIComponent(lastUserDataHash)}` : '';
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityUzivatel?rok=${rok}${hashParam}`;
  const response: UserDataResponse = await fetch(url).then(r => r.json());

  if (response.hash) {
    lastUserDataHash = response.hash;
  }

  return response;
};

export type ApiAktivitaAkce =
  | "prihlasit"
  | "odhlasit"
  | "prihlasSledujiciho"
  | "odhlasSledujiciho";

type ApiAktivitaAkceResponse = {
  úspěch: boolean,
  chyba?: {hláška:string},
  obsazenost?: ApiAktivitaObsazenost,
  aktivitaUzivatel?: ApiAktivitaUživatel,
}

export const fetchAktivitaAkce = async (aktivitaId: number, typ: ApiAktivitaAkce): Promise<ApiAktivitaAkceResponse> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivitaAkce`;
  const formdata = new FormData();
  formdata.set(typ, aktivitaId.toString(10));
  return fetch(url, {method: "POST", body: formdata}).then(async x => x.json());
};
