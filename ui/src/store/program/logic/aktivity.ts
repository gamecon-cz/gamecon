import { AktivitaStav } from "../../../api/program";
import { Pohlavi } from "../../../api/přihlášenýUživatel";
import { GAMECON_KONSTANTY } from "../../../env";
import { volnoTypZObsazenost } from "../../../utils";
import { Aktivita } from "../slices/programDataSlice";

export type FiltrProgramTabulkaVýběr =
  | {
      typ: "můj";
    }
  | {
      typ: "den";
      datum: Date;
    };

export type FiltrAktivit = Partial<{
  ročník: number;
  výběr: FiltrProgramTabulkaVýběr;
  filtrPřihlašovatelné: boolean;
  filtrLinie: string[];
  filtrTagy: string[];
  filtrStavAktivit: AktivitaStav[];
}>;

export const aktivitaStatusZAktivity = (
  aktivita: Aktivita,
  pohlavi?: Pohlavi | undefined
): AktivitaStav => {
  if (
    aktivita.stavPrihlaseni != undefined &&
    aktivita.stavPrihlaseni !== "sledujici"
  ) {
    return "prihlasen";
  }
  if (aktivita.vedu) {
    return "organizator";
  }
  if (aktivita.stavPrihlaseni === "sledujici") {
    return "nahradnik";
  }
  if (aktivita.vdalsiVlne) {
    return "vDalsiVlne";
  }
  if (aktivita.vBudoucnu) {
    return "vBudoucnu";
  }

  if (aktivita.obsazenost) {
    const volnoTyp = volnoTypZObsazenost(aktivita.obsazenost);
    if (volnoTyp !== "u" && volnoTyp !== pohlavi) {
      return "plno";
    }
  }
  return "volno";
};

const jeAktivitaVeDni = (casAktivity: Date, datum: Date) => {
  if (GAMECON_KONSTANTY.PROGRAM_ZACATEK < GAMECON_KONSTANTY.PROGRAM_KONEC) {
    return casAktivity.getDay() === datum.getDay();
  } else {
    return (
      (casAktivity.getDay() === datum.getDay() &&
        casAktivity.getHours() >= GAMECON_KONSTANTY.PROGRAM_ZACATEK) ||
      (casAktivity.getDay() === datum.getDay() + 1 &&
        casAktivity.getHours() <= GAMECON_KONSTANTY.PROGRAM_KONEC)
    );
  }
};

// TODO: přidat zbytek filtrů
export const filtrujAktivity = (aktivity: Aktivita[], filtr: FiltrAktivit) => {
  const {
    filtrLinie,
    filtrPřihlašovatelné,
    filtrTagy,
    ročník,
    výběr,
    filtrStavAktivit,
  } = filtr;

  let aktivityFiltrované = aktivity;

  if (ročník)
    aktivityFiltrované = aktivityFiltrované.filter(
      (aktivita) => new Date(aktivita.cas.od).getFullYear() === ročník
    );

  if (výběr !== undefined)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) =>
      výběr.typ === "můj"
        ? aktivita?.stavPrihlaseni != undefined
        : jeAktivitaVeDni(new Date(aktivita.cas.od), výběr.datum)
    );

  if (filtrLinie)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) =>
      filtrLinie.some((x) => x === aktivita.linie)
    );

  if (filtrTagy)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) =>
      filtrTagy.some((x) => aktivita.stitky.some((stitek) => stitek === x))
    );

  // TODO: přihlašovatelnost aktivity dle pohlaví
  if (filtrStavAktivit)
    aktivityFiltrované = aktivityFiltrované.filter((aktivita) =>
      filtrStavAktivit.some((x) => aktivitaStatusZAktivity(aktivita) === x)
    );

  if (filtrPřihlašovatelné)
    aktivityFiltrované = aktivityFiltrované.filter(
      (aktivita) => aktivita.prihlasovatelna && !aktivita.probehnuta
    );

  return aktivityFiltrované;
};
