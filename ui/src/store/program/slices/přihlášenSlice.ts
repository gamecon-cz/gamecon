import { ProgramStateCreator } from "..";
import { fetchPřihlášenýUživatel, PřihlášenýUživatel } from "../../../api/přihlášenýUživatel";

export type PřihlášenýUživatelSlice = {
  přihlášenýUživatel: {
    data: PřihlášenýUživatel,
  }
  načtiPřihlášenýUživatel(): Promise<void>
}

export const createPřihlášenýUživatelSlice: ProgramStateCreator<PřihlášenýUživatelSlice> = (set, _get) => ({
  přihlášenýUživatel: {
    data: {},
  },
  async načtiPřihlášenýUživatel() {
    const přihlášenýUživatel = await fetchPřihlášenýUživatel();

    set(s => {
      s.přihlášenýUživatel.data = přihlášenýUživatel;
    }, undefined, "dotažení přihlášenýUživatel");
  },
});
