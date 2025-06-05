import { ProgramStateCreator, useProgramStore } from "..";
import { ApiAktivitaAkce, ApiAktivitaNepřihlášen, ApiAktivitaUživatel, ApiŠtítek, fetchAktivitaAkce, fetchRocnikAktivity, fetchŠtítky, Obsazenost } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { nastavChyba } from "./všeobecnéSlice";

export type DataApiStav = "načítání" | "dotaženo" | "chyba";

// todo: tyhle transofrmace toho co jde z api by se měli asi dít dřív
export type Aktivita = Omit<ApiAktivitaNepřihlášen & ApiAktivitaUživatel, "popisId"> & {
  popis: string;
  obsazenost: Obsazenost;
};

export type ProgramDataSlice = {
  data: {
    podleRočníku: {
      [ročník: number]: {
        aktivityPodleId: { [id: number]: Aktivita },
      }
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
    podleRočníku: {},
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

const vytvořObsazenostPrázdnéSUpozorněním = (aktivitaId: number):Obsazenost =>{
  console.warn(`pro aktivitu ${aktivitaId} nebyla nalezena obsazenost`);
  return {
    f: 0,
    kf:0,
    km:0,
    ku:0,
    m:0,
  };
}

export const načtiRok = async (ročník: number) => {
  const nastavStav = nastavStavProRok.bind(undefined, ročník);

  try {
    nastavStav("načítání");
    const rocnikData = await fetchRocnikAktivity(ročník);
    nastavStav("dotaženo");

    useProgramStore.setState(s => {
      s.data.podleRočníku[ročník] = {
        aktivityPodleId: {},
      };
      const ročníkData = s.data.podleRočníku[ročník];
      for (const aktivita of rocnikData.aktivityNeprihlasen.data.concat(rocnikData.aktivitySkryte.data)) {
        const popis = rocnikData.popisy.data.find(x=>x.id === aktivita.popisId)?.popis ?? "";
        const aktivitaUživatel = rocnikData.aktivityUživatel.data.find(x=>x.id === aktivita.id)!;
        const obsazenost = rocnikData.obsazenosti.data.find(x=>x.idAktivity === aktivita.id)?.obsazenost
          ?? vytvořObsazenostPrázdnéSUpozorněním(aktivita.id);
        ročníkData.aktivityPodleId[aktivita.id] = {...aktivita, ...aktivitaUživatel, popis, obsazenost};
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
