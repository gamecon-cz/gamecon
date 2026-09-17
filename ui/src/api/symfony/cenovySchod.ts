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
 * „0 Kč / 200 Kč / 400 Kč (první tři se slevou)".
 *
 * Vrací null u jediného stupně — tam není co vysvětlovat a stačí holá cena.
 */
export function popisSchodu(steps: ApiPriceStep[]): string | null {
  if (steps.length < 2) return null;

  const posledni = steps[steps.length - 1];
  // Kolik kusů je zvýhodněných: poslední stupeň je ten bez slevy, takže nároky končí
  // těsně před ním. Bez toho by třístupňový žebřík (2 + 1 zdarma) hlásil jen dva.
  const zvyhodnenych = posledni.fromQuantity - 1;
  if (zvyhodnenych <= 0) return null;

  const vsechnyZdarma = steps
    .slice(0, -1)
    .every((krok) => parseFloat(krok.price) === 0);

  const poradi =
    zvyhodnenych === 1
      ? "první"
      : zvyhodnenych === 2
        ? "první dva"
        : `první ${zvyhodnenych}`;

  const ceny = steps.map((krok) => formatCena(krok.price)).join(" / ");

  return `${ceny} (${poradi} ${vsechnyZdarma ? "zdarma" : "se slevou"})`;
}
