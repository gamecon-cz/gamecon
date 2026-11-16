import { ApiAccommodationType } from "../../api/symfony/types";

export type KontrolaVyberu = { platny: true } | { platny: false; duvod: string };

const tvarNoci = (pocet: number): string => {
  if (pocet === 1) return "noc";
  return pocet >= 2 && pocet <= 4 ? "noci" : "nocí";
};

/** Days (0 = Wednesday …) the picked variants fall on, each once, in festival order. */
export function dnyVyberu(types: ApiAccommodationType[], variantIds: number[]): number[] {
  const dny = new Set<number>();
  for (const typ of types) {
    for (const [den, bunka] of Object.entries(typ.nights)) {
      if (variantIds.includes(bunka.variantId)) {
        dny.add(Number(den));
      }
    }
  }

  return [...dny].sort((a, b) => a - b);
}

/**
 * The same rules the server applies to a booking (`AccommodationWriter`), asked before anything
 * is sent: a pick that fails them waits in the grid instead of being refused. Dropping every
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
 * The same, for a pick of variants. One the grid does not show (a held night of a room type the
 * sleeping-bag restriction hides) has a day only the server knows, so counting just the visible
 * ones would hold a pick the server would answer for. Such a pick is not held back.
 */
export function zkontrolujVyberVMřížce(
  types: ApiAccommodationType[],
  variantIds: number[],
  minimumNights: number,
): KontrolaVyberu {
  const viditelné = new Set(types.flatMap((typ) => Object.values(typ.nights).map((bunka) => bunka.variantId)));
  if (variantIds.some((variantId) => !viditelné.has(variantId))) {
    return { platny: true };
  }

  return zkontrolujVyber(dnyVyberu(types, variantIds), minimumNights);
}

export function stejnaMnozina(prvni: number[], druha: number[]): boolean {
  return prvni.length === druha.length && prvni.every((prvek) => druha.includes(prvek));
}
