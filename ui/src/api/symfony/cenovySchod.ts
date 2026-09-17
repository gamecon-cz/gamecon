import { ApiPriceStep } from "./types";

/** Format price string for Czech locale: "120.00" → "120 Kč", "80.50" → "80,50 Kč" */
export function formatCena(price: string): string {
  const castka = parseFloat(price);
  if (isNaN(castka)) return `${price} Kč`;
  if (Number.isInteger(castka)) return `${castka} Kč`;
  return `${castka.toFixed(2).replace(".", ",")} Kč`;
}

/**
 * Cena kusu, který by si zákazník přidal jako další.
 *
 * Stupně přicházejí seřazené a platí „od N. kusu", takže se bere poslední, na který už
 * dosáhl. Díky tomu se po přidání do košíku přepočítá cena bez dotazu na server.
 */
export function cenaDalsihoKusu(steps: ApiPriceStep[], jizMa: number): string | null {
  if (steps.length === 0) return null;

  let aktualni = steps[0];
  for (const step of steps) {
    if (step.fromQuantity <= jizMa + 1) aktualni = step;
  }

  return aktualni.price;
}

/**
 * Popis žebříku pro zobrazení: „0 Kč / 30 Kč (první zdarma)".
 *
 * Vrací null, když zvýhodnění nezbývá — po vyčerpání nároku i u položky bez něj stačí
 * holá cena a vysvětlovat není co.
 */
export function popisSchodu(steps: ApiPriceStep[], jizMa = 0): string | null {
  if (steps.length < 2) return null;

  const [prvni, dalsi] = steps;
  const zvyhodnenych = dalsi.fromQuantity - 1;
  const zbyva = zvyhodnenych - jizMa;
  if (zbyva <= 0) return null;

  const zdarma = parseFloat(prvni.price) === 0;
  const poradi =
    jizMa > 0
      ? zbyva === 1
        ? "ještě jeden"
        : `ještě ${zbyva}`
      : zvyhodnenych === 1
        ? "první"
        : zvyhodnenych === 2
          ? "první dva"
          : `prvních ${zvyhodnenych}`;

  return `${formatCena(prvni.price)} / ${formatCena(dalsi.price)} (${poradi} ${zdarma ? "zdarma" : "se slevou"})`;
}
