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

export type ApiCachovanaOdpověď<Data, kompletni = true> = {
  hash: string,
} & ( kompletni extends true ? {data: Data,} : {data?:Data});

/* todo:
  první kolekce bez uživatele viditelnaPro (pohlídat ať to nemění datasourceColector)
  druhá kolekce s uživatelem
  upravit viditelnaPro ať vrací jen aktivity které jsou viditelné jen pro konkrétního uživatele ALE ne normálně viditelná (bez přihlášení)
 */
// todo: datasourceColector pro viditelnaPro a organizuje a prihlasen
/* todo:
každý sub dotaz bude mít vlastní datasourceColector
pohlídat aby datasource collector obsahoval vždy všechny relevantní data
(např aktivity viditelné pouze pro přihlášeného uživatele budou mít stejný datasourceColector jako )
 */

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
  // nahradnik?: boolean,
  /** orgovská vlastnost */
  mistnost?: string,
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
  //todo: obsazenost rozdělit na kapacitu a obsazenost kapacita se bude posílat s aktivitou základ
  obsazenost: Obsazenost,
};

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
  // nazevHlavniKategorie: string,
  // idKategorieTagu: string,
  // poznamka: string,
};

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

/**
 * spojí cachované data s dotaženými a uloží novou cache
 */
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

export type TymVSeznamu = {
  id: number,
  nazev: string | null,
  pocetClenu: number,
  limit: number | null | undefined,
  verejny: boolean,
};

export type AktivitaKVyberu = {
  id: number,
  nazev: string,
  casText: string,
  cisloKola?: number,
};

export type AktivitaTymResponse = {
  id: number,
  kod: number,
  verejny?: boolean,
  jeKapitan?: boolean,
  casText?: string,
  jeTrebaPredpripravit?: boolean,
  aktivityKPriprave?: AktivitaKVyberu[],
  casZalozeniMs?: number,
  casExpiraceMs?: number,
  limitTymu?: number | null,
  zamceny?: boolean,
  minKapacita?: number | null,
  maxKapacita?: number | null,
  clenove?: ClenTymu[],
  vsechnyTymy?: TymVSeznamu[],
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
