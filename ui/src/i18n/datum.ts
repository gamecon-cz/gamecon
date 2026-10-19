import { t, KlíčPřekladu } from ".";
import { GAMECON_KONSTANTY } from "../env";
import { DNY_NÁZVY, DNY_NÁZVY_S_HÁČKY, formátujDenVTýdnu, pražskýDenVMěsíci, pražskýMěsíc } from "../utils";

/*
 * Dny pro čtenáře v jazyce stránky. Klíče skupin v tabulce programu a slugy v URL
 * zůstávají česky (formátujDenVTýdnu), překládá se jen to, co se zobrazuje.
 */

const KLÍČE_DNŮ: Record<string, KlíčPřekladu> = {
  pondeli: "den.pondeli",
  utery: "den.utery",
  streda: "den.streda",
  ctvrtek: "den.ctvrtek",
  patek: "den.patek",
  sobota: "den.sobota",
  nedele: "den.nedele",
};

/** „pátek" / „Friday" */
export const názevDneVTýdnu = (datum: Date | number): string =>
  t(KLÍČE_DNŮ[formátujDenVTýdnu(datum)]);

/**
 * Popisek skupiny v tabulce programu: den (klíčem je český název s háčky) přeloží,
 * ostatní klíče (linie, místnost) vrátí, jak jsou.
 */
export const popisekSkupiny = (klíč: string): string => {
  const index = DNY_NÁZVY_S_HÁČKY.indexOf(klíč);
  return index === -1 ? klíč : t(KLÍČE_DNŮ[DNY_NÁZVY[index]]);
};

/** „pátek 18.7." / „Friday 18/7", s `pouzeText` jen den */
export const formátujDatumProČtenáře = (datum: Date, pouzeText = false): string => {
  const den = názevDneVTýdnu(datum);
  if (pouzeText) return den;

  const denVMěsíci = pražskýDenVMěsíci(datum);
  const měsíc = pražskýMěsíc(datum);

  return GAMECON_KONSTANTY.JAZYK === "en"
    ? `${den} ${denVMěsíci}/${měsíc}`
    : `${den} ${denVMěsíci}.${měsíc}.`;
};
