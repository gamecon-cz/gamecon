import { ProgramStateCreator, useProgramStore } from "..";
import { ApiAktivitaAkce, ApiAktivitaNepřihlášen, ApiAktivitaObsazenost, ApiAktivitaPopis, ApiAktivitaUživatel, ApiŠtítek, fetchAktivitaAkce, fetchManifestFresh, fetchRocnikAktivity, fetchStaticProgramData, fetchUserData, fetchŠtítky, Obsazenost } from "../../../api/program";
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

/** Build Map for O(1) lookups instead of O(n) .find() */
function buildPopisyMap(popisy: ApiAktivitaPopis[]): Map<string, string> {
  const map = new Map<string, string>();
  for (const p of popisy) {
    map.set(p.id, p.popis);
  }
  return map;
}

function buildObsazenostiMap(obsazenosti: ApiAktivitaObsazenost[]): Map<number, Obsazenost> {
  const map = new Map<number, Obsazenost>();
  for (const o of obsazenosti) {
    map.set(o.idAktivity, o.obsazenost);
  }
  return map;
}

function buildUzivatelMap(uzivatelData: ApiAktivitaUživatel[]): Map<number, ApiAktivitaUživatel> {
  const map = new Map<number, ApiAktivitaUživatel>();
  for (const u of uzivatelData) {
    map.set(u.id, u);
  }
  return map;
}

const useStaticFiles = () => GAMECON_KONSTANTY.programManifest !== null || GAMECON_KONSTANTY.URL_PROGRAM_CACHE !== "/cache/public/program";

export const načtiRok = async (ročník: number) => {
  const nastavStav = nastavStavProRok.bind(undefined, ročník);

  try {
    nastavStav("načítání");

    if (useStaticFiles()) {
      // New path: static files + user API
      const [staticData, userData] = await Promise.all([
        fetchStaticProgramData(ročník),
        fetchUserData(ročník),
      ]);

      nastavStav("dotaženo");

      const popisyMap = buildPopisyMap(staticData.popisy);
      const obsazenostiMap = buildObsazenostiMap(staticData.obsazenosti);
      const uzivatelMap = userData.data
        ? buildUzivatelMap(userData.data.aktivityUzivatel)
        : new Map<number, ApiAktivitaUživatel>();
      const skryteAktivity = userData.data?.aktivitySkryte ?? [];

      useProgramStore.setState(s => {
        s.data.podleRočníku[ročník] = {
          aktivityPodleId: {},
        };
        const ročníkData = s.data.podleRočníku[ročník];

        // Process publicly visible activities from static files
        for (const aktivita of staticData.aktivity) {
          const popis = popisyMap.get(aktivita.popisId) ?? "";
          const obsazenost = obsazenostiMap.get(aktivita.id)
            ?? vytvořObsazenostPrázdnéSUpozorněním(aktivita.id);
          const aktivitaUživatel = uzivatelMap.get(aktivita.id);
          ročníkData.aktivityPodleId[aktivita.id] = {
            ...aktivita,
            ...aktivitaUživatel,
            popis,
            obsazenost,
          } as Aktivita;
        }

        // Process hidden activities visible only to this user
        for (const aktivita of skryteAktivity) {
          const popis = popisyMap.get(aktivita.popisId) ?? "";
          const obsazenost = obsazenostiMap.get(aktivita.id)
            ?? vytvořObsazenostPrázdnéSUpozorněním(aktivita.id);
          const aktivitaUživatel = uzivatelMap.get(aktivita.id);
          ročníkData.aktivityPodleId[aktivita.id] = {
            ...aktivita,
            ...aktivitaUživatel,
            popis,
            obsazenost,
          } as Aktivita;
        }
      }, undefined, "dotažení aktivit");
    } else {
      // Legacy path: old monolithic API
      const rocnikData = await fetchRocnikAktivity(ročník);
      nastavStav("dotaženo");

      const popisyMap = buildPopisyMap(rocnikData.popisy.data);
      const obsazenostiMap = buildObsazenostiMap(rocnikData.obsazenosti.data);
      const uzivatelMap = buildUzivatelMap(rocnikData.aktivityUživatel.data);

      useProgramStore.setState(s => {
        s.data.podleRočníku[ročník] = {
          aktivityPodleId: {},
        };
        const ročníkData = s.data.podleRočníku[ročník];
        for (const aktivita of rocnikData.aktivityNeprihlasen.data.concat(rocnikData.aktivitySkryte.data)) {
          const popis = popisyMap.get(aktivita.popisId) ?? "";
          const aktivitaUživatel = uzivatelMap.get(aktivita.id)!;
          const obsazenost = obsazenostiMap.get(aktivita.id)
            ?? vytvořObsazenostPrázdnéSUpozorněním(aktivita.id);
          ročníkData.aktivityPodleId[aktivita.id] = {...aktivita, ...aktivitaUživatel, popis, obsazenost};
        }
      }, undefined, "dotažení aktivit");
    }
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
    const response = await fetchAktivitaAkce(aktivitaId, typ);

    if (response.chyba?.hláška){
      nastavStavAkce("chyba");
      nastavChyba(response.chyba.hláška);
    } else {
      nastavStavAkce("dotaženo");
    }

    // Apply immediate update from enriched response
    if (response.obsazenost || response.aktivitaUzivatel) {
      useProgramStore.setState(s => {
        const ročník = GAMECON_KONSTANTY.ROCNIK;
        const ročníkData = s.data.podleRočníku[ročník];
        if (!ročníkData) return;

        if (response.obsazenost) {
          const aktivita = ročníkData.aktivityPodleId[response.obsazenost.idAktivity];
          if (aktivita) {
            aktivita.obsazenost = response.obsazenost.obsazenost;
          }
        }

        if (response.aktivitaUzivatel) {
          const aktivita = ročníkData.aktivityPodleId[response.aktivitaUzivatel.id];
          if (aktivita) {
            if (response.aktivitaUzivatel.stavPrihlaseni !== undefined) {
              aktivita.stavPrihlaseni = response.aktivitaUzivatel.stavPrihlaseni;
            }
            if (response.aktivitaUzivatel.slevaNasobic !== undefined) {
              aktivita.slevaNasobic = response.aktivitaUzivatel.slevaNasobic;
            }
            if (response.aktivitaUzivatel.zamcenaDo !== undefined) {
              aktivita.zamcenaDo = response.aktivitaUzivatel.zamcenaDo;
            }
            if (response.aktivitaUzivatel.zamcenaMnou !== undefined) {
              aktivita.zamcenaMnou = response.aktivitaUzivatel.zamcenaMnou;
            }
          }
        }
      }, undefined, "okamžitá aktualizace z akce");
    }

    // Delayed re-fetch to pick up regenerated static files + user data changes
    if (useStaticFiles()) {
      setTimeout(async () => {
        try {
          const rok = GAMECON_KONSTANTY.ROCNIK;
          const [manifest, userData] = await Promise.all([
            fetchManifestFresh(rok),
            fetchUserData(rok),
          ]);

          // Re-fetch obsazenosti if manifest changed
          const currentManifest = GAMECON_KONSTANTY.programManifest;
          if (!currentManifest || manifest.obsazenosti !== currentManifest.obsazenosti) {
            const url = `${GAMECON_KONSTANTY.URL_PROGRAM_CACHE}/${manifest.obsazenosti}`;
            const obsazenosti: ApiAktivitaObsazenost[] = await fetch(url).then(r => r.json());
            const obsazenostiMap = buildObsazenostiMap(obsazenosti);

            useProgramStore.setState(s => {
              const ročníkData = s.data.podleRočníku[rok];
              if (!ročníkData) return;
              for (const [id, obsazenost] of obsazenostiMap) {
                const aktivita = ročníkData.aktivityPodleId[id];
                if (aktivita) {
                  aktivita.obsazenost = obsazenost;
                }
              }
            }, undefined, "aktualizace obsazeností ze statických souborů");

            // Update stored manifest reference
            GAMECON_KONSTANTY.programManifest = manifest;
          }

          // Apply user data updates
          if (userData.data) {
            const uzivatelMap = buildUzivatelMap(userData.data.aktivityUzivatel);
            useProgramStore.setState(s => {
              const ročníkData = s.data.podleRočníku[rok];
              if (!ročníkData) return;
              for (const [id, uzivatel] of uzivatelMap) {
                const aktivita = ročníkData.aktivityPodleId[id];
                if (aktivita) {
                  Object.assign(aktivita, uzivatel);
                }
              }
            }, undefined, "aktualizace uživatelských dat po akci");
          }
        } catch (e) {
          console.warn("Nepodařilo se aktualizovat data po akci:", e);
        }
      }, 2500);
    } else {
      // Legacy: full re-fetch
      await načtiRok(GAMECON_KONSTANTY.ROCNIK);
    }
  } catch (e) {
    console.error(e);
  }
};
