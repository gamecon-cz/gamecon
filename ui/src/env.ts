/*
 * Konstanty předané ze serveru společně se scriptem.
 * Liší se pro testovací server
 */

import { range } from "./utils";


type GameconKonstanty = {
  IS_DEV_SERVER: boolean,
  /**
   * cesta k této stráce v rámci které se preact využívá.
   * například /web/program/
   */
  BASE_PATH_PAGE: string,
  /**
   * cesta k api
   * například /web/api/
   */
  BASE_PATH_API: string,
  ROCNIK: number,
  PROGRAM_OD: number,
  PROGRAM_DO: number,
  PROGRAM_DNY: number[],
  LEGENDA: string,
};

declare global {
  interface Window {
    GAMECON_KONSTANTY: Partial<GameconKonstanty>;
    preactMost: {
      obchod: {
        show?: (() => void) | undefined,
      }
    }
  }
}

const GAMECON_KONSTANTY_DEFAULT: GameconKonstanty = {
  IS_DEV_SERVER: false,
  BASE_PATH_PAGE: "/",
  BASE_PATH_API: "/api/",
  ROCNIK: 2022,
  PROGRAM_OD: 1658268000000,
  PROGRAM_DO: 1658689200000,
  PROGRAM_DNY: [],
  LEGENDA: "",
};

export const GAMECON_KONSTANTY = {
  ...GAMECON_KONSTANTY_DEFAULT,
  ...window.GAMECON_KONSTANTY,
}

const ČAS_DEN = 24 * 60 * 60 * 1000;
GAMECON_KONSTANTY.PROGRAM_DNY = range(GAMECON_KONSTANTY.PROGRAM_OD, GAMECON_KONSTANTY.PROGRAM_DO, ČAS_DEN).reverse();

export const initEnv = () => {
  window.preactMost = {
    obchod: {
    }
  };
}
