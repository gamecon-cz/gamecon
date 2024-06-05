import { ProgramStateCreator, useProgramStore } from "..";

export type VšeobecnéSlice = {
  všeobecné: {
    filtryOtevřené: boolean,
    zvětšeno: boolean,
    kompaktní: boolean,
    modalOdhlásitAktivitaId?: number,
    // TODO: dvě souběžné načítání mají za výsledek že jak doběhme první tak se načítání dá na false ikdyž běží další dotazy
    načítání: boolean,
  }
}

export const createVšeobecnéSlice: ProgramStateCreator<VšeobecnéSlice> = (_set, _get) => ({
  všeobecné: {
    filtryOtevřené: false,
    zvětšeno: false,
    kompaktní: false,
    načítání: false,
  },
});

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
