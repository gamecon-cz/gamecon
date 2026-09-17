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
 * Server posílá žebřík už posunutý o to, co zákazník má, takže první stupeň je právě
 * tenhle kus. Košík účtuje totéž číslo — viz DiscountCalculator::priceForNextPiece().
 */
export function cenaDalsihoKusu(steps: ApiPriceStep[]): string | null {
  return steps[0]?.price ?? null;
}

/**
 * Popis žebříku: „0 Kč / 30 Kč (první zdarma)", u víc zvýhodněných stupňů
 * „0 Kč / 0 Kč / 400 Kč (první tři zdarma)".
 *
 * Text závorky skládá backend (PriceStep::label). Popisy jsou kumulativní — každý shrnuje
 * žebřík od prvního kusu — takže stačí vzít ten poslední, který nějaký nese.
 *
 * Vrací null u jediného stupně — tam není co vysvětlovat a stačí holá cena.
 */
export function popisSchodu(steps: ApiPriceStep[]): string | null {
  if (steps.length < 2) return null;

  // Pozpátku ručně, ne findLast — build cílí na es6 a esbuild převádí syntaxi, ne metody.
  let popis: string | null = null;
  for (let index = steps.length - 1; index >= 0 && popis === null; index--) {
    popis = steps[index].label;
  }
  if (popis === null) return null;

  const ceny = steps.map((krok) => formatCena(krok.price)).join(" / ");

  return `${ceny} (${popis})`;
}
