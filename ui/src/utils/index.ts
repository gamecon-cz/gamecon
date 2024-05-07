export * from "./array";
export * from "./enum";
export * from "./gamecon";
export * from "./tranformace";
export * from "./async";

export const tryParseNumber = (str: string | null): number | undefined => {
  if (str == null || str === "") return;
  const maybeNumber = +str;
  if (str && !Number.isNaN(maybeNumber)) return maybeNumber;
};

/**
 * Match the first character of the string and capitalize it
 */
export const mb_ucfirst = (input: string): string => {
  return input.replace(/^./u, match => match.toUpperCase());
}

export type TValueLabel<T = any> = {
  value: T;
  label: T;
};

export const asValueLabel = <T,>(obj: T): TValueLabel<T> => ({
  value: obj,
  label: obj,
});

export const datumPřidejDen = (datum: Date, dny = 1) =>{
  const výsledek = new Date(datum);
  výsledek.setDate(výsledek.getDate() + dny);
  return výsledek;
};
