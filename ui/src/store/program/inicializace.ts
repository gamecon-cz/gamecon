import { useProgramStore } from ".";
import { LOCAL_STORAGE_KLÍČE } from "../localStorageKlíče";
import { tabulkaMožnostíUrlStateProgram } from "./logic/url";
import { načtiRok } from "./slices/programDataSlice";
import { nastavStateZUrl, nastavUrlZState } from "./slices/urlSlice";


// TODO: logiku pro autofetch na začátek první vlny (nějak vizuálně komunikovat že stránka byla načtena)

export const inicializujProgramStore = () => {
  // Načtu do stavu url
  nastavStateZUrl();
  // Normalizuju url podle stavu
  nastavUrlZState(true);

  useProgramStore.subscribe(s => s.urlState, () => {
    nastavUrlZState();
  });

  addEventListener("popstate", () => {
    nastavStateZUrl();
  });

  useProgramStore.subscribe(s => !!s.přihlášenýUživatel.data.prihlasen, (přihlášen) => {
    useProgramStore.setState(s => {
      s.urlStateMožnosti = tabulkaMožnostíUrlStateProgram({ přihlášen });
    });
  });

  const přihlášenýUživatelPřednačteno = window?.gameconPřednačtení?.přihlášenýUživatel;
  if (přihlášenýUživatelPřednačteno) {
    useProgramStore.setState(s=>{
      s.přihlášenýUživatel.data = přihlášenýUživatelPřednačteno;
      console.log(přihlášenýUživatelPřednačteno);
    });
  }

  const dataProgramString = localStorage.getItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM);
  // TODO: vyhodnotit pravidla pro to kdy se může použít cache
  if (false as any && dataProgramString) {
    try {
      useProgramStore.setState(s=>{
        s.data =  JSON.parse(dataProgramString);
      }, undefined, "načtení uložených dat");
    }catch(e) {
      console.warn("nepodařilo se načíst data z local storage");
    }
  }

  useProgramStore.subscribe(s=>s.data, (data)=>{
    localStorage.setItem(LOCAL_STORAGE_KLÍČE.DATA_PROGRAM, JSON.stringify(data));
  });

  const rok = useProgramStore.getState().urlState.rok;
  void načtiRok(rok);

  useProgramStore.subscribe(s => s.urlState.rok, (rok) => {
    void načtiRok(rok);
  });
};
