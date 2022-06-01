/* 
  Virtuální API dokud se nudělá skutečné
*/

import { range } from "../utils";

const ČAS_DEN = 24 * 60 * 60 * 1_000;
export const UKÁZKOVÉ_DNY = range(5)
  .map((x) => Date.now() - x * ČAS_DEN)
  .reverse();

export const PŘIHLÁŠEN = true;

export const AKTIVNÍ_MOŽNOST_PROGRAM = "můj program";


export const LEGENDA_TEXT = "Další vlna nových aktivit: 1.7. v 20:00";
export const ORGANIZATOR = false;
export const KONCOVKA_DLE_POHLAVÍ = "";

