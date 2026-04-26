import { ProgramStateCreator, useProgramStore } from "..";
import { fetchPřihlášenýUživatel, ApiPřihlášenýUživatel } from "../../../api/přihlášenýUživatel";

export type PřihlášenýUživatelSlice = {
  přihlášenýUživatel: {
    data: ApiPřihlášenýUživatel,
  }
}

export const createPřihlášenýUživatelSlice: ProgramStateCreator<PřihlášenýUživatelSlice> = (_set, _get) => ({
  přihlášenýUživatel: {
    data: {},
  },
});

export const načtiPřihlášenýUživatel = async () => {
  const přihlášenýUživatel = await fetchPřihlášenýUživatel();

  useProgramStore.setState(s => {
    s.přihlášenýUživatel.data = přihlášenýUživatel;
  }, undefined, "dotažení přihlášenýUživatel");
};
