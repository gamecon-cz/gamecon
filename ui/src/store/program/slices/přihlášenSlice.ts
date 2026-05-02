import { ProgramStateCreator, useProgramStore } from "..";
import { fetchPřihlášenýUživatel, ApiUživatel } from "../../../api/přihlášenýUživatel";

export type PřihlášenýUživatelSlice = {
  přihlášenýUživatel: {
    /** účastník kterého se data zobrazují */
    data: ApiUživatel,
    operator: ApiUživatel,
  }
}

export const createPřihlášenýUživatelSlice: ProgramStateCreator<PřihlášenýUživatelSlice> = (_set, _get) => ({
  přihlášenýUživatel: {
    data: {},
    operator: {},
  },
});

export const načtiPřihlášenýUživatel = async () => {
  const přihlášenýUživatel = await fetchPřihlášenýUživatel();

  useProgramStore.setState(s => {
    s.přihlášenýUživatel.data = přihlášenýUživatel.ucastnik;
    s.přihlášenýUživatel.operator = přihlášenýUživatel.operator;
  }, undefined, "dotažení přihlášenýUživatel");
};
