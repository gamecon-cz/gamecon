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

export type ApiCachovanaOdpověď<Data, kompletni = true> = {
  hash: string,
} & ( kompletni extends true ? {data: Data,} : {data?:Data});

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

// --- Legacy types (kept for backward compatibility with old API) ---

type ApiAktivityProgramResponse<kompletni = true> = {
  aktivityNeprihlasen: ApiCachovanaOdpověď<ApiAktivitaNepřihlášen[], kompletni>;
  aktivitySkryte: ApiCachovanaOdpověď<ApiAktivitaNepřihlášen[], kompletni>;
  aktivityUživatel: ApiCachovanaOdpověď<ApiAktivitaUživatel[], kompletni>;
  popisy: ApiCachovanaOdpověď<ApiAktivitaPopis[], kompletni>;
  obsazenosti: ApiCachovanaOdpověď<ApiAktivitaObsazenost[], kompletni>;
};

type ApiAktivityProgramResponseHashe = {
  aktivityNeprihlasen: string,
  aktivitySkryte: string,
  aktivityUživatel: string,
  popisy: string,
  obsazenosti: string,
}

export type ApiŠtítek = {
  id: number,
  nazev: string,
  nazevKategorie: string,
};

// --- Static file manifest ---

export type ProgramManifest = {
  aktivity: string,
  popisy: string,
  obsazenosti: string,
};

// --- Static file fetching ---

export type StaticProgramData = {
  aktivity: ApiAktivitaNepřihlášen[],
  popisy: ApiAktivitaPopis[],
  obsazenosti: ApiAktivitaObsazenost[],
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

  const [aktivity, popisy, obsazenosti] = await Promise.all([
    fetchJsonFile<ApiAktivitaNepřihlášen[]>(manifest.aktivity),
    fetchJsonFile<ApiAktivitaPopis[]>(manifest.popisy),
    fetchJsonFile<ApiAktivitaObsazenost[]>(manifest.obsazenosti),
  ]);

  return { aktivity, popisy, obsazenosti };
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
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityUzivatel?rok=${rok}`;
  const body = JSON.stringify({ hash: lastUserDataHash });
  const response: UserDataResponse = await fetch(url, {
    method: "POST",
    body,
    headers: { 'Content-Type': 'application/json' },
  }).then(r => r.json());

  if (response.hash) {
    lastUserDataHash = response.hash;
  }

  return response;
};

// --- Legacy API (still used as fallback when static files are not available) ---

const vytvořLocalStorageKlíč = (ročník: number) => `_cache_fetchRocnikAktivity_${ročník}`;

const vraťDataZCache = (ročník: number): ApiAktivityProgramResponse | undefined =>{
  const localStorageKlíč = vytvořLocalStorageKlíč(ročník);
  try {
    const cachovanéDataStr = localStorage.getItem(localStorageKlíč);
    if (!cachovanéDataStr) return undefined;
    const cachovanéData = JSON.parse(cachovanéDataStr);
    return cachovanéData;
  }catch(e) {
    console.log("nepodařilo se rozparsovat data z cache");
    return undefined;
  }
}

const zapišCache = (ročník: number, data: ApiAktivityProgramResponse): void =>{
  const localStorageKlíč = vytvořLocalStorageKlíč(ročník);
  try {
    localStorage.setItem(localStorageKlíč, JSON.stringify(data));
  }catch(e) {
    console.log("nepodařilo se zapsat cache");
  }
}

const vytvořNovéDataZCacheADat = <T,>(cache: ApiCachovanaOdpověď<T, false> | undefined, newData: ApiCachovanaOdpověď<T, false>): ApiCachovanaOdpověď<T, true> =>{
  if (!cache || newData.data) return newData as any;
  console.log(`využívám cache ${cache.hash}`)
  return cache as any;
}

const aplikujCacheNaOdpověď = (cacheData :ApiAktivityProgramResponse<true> | undefined, ročník: number, data: ApiAktivityProgramResponse<false>) => {
  const spojenéData: ApiAktivityProgramResponse = {
    aktivityNeprihlasen: vytvořNovéDataZCacheADat(cacheData?.aktivityNeprihlasen, data?.aktivityNeprihlasen),
    aktivitySkryte: vytvořNovéDataZCacheADat(cacheData?.aktivitySkryte, data?.aktivitySkryte),
    aktivityUživatel: vytvořNovéDataZCacheADat(cacheData?.aktivityUživatel, data?.aktivityUživatel),
    popisy: vytvořNovéDataZCacheADat(cacheData?.popisy, data?.popisy),
    obsazenosti: vytvořNovéDataZCacheADat(cacheData?.obsazenosti, data?.obsazenosti),
  };
  zapišCache(ročník, spojenéData);

  return spojenéData;
};

const vraťAktuálníHasheZCache = (cacheData: ApiAktivityProgramResponse<true> | undefined): ApiAktivityProgramResponseHashe | undefined =>{
  if (!cacheData) return undefined;
  return {
    aktivityNeprihlasen: cacheData.aktivityNeprihlasen.hash,
    aktivitySkryte: cacheData.aktivitySkryte.hash,
    aktivityUživatel: cacheData.aktivityUživatel.hash,
    popisy: cacheData.popisy.hash,
    obsazenosti: cacheData.obsazenosti.hash,
  };
}

export const fetchRocnikAktivity = async (ročník: number): Promise<ApiAktivityProgramResponse> => {
  const cacheData = vraťDataZCache(ročník);
  const hashe = vraťAktuálníHasheZCache(cacheData);

  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityProgram?${ročník ? `rok=${ročník}` : ""}`;
  const body = JSON.stringify({ hashe });
  const odpověď: ApiAktivityProgramResponse<false> = await fetch(url, { method: "POST", body,
    headers: {
    'Content-Type': 'application/json'
  }, })
  .then(async x => x.json())
  ;
  const kompletníOdpověď = aplikujCacheNaOdpověď(cacheData, ročník, odpověď);
  return kompletníOdpověď;
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
  obsazenost?: ApiAktivitaObsazenost,
  aktivitaUzivatel?: ApiAktivitaUživatel,
}

export const fetchAktivitaAkce = async (aktivitaId: number, typ: ApiAktivitaAkce): Promise<ApiAktivitaAkceResponse> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivitaAkce`;
  const formdata = new FormData();
  formdata.set(typ, aktivitaId.toString(10));
  return fetch(url, {method: "POST", body: formdata}).then(async x => x.json());
};
