/**
 * The server locks every meal once orders can no longer be placed or changed — the counts went
 * to the canteen, or everything is withdrawn. The desk gets no deadline locks, so after the
 * deadline it never sees this.
 */
export const objednavkyZmrazeny = (meals: readonly { locked: boolean }[]): boolean =>
  meals.length > 0 && meals.every((meal) => meal.locked);
