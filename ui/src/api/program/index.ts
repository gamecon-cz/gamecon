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
  turnajId?: number;
  turnajKolo?: number;
  vBudoucnu?: boolean,
  vdalsiVlne?: boolean,
  probehnuta?: boolean,
  jeBrigadnicka?: boolean,
  tymova?: boolean,
  /** přihlašovatelná na základě stavu (ne kapacity) */
  prihlasovatelna: boolean,
}

export type ApiLokace = {
  id: number,
  poradi: number,
  nazev: string,
};

/**
 * Všechna pole jsou povinná. Backend MUSÍ vždy poslat všechna pole.
 * Pokud hodnota sémanticky "chybí" (uživatel není přihlášen, aktivita
 * není zamčená, …), backend pošle `null`.
 */
export type ApiAktivitaUživatel = {
  id: number,
  /** V jakém stavu je pokud je přihlášen */
  stavPrihlaseni?: StavPřihlášení,
  /** uživatelská vlastnost */
  slevaNasobic?: number,
  // nahradnik?: boolean,
  /** orgovská vlastnost */
  // todo: tady lepší asi posílat jen id a posílat místnosti zvlášť pro případ že chceme zobrazit prázdné řádky místnostem
  mistnosti?: ApiLokace[],
  // todo: tohle je taky možný stav přihlášení (odebrat tady a přidat do stavPrihlaseni)
  vedu?: boolean,
  // todo(tym): členové týmu (pak není potřeba tymPocetClenu)
  // todo(tym): kapitán týmu
  // todo(tym): název týmu
  // todo(tym): navazujici aktivity ať se může vypsat v modalu
  /** počet členů týmu, ve kterém je uživatel přihlášen */
  tymPocetClenu?: number,
  /** limit členů týmu */
  tymLimit?: number | null,
  /** není skutečná vlastnost. tohle vynucuje že kde má byt ApiAKtivitaUživatel, tak se minimálně alespoň pokusím aby tam bylo */
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
  const response = await fetch(url);
  if (response.ok) {
    return response.json();
  }

  if (response.status === 404) {
    // Manifest doesn't exist yet — ask PHP to regenerate it
    const apiUrl = `${GAMECON_KONSTANTY.BASE_PATH_API}programManifest?rok=${rok}`;
    const apiResponse = await fetch(apiUrl);
    if (!apiResponse.ok) {
      throw new Error(`Nepodařilo se vygenerovat manifest pro rok ${rok} (HTTP ${apiResponse.status}).`);
    }
    return apiResponse.json();
  }

  throw new Error(`Manifest pro rok ${rok} není dostupný (HTTP ${response.status}). Zkuste stránku načíst znovu.`);
}

async function fetchJsonFile<T>(filename: string): Promise<T> {
  const url = `${GAMECON_KONSTANTY.URL_PROGRAM_CACHE}/${filename}`;
  const response = await fetch(url);
  if (!response.ok) {
    throw new Error(`Nepodařilo se načíst ${filename} (HTTP ${response.status}). URL: ${url}`);
  }
  return response.json();
}

export const fetchStaticProgramData = async (rok: number): Promise<StaticProgramData> => {
  const manifest: ProgramManifest = rok === GAMECON_KONSTANTY.ROCNIK
    ? (GAMECON_KONSTANTY.programManifest ?? await fetchManifest(rok))
    : await fetchManifest(rok);

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

export const fetchAktivitaAkce = async (aktivitaId: number, typ: ApiAktivitaAkce, idTýmu?: number, kódTýmu?: number): Promise<ApiAktivitaAkceResponse> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivitaAkce`;
  const formdata = new FormData();
  formdata.set(typ, aktivitaId.toString(10));
  if (idTýmu)
    formdata.set("tymId", idTýmu.toString(10))
  else if (kódTýmu)
    formdata.set("tymKod", kódTýmu.toString(10))
  return fetch(url, {method: "POST", body: formdata}).then(async x => x.json());
};

export type ClenTymu = {
  id: number,
  jmeno: string,
  jeKapitan: boolean,
};

export type ApiTymVSeznamu = {
  id: number,
  nazev: string | null,
  pocetClenu: number,
  limit: number | null | undefined,
  verejny: boolean,
};

export type AktivitaTymFazeRozpraovni = "vyberKola" | "prihlaseniKapitana";
export type ApiAktivitaTym = {
  id: number,
  idTurnajeNeboAktivity: number,
  nazev?: string,
  kod?: number,
  verejny?: boolean,
  idKapitana?: number,
  casExpiraceMs?: number,
  limitTymu?: number | null,
  zamceny?: boolean,
  smazatPoExpiraci?: boolean,
  minKapacita?: number | null,
  maxKapacita?: number | null,
  clenove?: ClenTymu[],
  aktivityTymuId?: number[],
  casSmazaniRozpracovanyMs?: number,
  rozpracovanyFaze?: AktivitaTymFazeRozpraovni,
}

export type AktivitaTymResponse = {
  tym?: ApiAktivitaTym;
  vsechnyTymy?: ApiTymVSeznamu[],
  idTurnajeNeboAktivity: number,
};

export const fetchAktivitaTým = async (aktivitaId: number, uživatelId = 0): Promise<AktivitaTymResponse> => {
  const urlUživParam = uživatelId ? `&uzivatelId=${uživatelId}` : "";
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivitaTym?aktivitaId=${aktivitaId}${urlUživParam}`;
  return fetch(url, {method: "GET"}).then(async x => x.json());
};

export type AkceTymu =
  | {typ: "zalozPrazdnyTym", aktivitaId: number}
  | {typ: "nastavVerejnost", idTymu: number, verejny: boolean}
  | {typ: "pregenerujKod", idTymu: number}
  | {typ: "odhlasClena", idTymu: number, aktivitaId: number, idClena: number}
  | {typ: "nastavLimit", idTymu: number, limit: number}
  | {typ: "predejKapitana", idTymu: number, idNovehoKapitana: number}
  | {typ: "zamkni", idTymu: number}
  | {typ: "odemkni", idTymu: number}
  | {typ: "smazTym", idTymu: number}
  | {typ: "potvrdVyberAktivit", idTymu: number, aktivitaId: number, idVybranychAktivit: number[]}
  | {typ: "prihlasKapitana", idTymu: number, aktivitaId: number}
  ;

type DistributiveOmit<T, K extends PropertyKey> = T extends unknown ? Omit<T, K> : never;
export type AkceTymuBezKontextu = DistributiveOmit<AkceTymu, "idTymu" | "aktivitaId">;

export const fetchAktivitaTymAkce = async (akce: AkceTymu): Promise<{úspěch: boolean, novyKod?: number, chyba?: {hláška: string}}> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivitaTym`;
  const formdata = new FormData();
  formdata.set("akce", akce.typ);
  if ("idTymu" in akce) formdata.set("idTymu", akce.idTymu.toString(10));
  if ("aktivitaId" in akce) formdata.set("aktivitaId", akce.aktivitaId.toString(10));
  if (akce.typ === "nastavVerejnost") formdata.set("verejny", akce.verejny ? "1" : "0");
  if (akce.typ === "odhlasClena") formdata.set("idClena", akce.idClena.toString(10));
  if (akce.typ === "nastavLimit") formdata.set("limit", akce.limit.toString(10));
  if (akce.typ === "predejKapitana") formdata.set("idNovehoKapitana", akce.idNovehoKapitana.toString(10));
  if (akce.typ === "potvrdVyberAktivit") akce.idVybranychAktivit.forEach(id => formdata.append("idVybranychAktivit[]", id.toString(10)));
  return fetch(url, {method: "POST", body: formdata}).then(async x => x.json());
};
