import { ProgramStateCreator } from "..";
import { Aktivita, AktivitaPřihlášen, fetchAktivity, fetchAktivityPřihlášen } from "../../../api/program";

// TODO: navrhnout a implementovat politiku updatů programu pro cache
// TODO: poupravit tvar dat rozdělit na data a indexaci (PodleRoku bude obsahovat jen id aktivity a ne celý objekt ...)


export type ProgramDataSlice = {
  data: {
    aktivityPodleRoku: {
      [rok: number]: Aktivita[],
    },
    aktivityPřihlášenPodleRoku: {
      [rok: number]: AktivitaPřihlášen[],
    },
    aktivityPodleId: {
      [id: number]: Aktivita,
    },
    aktivityPřihlášenPodleId: {
      [id: number]: AktivitaPřihlášen,
    },
  }
  /** Pokud ještě není dotažený tak dotáhne rok, příhlášen se dotahuje vždy */
  načtiRok(rok: number, načtiZnova?: boolean): Promise<void>;
}

export const createProgramDataSlice: ProgramStateCreator<ProgramDataSlice> = (set, get) => ({
  data: {
    aktivityPodleRoku: {},
    aktivityPřihlášenPodleRoku: {},
    aktivityPodleId: {},
    aktivityPřihlášenPodleId: {},
  },
  async načtiRok(rok: number, načtiZnova = false) {
    const aktivityPřihlášen = await fetchAktivityPřihlášen(rok);

    set(s => {
      s.data.aktivityPřihlášenPodleRoku[rok] = aktivityPřihlášen;
      for (const aktivita of aktivityPřihlášen) {
        s.data.aktivityPřihlášenPodleId[aktivita.id] = aktivita;
      }
    }, undefined, "dotažení přihlášen-aktivity");

    const dotaženo = !!get().data.aktivityPodleRoku[rok];
    if (dotaženo && !načtiZnova) return;

    const aktivity = await fetchAktivity(rok);
    set(s => {
      s.data.aktivityPodleRoku[rok] = aktivity;
      for (const aktivita of aktivity) {
        s.data.aktivityPodleId[aktivita.id] = aktivita;
      }
    }, undefined, "dotažení aktivit");
  },
});
