import { ProgramStateCreator, useProgramStore } from "..";
import { APIAktivita, APIAktivitaPřihlášen, fetchAktivity, fetchAktivityPřihlášen } from "../../../api/program";

export type ProgramDataSlice = {
  data: {
    aktivityPodleId: {
      [id: number]: APIAktivita,
    },
    aktivityPřihlášenPodleId: {
      [id: number]: APIAktivitaPřihlášen,
    },
  },
}

export const createProgramDataSlice: ProgramStateCreator<ProgramDataSlice> = () => ({
  data: {
    aktivityPodleId: {},
    aktivityPřihlášenPodleId: {},
  },
});

/** Pokud ještě není dotažený tak dotáhne rok, příhlášen se dotahuje vždy */
export const načtiRok = async (rok: number) => {
  const aktivityPřihlášen = await fetchAktivityPřihlášen(rok);

  useProgramStore.setState(s => {
    for (const aktivita of aktivityPřihlášen) {
      s.data.aktivityPřihlášenPodleId[aktivita.id] = aktivita;
    }
  }, undefined, "dotažení přihlášen-aktivity");

  const aktivity = await fetchAktivity(rok);
  useProgramStore.setState(s => {
    for (const aktivita of aktivity) {
      s.data.aktivityPodleId[aktivita.id] = aktivita;
    }
  }, undefined, "dotažení aktivit");
};
