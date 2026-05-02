import { GAMECON_KONSTANTY } from "../env";


export const GC_STAV_NEPŘIHLÁŠEN = "nepřihlášen";
export const GC_STAV_PŘIHLÁŠEN = "přihlášen";
export const GC_STAV_PŘÍTOMEN = "přítomen";
export const GC_STAV_ODJEL = "odjel";

export const GC_STAV = {
  NEPŘIHLÁŠEN: GC_STAV_NEPŘIHLÁŠEN,
  PŘIHLÁŠEN: GC_STAV_PŘIHLÁŠEN,
  PŘÍTOMEN: GC_STAV_PŘÍTOMEN,
  ODJEL: GC_STAV_ODJEL,
};

type GCStav =
  | typeof GC_STAV_NEPŘIHLÁŠEN
  | typeof GC_STAV_PŘIHLÁŠEN
  | typeof GC_STAV_PŘÍTOMEN
  | typeof GC_STAV_ODJEL
  ;

export type Pohlavi = "m" | "f";

export type ApiUživatel = {
  id: number,
  pohlavi: Pohlavi,
  gcStav: GCStav,

  role?: {
    organizator?: boolean,
    brigadnik?: boolean,
    sefInfa?: boolean,
  }
}

export type ApiPřihlášenýUživatel = {
  ucastnik?: ApiUživatel;
  operator?: ApiUživatel;
}

export const fetchPřihlášenýUživatel = async (): Promise<ApiPřihlášenýUživatel> => {
  const url = `${GAMECON_KONSTANTY.BASE_PATH_API}prihlasenyUzivatel`;
  return fetch(url).then(async x => x.json());
};
