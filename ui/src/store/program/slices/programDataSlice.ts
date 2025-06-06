import { ProgramStateCreator, useProgramStore } from "..";
import { ApiAktivita, ApiAktivitaAkce, ApiŠtítek, fetchAktivitaAkce, fetchAktivity, fetchŠtítky } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { nastavChyba } from "./všeobecnéSlice";

export type DataApiStav = "načítání" | "dotaženo" | "chyba";

export type ProgramDataSlice = {
  data: {
    aktivityPodleId: {
      [id: number]: ApiAktivita
    },
    štítky: ApiŠtítek[],
  },
  dataStatus: {
    podleRoku: {
      [rok: number]: DataApiStav
    },
    akce?: DataApiStav
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

const nastavStavProRok = (rok: number, stav: DataApiStav) => {
  useProgramStore.setState(s=>{
    s.dataStatus.podleRoku[rok] = stav;
  }, undefined, "Natavení api stavu pro rok");
};

export const načtiRok = async (rok: number) => {
  const nastavStav = nastavStavProRok.bind(undefined, rok);

  try {
    nastavStav("načítání");
    const aktivity = await fetchAktivity(rok);
    nastavStav("dotaženo");

    useProgramStore.setState(s => {
      for (const aktivita of aktivity) {
        s.data.aktivityPodleId[aktivita.id] = aktivita;
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

const nastavStavAkce = (stav: DataApiStav) => {
  useProgramStore.setState(s=>{
    s.dataStatus.akce = stav;
  }, undefined, "Natavení api stavu pro akci");
};

export const useStavAkce = () => useProgramStore(s=>s.dataStatus.akce);

export const proveďAkciAktivity = async (aktivitaId: number, typ: ApiAktivitaAkce) => {
  try {
    nastavStavAkce("načítání");
    const { chyba } = await fetchAktivitaAkce(aktivitaId, typ);

    if (chyba?.hláška){
      nastavStavAkce("chyba");
      nastavChyba(chyba.hláška);
    } else {
      nastavStavAkce("dotaženo");
    }

    await načtiRok(GAMECON_KONSTANTY.ROCNIK);
  } catch (e) {
    console.error(e);
  }
};
