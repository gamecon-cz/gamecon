/*
 * Konstanty předané ze serveru společně se scriptem.
 * Liší se pro testovací server
 */

import { PřihlášenýUživatel } from "./api/přihlášenýUživatel";
import { range } from "./utils";


type GameconKonstanty = {
  /**
   * Jestli se jedná o vite dev server
   */
  IS_DEV_SERVER: boolean,
  /**
   * Chrome redux devtools. Zapnout v php nastavení serveru pomocí 
define('FORCE_REDUX_DEVTOOLS', true);
   */
  FORCE_REDUX_DEVTOOLS: boolean,
  /**
   * cesta k této stráce v rámci které se preact využívá.
   * například /web/program/
   * preact by měl mít ve zprávě pouze část url 
   *   následující za touto cestou
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
  /**
   * !nenastavovat ručně, dopočítá se při startu z PROGRAM_OD-..._DO
   */
  PROGRAM_DNY: number[],
  PROGRAM_ŘAZENÍ_LINIE: string[],
  LEGENDA: string,

  /*
   * Začátek a konec programu pro determinaci toho které aktivity po půlnoci spadají ještě do daného dne
   */
  PROGRAM_ZACATEK: number;
  PROGRAM_KONEC: number;
}

type GameconPřednačtení = {
  přihlášenýUživatel?: PřihlášenýUživatel,
  časPřednačtení?: number,
  etagy?: Record<string, string>
};

declare global {
  // interface se automaticky propojí s existujícím 
  //   proto je nutné použít interface a né type
  // eslint-disable-next-line @typescript-eslint/consistent-type-definitions
  interface Window {
    GAMECON_KONSTANTY: Partial<GameconKonstanty>;
    gameconPřednačtení: GameconPřednačtení;
    preactMost: {
      obchod: {
        show?: (() => void) | undefined,
      }
    }
  }
}

const GAMECON_KONSTANTY_DEFAULT: GameconKonstanty = {
  IS_DEV_SERVER: false,
  FORCE_REDUX_DEVTOOLS: false,
  BASE_PATH_PAGE: "http://localhost:3000/",
  BASE_PATH_API: "/api/",
  ROCNIK: 2022,
  PROGRAM_OD: 1658268000000,
  PROGRAM_DO: 1658689200000,
  PROGRAM_DNY: [],
  LEGENDA: "",
  PROGRAM_ŘAZENÍ_LINIE: [
    "brigádnické", "workshopy", "(bez typu – organizační)",
    "organizační výpomoc", "deskoherna", "turnaje v deskovkách",
    "epické deskovky", "wargaming", "larpy", "RPG",
    "mistrovství v DrD", "legendy klubu dobrodruhů",
    "akční a bonusové aktivity", "Přednášky", "doprovodný program"
  ],
  PROGRAM_ZACATEK: 8,
  PROGRAM_KONEC: 6,
};

export const GAMECON_KONSTANTY = {
  ...GAMECON_KONSTANTY_DEFAULT,
  ...window.GAMECON_KONSTANTY,
};

const ČAS_DEN = 24 * 60 * 60 * 1000;
GAMECON_KONSTANTY.PROGRAM_DNY = range(GAMECON_KONSTANTY.PROGRAM_OD, GAMECON_KONSTANTY.PROGRAM_DO, ČAS_DEN);

/** Roky ve kterých se gamecon konal */
export const ROKY = range(2009, GAMECON_KONSTANTY.ROCNIK).filter(x => x !== 2020);

export const initEnv = () => {
  window.preactMost = {
    obchod: {
    }
  };
};
