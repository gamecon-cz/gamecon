import { ProgramStateCreator, useProgramStore } from "..";
import { APIAktivita, ApiAktivitaAkce, APIAktivitaPřihlášen, APIŠtítek, fetchAktivitaAkce, fetchAktivity, fetchŠtítky } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { nastavChyba } from "./všeobecnéSlice";

const DOTAŽENO = "dotaženo";

export type Aktivita = APIAktivita & APIAktivitaPřihlášen & {
  /**
   * kdy byla APIAktivita dotažena
   */
  [DOTAŽENO]: number
};
type AktivitaČást = Aktivita | APIAktivitaPřihlášen;

export const jeAktivitaDotažená = (část: AktivitaČást | undefined): část is Aktivita => {
  return !!část && (DOTAŽENO in část);
};

export const filtrujDotaženéAktivity = (aktivityPodleId: {
  [id: number]: AktivitaČást
}): Aktivita[] => Object.values(aktivityPodleId).filter(jeAktivitaDotažená);

export type DataApiStav = {
  stav: "načítání",
} | {
  stav: "dotaženo",
}| {
  stav: "chyba",
}

export type ProgramDataSlice = {
  data: {
    aktivityPodleId: {
      [id: number]: AktivitaČást
    },
    štítky: APIŠtítek[],
  },
  dataStatus: {
    podleRoku: {
      [rok: number]: DataApiStav
    },
  },
}

export const createProgramDataSlice: ProgramStateCreator<ProgramDataSlice> = () => ({
  data: {
    aktivityPodleId: {},
    štítky: [],
  },
  dataStatus: {
    podleRoku: {},
  }
});

const nastavStavProRok = (rok: number, stavString: DataApiStav["stav"]) => {
  useProgramStore.setState(s=>{
    s.dataStatus.podleRoku[rok] = {stav: stavString};
  }, undefined, "Natavení api stavu pro rok");
}

/** Pokud ještě není dotažený tak dotáhne rok, příhlášen se dotahuje vždy */
export const načtiRok = async (rok: number) => {
  const nastavStav = nastavStavProRok.bind(undefined, rok);

  try {
    nastavStav("načítání");
    const aktivity = await fetchAktivity(rok);
    nastavStav("dotaženo");

    useProgramStore.setState(s => {
      for (const aktivita of aktivity) {
        s.data.aktivityPodleId[aktivita.id] = { ...s.data.aktivityPodleId[aktivita.id], ...aktivita, [DOTAŽENO]: Date.now() };
      }
    }, undefined, "dotažení aktivit");
  } catch(e) {
    nastavStav("chyba");
  }
};

export const načtiŠtítky = async () => {
  const štítky = await fetchŠtítky();

  useProgramStore.setState(s => {
    s.data.štítky = štítky;
  }, undefined, "dotažení štítků");
};

export const proveďAkciAktivity = async (aktivitaId: number, typ: ApiAktivitaAkce) => {
  try {
    const { chyba } = await fetchAktivitaAkce(aktivitaId, typ)

    if (chyba?.hláška)
      nastavChyba(chyba.hláška)

    await načtiRok(GAMECON_KONSTANTY.ROCNIK)
  } catch (e) {
    console.error(e);
  }
}
