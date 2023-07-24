import { Aktivita } from "../slices/programDataSlice";

export type FiltrProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  }
  ;

export type FiltrAktivit = Partial<{
  ročník: number,
  výběr: FiltrProgramTabulkaVýběr,
  filtrPřihlašovatelné: boolean,
  filtrLinie: string[],
  filtrTagy: string[],
}>;

// TODO: přidat zbytek filtrů
export const filtrujAktivity = (aktivity: Aktivita[], filtr: FiltrAktivit) =>{
  const {
    filtrLinie, filtrPřihlašovatelné, filtrTagy, ročník, výběr
  }= filtr;

  let aktivityFiltrované = aktivity;

  if (ročník)
    aktivityFiltrované = aktivityFiltrované
      .filter(aktivita => new Date(aktivita.cas.od).getFullYear() === ročník);

  if (výběr !== undefined)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        výběr.typ === "můj"
          ? aktivita?.stavPrihlaseni != undefined
          : new Date(aktivita.cas.od).getDay() === výběr.datum.getDay()
      );

  if (filtrLinie)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        filtrLinie.some(x => x === aktivita.linie)
      );

  if (filtrTagy)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        filtrTagy.some(x => aktivita.stitky.some(stitek => stitek === x))
      );

  if (filtrPřihlašovatelné)
    aktivityFiltrované = aktivityFiltrované
      .filter((aktivita) =>
        aktivita.prihlasovatelna && !aktivita.probehnuta
      );

  return aktivityFiltrované;
};

