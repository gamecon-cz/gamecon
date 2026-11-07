import { PracovníUživatel } from "../../env";

/**
 * Hlavička mřížky na infopultu: obsluha musí vidět, na čí účet se prodává, než cokoli potvrdí.
 */
export const nadpisProdeje = (pracovníUživatel: PracovníUživatel | null): string => {
  if (pracovníUživatel === null) {
    return "Anonymní prodej";
  }

  return pracovníUživatel.nazev === ""
    ? `Prodej uživateli ID ${pracovníUživatel.id}`
    : `Prodej uživateli ${pracovníUživatel.nazev} (ID ${pracovníUživatel.id})`;
};
