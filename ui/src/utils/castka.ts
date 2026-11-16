/**
 * Stejný zápis částky jako Finance::formatujCastku() a admin/files/formatuj-castku-1.0.js: celé koruny bez desetin,
 * jinak dvě desetinná místa, čárka a pevná mezera mezi tisíci. Částka má být už zaokrouhlená na haléře, Math.round
 * tady jen smaže chybu plovoucí čárky.
 */
export function formatujCastku(castka: number): string {
  const zaokrouhlena = Math.round(castka * 100) / 100;

  return (zaokrouhlena === 0 ? 0 : zaokrouhlena).toLocaleString("cs-CZ", {
    minimumFractionDigits: Number.isInteger(zaokrouhlena) ? 0 : 2,
    maximumFractionDigits: 2,
  });
}

/**
 * Částka z API, které desetinná čísla posílá jako text ("120.00"). Nečíselný text se vrátí beze změny.
 */
export function formatCastkaZApi(castka: string): string {
  const cislo = parseFloat(castka);

  return Number.isNaN(cislo) ? castka : formatujCastku(cislo);
}

/**
 * "120.00" → "120 Kč", "80.50" → "80,50 Kč". Tenká mezera před Kč jako ve Finance::formatovanyStav().
 */
export function formatCena(cena: string): string {
  return `${formatCastkaZApi(cena)}\u2009Kč`;
}

/**
 * Částka do políčka formuláře: "120.00" → "120", "80.50" → "80.5". Zůstává desetinná tečka, protože hodnotu
 * z políčka pak API přijme jako desetinné číslo (český zápis s čárkou by neprošel).
 */
export function castkaProFormular(castka: string): string {
  const cislo = parseFloat(castka);

  return Number.isNaN(cislo) ? castka : String(cislo);
}
