export const PRAGUE_TZ = "Europe/Prague";

const fmtHodiny = new Intl.DateTimeFormat("cs-CZ", { timeZone: PRAGUE_TZ, hour: "numeric", hour12: false });
const fmtDen = new Intl.DateTimeFormat("en-US", { timeZone: PRAGUE_TZ, weekday: "short" });
const fmtDenMesic = new Intl.DateTimeFormat("cs-CZ", { timeZone: PRAGUE_TZ, day: "numeric" });
const fmtMesic = new Intl.DateTimeFormat("cs-CZ", { timeZone: PRAGUE_TZ, month: "numeric" });
const fmtRok = new Intl.DateTimeFormat("cs-CZ", { timeZone: PRAGUE_TZ, year: "numeric" });

const WEEKDAYS_EN = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

/** Hodiny 0–23 v Europe/Prague */
export const pražskéHodiny = (date: Date): number => {
  const formatted = fmtHodiny.format(date);
  return parseInt(formatted, 10) % 24; // % 24 pro případné "24" o půlnoci v některých prostředích
};

/** Den v týdnu 0–6 (Ned–Sob) v Europe/Prague */
export const pražskýDenVTýdnu = (date: Date): number => {
  const formatted = fmtDen.format(date);
  return WEEKDAYS_EN.indexOf(formatted);
};

/** Den v měsíci 1–31 v Europe/Prague */
export const pražskýDenVMěsíci = (date: Date): number =>
  parseInt(fmtDenMesic.format(date), 10);

/** Měsíc 1–12 v Europe/Prague */
export const pražskýMěsíc = (date: Date): number =>
  parseInt(fmtMesic.format(date), 10);

/** Rok v Europe/Prague */
export const pražskýRok = (date: Date): number =>
  parseInt(fmtRok.format(date), 10);

