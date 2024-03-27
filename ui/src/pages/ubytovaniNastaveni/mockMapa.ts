import _mapa from "./mockMapa.json";


type Místnost = {
  značení: string;
  šířka: number;
  typ?: string;
  popis?: string;
  wcSprchy?: boolean;
};

type Schodiště = {
  schodiště: string;
  šířka: number;
};

export const jeSchoditě = (vstup: Místnost | Schodiště): vstup is Schodiště =>{
  return "schodiště" in vstup;
};

export type Mapa = (Místnost | Schodiště)[][];

export const mapa = _mapa as Mapa;

