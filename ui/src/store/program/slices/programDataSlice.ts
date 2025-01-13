import { ProgramStateCreator, useProgramStore } from "..";
import { APIAktivita, APIAktivitaPřihlášen, APIŠtítek, fetchAktivity, fetchAktivityPřihlášen, fetchŠtítky } from "../../../api/program";

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

export type ProgramDataSlice = {
  data: {
    aktivityPodleId: {
      [id: number]: AktivitaČást
    },
    štítky: APIŠtítek[],
  },
}

export const createProgramDataSlice: ProgramStateCreator<ProgramDataSlice> = () => ({
  data: {
    aktivityPodleId: {},
    štítky: [],
  },
});

/** Pokud ještě není dotažený tak dotáhne rok, příhlášen se dotahuje vždy */
export const načtiRok = async (rok: number) => {
  const [aktivityPřihlášen, aktivity] =
    await Promise.all([
      fetchAktivityPřihlášen(rok),
      fetchAktivity(rok)
    ]);

  useProgramStore.setState(s => {
    for (const aktivita of aktivity) {
      s.data.aktivityPodleId[aktivita.id] = { ...s.data.aktivityPodleId[aktivita.id], ...aktivita, [DOTAŽENO]: Date.now() };
    }
  }, undefined, "dotažení aktivit");
};

export const načtiŠtítky = async () => {
  const štítky = await fetchŠtítky();

  useProgramStore.setState(s => {
    s.data.štítky = štítky;
  }, undefined, "dotažení štítků");
};
