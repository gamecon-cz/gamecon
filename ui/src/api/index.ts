import { GAMECON_KONSTANTY } from "../env";

export * from "./fakeApi";

export type ActivityStatus =
  | "vDalsiVlne"
  | "vBudoucnu"
  | "plno"
  | "prihlasen"
  | "nahradnik"
  | "organizator";

export type Obsazenost = {
  m: number,
  f: number,
  km: number,
  kf: number,
  ku: number,
}

export type Aktivita = {
  id: number,
  nazev: string,
  kratkyPopis: string,
  popis: string,
  obrazek: string,
  vypraveci: string[],
  stitky: string[],
  cenaZaklad: number,
  casText: string,
  cas: {
    od: number,
    do: number,
  },
  obsazenost: Obsazenost,
  linie: string,
  vBudoucnu?: boolean,
  vdalsiVlne?: boolean,
  probehnuta?: boolean,
  /** uživatelská vlastnost */
  prihlaseno?: boolean,
  /** uživatelská vlastnost */
  slevaNasobic?: number,
  /** uživatelská vlastnost */
  nahradnik?: boolean,
  /** orgovská vlastnost */
  mistnost?: string,
  /** orgovská vlastnost */
  vedu?: boolean,
}


// TODO: dotahovat zvlášť aktivity a metadata k nim (současně posílá moc velký soubor)

export const fetchAktivity = async (rok: number): Promise<Aktivita[]> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}aktivityProgram?${rok ? `rok=${rok}` : ""}`;
  return fetch(url, { method: "POST" }).then(x => x.json());
}
