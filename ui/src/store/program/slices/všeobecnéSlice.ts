import { ProgramStateCreator, useProgramStore } from "..";
import { fetchAktivitaTým, AktivitaTymResponse } from "../../../api/program";

export type NastaveniTymuData = AktivitaTymResponse;

export type VšeobecnéSlice = {
  všeobecné: {
    chyba?: string,
    filtryOtevřené: boolean,
    zvětšeno: boolean,
    kompaktní: boolean,
    modalOdhlásitAktivitaId?: number,
    modalUpozorněníNaVěk?: {
      nazevAktivity: string,
      minimalniVek: number,
    },
    nastaveniTymu?: {
      aktivitaId: number,
      nazevAktivity?: string,
      data?: NastaveniTymuData,
    },
  }
}

export const createVšeobecnéSlice: ProgramStateCreator<VšeobecnéSlice> = (_set, _get) => ({
  všeobecné: {
    filtryOtevřené: false,
    zvětšeno: false,
    kompaktní: false,
  },
});

export const nastavModalNastaveníTýmu = (aktivitaId?: number, nazevAktivity?: string) => {
  useProgramStore.setState(s => {
    if (aktivitaId)
      s.všeobecné.nastaveniTymu = { aktivitaId, nazevAktivity };
    else
      delete s.všeobecné.nastaveniTymu;
  }, undefined, "nastav modal nastaveni tymu");
}

export const dotáhniNastaveníTýmuProModal = async () => {
  const aktivitaId = useProgramStore.getState().všeobecné.nastaveniTymu?.aktivitaId;
  if (!aktivitaId) {
    // todo:
    console.warn("");
    return;
  }
  const data = await fetchAktivitaTým(aktivitaId);
  useProgramStore.setState(s => {
    s.všeobecné.nastaveniTymu = {
      aktivitaId,
      nazevAktivity: s.všeobecné.nastaveniTymu?.nazevAktivity,
      data: data
    };
  }, undefined, "dotáhni nastavení týmu");
}

export const nastavFiltryOtevřené = (hodnota: boolean) => {
  useProgramStore.setState(s => {
    s.všeobecné.filtryOtevřené = hodnota;
  }, undefined, "nastav filtry otevřené");
};

export const přepniZvětšeno = () => {
  useProgramStore.setState(s => {
    s.všeobecné.zvětšeno = !s.všeobecné.zvětšeno;
  }, undefined, "přepni zvětšeno");
};

export const nastavZvětšeno = (hodnota: boolean) => {
  useProgramStore.setState(s => {
    s.všeobecné.zvětšeno = hodnota;
  }, undefined, "nastav zvětšeno");
};

export const nastavKompaktní = (hodnota: boolean) => {
  useProgramStore.setState(s => {
    s.všeobecné.kompaktní = hodnota;
  }, undefined, "nastav kompaktní");
};

export const přepniKompaktní = () => {
  useProgramStore.setState(s => {
    s.všeobecné.kompaktní = !s.všeobecné.kompaktní;
  }, undefined, "přepni kompaktní");
};

/**
 * Bez id aktivity se modal skryje
 */
export const nastavModalOdhlásit = (aktivitaId?: number) => {
  useProgramStore.setState(s => {
    s.všeobecné.modalOdhlásitAktivitaId = aktivitaId;
  }, undefined, "nastav modal odhlásit");
};

// funkce do stavu nepatří (devtools ho serializují), proto vedle něj
let přihlášeníPoUpozorněníNaVěk: (() => void) | undefined;

/**
 * Upozornění před přihlášením na aktivitu, na jejíž minimální věk účastník nedosáhne.
 * Přihlášení provede až potvrzení (přihlaš), zavření ho zahodí.
 */
export const otevřiUpozorněníNaVěk = (nazevAktivity: string, minimalniVek: number, přihlaš: () => void) => {
  přihlášeníPoUpozorněníNaVěk = přihlaš;
  useProgramStore.setState(s => {
    s.všeobecné.modalUpozorněníNaVěk = { nazevAktivity, minimalniVek };
  }, undefined, "otevři upozornění na věk");
};

export const zavřiUpozorněníNaVěk = (přihlásit: boolean) => {
  const přihlaš = přihlášeníPoUpozorněníNaVěk;
  přihlášeníPoUpozorněníNaVěk = undefined;
  useProgramStore.setState(s => {
    delete s.všeobecné.modalUpozorněníNaVěk;
  }, undefined, "zavři upozornění na věk");
  if (přihlásit) přihlaš?.();
};

export const nastavChyba = (chyba?: string) => {
  useProgramStore.setState(s => {
    s.všeobecné.chyba = chyba;
  }, undefined, "nastav chyba");
};
