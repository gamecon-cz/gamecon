import { useProgramStore } from ".";
import { GAMECON_KONSTANTY } from "../../env";
import { distinct } from "../../utils";
import { LOCAL_STORAGE_KLÍČE } from "../localStorageKlíče";
import { urlStavProgramTabulkaMožnostíDnyMůj } from "./logic/url";
import { filtrujDotaženéAktivity, načtiRok } from "./slices/programDataSlice";
import { nastavStateZUrl, nastavUrlZState } from "./slices/urlSlice";
import { nastavFiltryOtevřené } from "./slices/všeobecnéSlice";

const indexŘazeníLinie = (klíč: string) => {
  const index = GAMECON_KONSTANTY.PROGRAM_ŘAZENÍ_LINIE.findIndex(
    (x) => x === klíč
  );

  return index !== -1 ? index : 1000;
};

// TODO: logiku pro autofetch na začátek první vlny
// TODO: nějak vizuálně komunikovat že stránka je/byla načtena
// TODO: logiku rozházet ke slicům

export const inicializujProgramStore = () => {
  // Načtu do stavu url
  nastavStateZUrl();
  // Normalizuju url podle stavu
  nastavUrlZState(true);

  useProgramStore.subscribe(s => s.urlStav, () => {
    nastavUrlZState();
  });

  addEventListener("popstate", () => {
    nastavStateZUrl();
  });

  useProgramStore.subscribe(s => !!s.přihlášenýUživatel.data.prihlasen, (přihlášen) => {
    useProgramStore.setState(s => {
      s.urlStavMožnosti.dny = urlStavProgramTabulkaMožnostíDnyMůj({ přihlášen });
    });
  });

  useProgramStore.subscribe(s => s.data, (data) => {
    useProgramStore.setState(s => {
      s.urlStavMožnosti.linie = distinct(filtrujDotaženéAktivity(data.aktivityPodleId).map(x => x.linie))
        .sort((a, b) => indexŘazeníLinie(a) - indexŘazeníLinie(b));
      s.urlStavMožnosti.tagy = distinct(filtrujDotaženéAktivity(data.aktivityPodleId).map(x => x.stitky).flat(1))
        .sort();
    });
  });

  const přihlášenýUživatelPřednačteno = window?.gameconPřednačtení?.přihlášenýUživatel;
  if (přihlášenýUživatelPřednačteno) {
    useProgramStore.setState(s => {
      s.přihlášenýUživatel.data = přihlášenýUživatelPřednačteno;
      console.log(přihlášenýUživatelPřednačteno);
    });
  }

  const dataProgramString = localStorage.getItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM);
  if (dataProgramString) {
    try {
      useProgramStore.setState(s => {
        s.data = JSON.parse(dataProgramString);
      }, undefined, "načtení uložených dat");
    } catch (e) {
      console.warn("nepodařilo se načíst data z local storage");
    }
  }

  useProgramStore.subscribe(s => s.data, (data) => {
    localStorage.setItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM, JSON.stringify(data));
  });

  const urlStav = useProgramStore.getState().urlStav;
  void načtiRok(urlStav.ročník);

  // ať máme vždy přednačtený aktuální ročník
  if (urlStav.ročník !== GAMECON_KONSTANTY.ROCNIK) {
    setTimeout(() => {
      void načtiRok(GAMECON_KONSTANTY.ROCNIK);
    }, 2000);
  }

  useProgramStore.subscribe(s => s.urlStav.ročník, (rok) => {
    void načtiRok(rok);
  });

  if (
    urlStav.ročník !== GAMECON_KONSTANTY.ROCNIK
    || urlStav.filtrLinie?.length
    || urlStav.filtrTagy?.length
    || urlStav.filtrPřihlašovatelné
  ) {
    nastavFiltryOtevřené(true);
  }
};
