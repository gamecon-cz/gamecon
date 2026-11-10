/*
 * Filtr nad seznamem ve stránce zapsaný v adrese, ať jde poslat odkaz (stránka linie, archiv přednášek).
 */

/** hodnoty seznamu oddělené čárkou (?den=patek,sobota) */
export const seznamZParametru = (parametry: URLSearchParams, klíč: string): string[] =>
  (parametry.get(klíč) ?? "").split(",").filter(Boolean);

export const číslaZParametru = (parametry: URLSearchParams, klíč: string): number[] =>
  seznamZParametru(parametry, klíč).map(Number).filter(Number.isInteger);

/** čárka do query smí, ?den=patek,sobota se čte líp než patek%2Csobota */
export const sČárkami = (urlObjekt: URL): string => {
  urlObjekt.search = urlObjekt.searchParams.toString().replace(/%2C/g, ",");

  return urlObjekt.toString();
};

/** prázdná hodnota parametr z adresy smaže */
export const nastavParametr = (urlObjekt: URL, klíč: string, hodnota: string) => {
  if (hodnota) urlObjekt.searchParams.set(klíč, hodnota);
  else urlObjekt.searchParams.delete(klíč);
};
