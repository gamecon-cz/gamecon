import { ProgramDataSlice } from "./slices/programDataSlice";
import { createProgramDataSlice } from "./slices/programDataSlice";
import { createMyStore, MyStateCreator } from "../common/MyStore";
import { createProgramUrlSlice, ProgramUrlSlice } from "./slices/urlSlice";
import { createPřihlášenýUživatelSlice, PřihlášenýUživatelSlice } from "./slices/přihlášenSlice";

type ProgramState = ProgramDataSlice & ProgramUrlSlice & PřihlášenýUživatelSlice;

export type ProgramStateCreator<T> = MyStateCreator<ProgramState, T>;

const createState: ProgramStateCreator<ProgramState> = (...args) => ({
  ...createProgramDataSlice(...args),
  ...createProgramUrlSlice(...args),
  ...createPřihlášenýUživatelSlice(...args),
});

// TODO: vytáhnout metody na modifikaci stavu ven ze slices, slices budou obsahovat pouze data
// TODO: nepoužívat nikde tenhle hook, zaobalit do selektorů a použít je
export const useProgramStore = createMyStore(createState);
