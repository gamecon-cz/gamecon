import { StateCreator } from "zustand";
import create from "zustand";
import { devtools } from "zustand/middleware";
import { immer } from "zustand/middleware/immer";
import { subscribeWithSelector } from "zustand/middleware";
import { GAMECON_KONSTANTY } from "../../env";

export type ZustandMutators = [
  ["zustand/subscribeWithSelector", never],
  // redux dev tools extension do prohlížeče
  ["zustand/devtools", never],
  ["zustand/immer", never],
];

export type MyStateCreator<State, T> = StateCreator<State, ZustandMutators, [], T>;

export const createMyStore = <State>(createState: MyStateCreator<State, State>) =>
  create<State>()(
    subscribeWithSelector(devtools(immer((...args) => ({ ...createState(...args) })), {
      enabled: GAMECON_KONSTANTY.IS_DEV_SERVER || GAMECON_KONSTANTY.FORCE_REDUX_DEVTOOLS
    }))
  );

