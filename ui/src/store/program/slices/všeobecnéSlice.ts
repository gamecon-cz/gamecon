import { ProgramStateCreator, useProgramStore } from "..";

export type VšeobecnéSlice = {
  všeobecné: {
    filtryOtevřené: boolean
  }
}

export const createVšeobecnéSlice: ProgramStateCreator<VšeobecnéSlice> = (_set, _get) => ({
  všeobecné: {
    filtryOtevřené: false
  },
});

export const nastavFiltryOtevřené = (hodnota: boolean) => {
  useProgramStore.setState(s => {
    s.všeobecné.filtryOtevřené = hodnota;
  }, undefined, "filtry otevřené");
};
