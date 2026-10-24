import { ApiMealDish, ApiMealMenuSlot } from "../../api/symfony/types";

/** „2026-07-15" → „15. 7." — termíny jídelníčku jsou vždy letošní, rok je zbytečný. */
export const datumCesky = (datum: string): string => {
  const [, mesic, den] = datum.split("-").map(Number);
  return mesic && den ? `${den}. ${mesic}.` : datum;
};

/** Název jídla, jak ho čte účastník: s označením vegetariánského. */
export const nazevJidla = (jidlo: ApiMealDish): string =>
  jidlo.vegetarian ? `${jidlo.name} (vegetariánské)` : jidlo.name;

export const vychoziJidlo = (slot: { dishes: ApiMealDish[] }): ApiMealDish | undefined =>
  slot.dishes.find((jidlo) => jidlo.default) ?? slot.dishes[0];

/** Jen sloty, ke kterým má zákazník koupenou porci — jen u nich je co vybírat. */
export const slotySPorci = (sloty: ApiMealMenuSlot[]): ApiMealMenuSlot[] =>
  sloty.filter((slot) => slot.portions.length > 0);

/** Sloty po dnech, v pořadí, v jakém je posílá server (den, pak oběd před večeří). */
export const poDnech = <T extends { day: number; dayName: string }>(sloty: T[]): { day: number; dayName: string; sloty: T[] }[] => {
  const dny: { day: number; dayName: string; sloty: T[] }[] = [];
  for (const slot of sloty) {
    const den = dny.find((kandidat) => kandidat.day === slot.day);
    if (den) {
      den.sloty.push(slot);
    } else {
      dny.push({ day: slot.day, dayName: slot.dayName, sloty: [slot] });
    }
  }
  return dny;
};

export const velkePismeno = (text: string): string => text.charAt(0).toUpperCase() + text.slice(1);
