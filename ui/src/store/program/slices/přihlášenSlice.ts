import { ProgramStateCreator, useProgramStore } from "..";
import { fetchPřihlášenýUživatel, ApiUživatel } from "../../../api/přihlášenýUživatel";

export type PřihlášenýUživatelSlice = {
  přihlášenýUživatel: {
    /** účastník kterého se data zobrazují */
    ucastnik?: ApiUživatel,
    operator?: ApiUživatel,
  }
}

export const createPřihlášenýUživatelSlice: ProgramStateCreator<PřihlášenýUživatelSlice> = (_set, _get) => ({
  přihlášenýUživatel: {
    ucastnik: undefined,
    operator: undefined,
  },
});

export const načtiPřihlášenýUživatel = async () => {
  const přihlášenýUživatel = await fetchPřihlášenýUživatel();

  useProgramStore.setState(s => {
    s.přihlášenýUživatel.ucastnik = přihlášenýUživatel.ucastnik;
    s.přihlášenýUživatel.operator = přihlášenýUživatel.operator;
  }, undefined, "dotažení přihlášenýUživatel");
};
