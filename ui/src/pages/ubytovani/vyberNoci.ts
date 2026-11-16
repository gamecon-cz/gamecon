import { ApiAccommodationType } from "../../api/symfony/types";

export type KontrolaVyberu = { platny: true } | { platny: false; duvod: string };

const tvarNoci = (pocet: number): string => {
  if (pocet === 1) return "noc";
  return pocet >= 2 && pocet <= 4 ? "noci" : "nocí";
};

/** The day (0 = Wednesday …) of every variant the grid shows. */
function mapaVariantNaDen(types: ApiAccommodationType[]): Map<number, number> {
  const dnyVariant = new Map<number, number>();
  for (const typ of types) {
    for (const [den, bunka] of Object.entries(typ.nights)) {
      dnyVariant.set(bunka.variantId, Number(den));
    }
  }

  return dnyVariant;
}

/** Days (0 = Wednesday …) the picked variants fall on, each once, in festival order. */
export function dnyVyberu(types: ApiAccommodationType[], variantIds: number[]): number[] {
  const dnyVariant = mapaVariantNaDen(types);
  const dny = new Set<number>();
  for (const variantId of variantIds) {
    const den = dnyVariant.get(variantId);
    if (den !== undefined) {
      dny.add(den);
    }
  }

  return [...dny].sort((a, b) => a - b);
}

/**
 * The same rules the server applies to a booking (`AccommodationWriter`), asked before anything
 * is sent: a pick that fails them waits in the grid instead of being refused (see
 * `zkontrolujVyberVMřížce()` for the one pick that cannot be judged here). Dropping every
 * night is always fine.
 */
export function zkontrolujVyber(dny: number[], minimumNights: number): KontrolaVyberu {
  if (dny.length === 0) {
    return { platny: true };
  }

  if (dny.length < minimumNights) {
    const chybi = minimumNights - dny.length;

    return { platny: false, duvod: `Vyber ještě ${chybi} navazující ${tvarNoci(chybi)} ubytování.` };
  }

  for (let index = 1; index < dny.length; index++) {
    if (dny[index] !== dny[index - 1] + 1) {
      return { platny: false, duvod: "Vybrané noci ubytování na sebe musí navazovat." };
    }
  }

  return { platny: true };
}

/**
 * The same, for a pick of variants. A variant the grid does not show (a held night of a room
 * type the sleeping-bag restriction hides) has a day the grid cannot tell, so counting only the
 * visible ones would hold a pick on a hint that can never come true. Such a pick is sent instead
 * and the server answers it, with a refusal under that restriction: it is never cut down to the
 * visible nights, which would delete the hidden one.
 */
export function zkontrolujVyberVMřížce(
  types: ApiAccommodationType[],
  variantIds: number[],
  minimumNights: number,
): KontrolaVyberu {
  const dnyVariant = mapaVariantNaDen(types);
  if (variantIds.some((variantId) => !dnyVariant.has(variantId))) {
    return { platny: true };
  }

  return zkontrolujVyber(dnyVyberu(types, variantIds), minimumNights);
}

/**
 * The hosts cannot arrange swaps of beds, so as in the old form's script ticking `variantId`, a
 * night of `typ`, moves every other picked night to `typ`; a night where it is not open is dropped.
 */
export function vyberTypNaNoci(
  types: ApiAccommodationType[],
  picked: number[],
  typ: ApiAccommodationType,
  day: number,
  variantId: number,
): number[] {
  const dnyVariant = mapaVariantNaDen(types);
  const vysledek: number[] = [];
  for (const pickedVariantId of picked) {
    const pickedDay = dnyVariant.get(pickedVariantId);
    if (pickedDay === undefined) {
      vysledek.push(pickedVariantId);
      continue;
    }
    // The night being ticked is replaced by the tile just ticked.
    if (pickedDay === day) continue;

    const cil = typ.nights[pickedDay];
    if (cil !== undefined && !cil.locked && !vysledek.includes(cil.variantId)) {
      vysledek.push(cil.variantId);
    }
  }
  vysledek.push(variantId);

  return vysledek;
}

export function stejnaMnozina(prvni: number[], druha: number[]): boolean {
  return prvni.length === druha.length && prvni.every((prvek) => druha.includes(prvek));
}
