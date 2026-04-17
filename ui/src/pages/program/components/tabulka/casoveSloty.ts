import { GAMECON_KONSTANTY } from "../../../../env";
import { range } from "../../../../utils";

export const PROGRAM_KROK_CASU_MINUTY = 15;

const MINUT_V_HODINE = 60;
const MINUT_V_DNI = 24 * MINUT_V_HODINE;
const PROGRAM_PRES_PULNOC =
  GAMECON_KONSTANTY.PROGRAM_ZACATEK > GAMECON_KONSTANTY.PROGRAM_KONEC;

export const SLOTU_ZA_HODINU = MINUT_V_HODINE / PROGRAM_KROK_CASU_MINUTY;
export const ZACATEK_PROGRAMU_V_MINUTACH =
  GAMECON_KONSTANTY.PROGRAM_ZACATEK * MINUT_V_HODINE;
export const KONEC_PROGRAMU_V_MINUTACH =
  GAMECON_KONSTANTY.PROGRAM_KONEC * MINUT_V_HODINE
  + (PROGRAM_PRES_PULNOC ? MINUT_V_DNI : 0);

export const PROGRAM_HODINY =
  GAMECON_KONSTANTY.PROGRAM_ZACATEK < GAMECON_KONSTANTY.PROGRAM_KONEC
    ? range(GAMECON_KONSTANTY.PROGRAM_ZACATEK, GAMECON_KONSTANTY.PROGRAM_KONEC)
    : range(GAMECON_KONSTANTY.PROGRAM_ZACATEK, 24).concat(
      range(0, GAMECON_KONSTANTY.PROGRAM_KONEC),
    );

export const PROGRAM_CASOVE_SLOTY = range(
  ZACATEK_PROGRAMU_V_MINUTACH,
  KONEC_PROGRAMU_V_MINUTACH,
  PROGRAM_KROK_CASU_MINUTY,
);

export const casDateTimeNaProgramoveMinuty = (
  cas: Date,
  jeZacatek: boolean,
): number => {
  const minutyOdPulnoci = cas.getHours() * MINUT_V_HODINE + cas.getMinutes();
  if (!PROGRAM_PRES_PULNOC) {
    return minutyOdPulnoci;
  }
  if (
    (jeZacatek && minutyOdPulnoci < ZACATEK_PROGRAMU_V_MINUTACH)
    || (!jeZacatek && minutyOdPulnoci <= ZACATEK_PROGRAMU_V_MINUTACH)
  ) {
    return minutyOdPulnoci + MINUT_V_DNI;
  }

  return minutyOdPulnoci;
};

export const delkaAktivityVeSlotech = (zacatek: Date, konec: Date): number => {
  const zacatekVMinutach = casDateTimeNaProgramoveMinuty(zacatek, true);
  let konecVMinutach = casDateTimeNaProgramoveMinuty(konec, false);
  if (konecVMinutach <= zacatekVMinutach) {
    konecVMinutach += MINUT_V_DNI;
  }

  return Math.max(
    1,
    Math.ceil((konecVMinutach - zacatekVMinutach) / PROGRAM_KROK_CASU_MINUTY),
  );
};
