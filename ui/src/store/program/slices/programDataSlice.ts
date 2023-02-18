import { ProgramStateCreator } from "..";
import { APIAktivita, APIAktivitaPřihlášen, fetchAktivity, fetchAktivityPřihlášen } from "../../../api/program";

export type ProgramDataSlice = {
  data: {
    aktivityPodleRoku: {
      [rok: number]: APIAktivita[],
    },
    aktivityPřihlášenPodleRoku: {
      [rok: number]: APIAktivitaPřihlášen[],
    },
    aktivityPodleId: {
      [id: number]: APIAktivita,
    },
    aktivityPřihlášenPodleId: {
      [id: number]: APIAktivitaPřihlášen,
    },
  },
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
