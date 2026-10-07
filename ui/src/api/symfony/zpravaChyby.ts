/** API Platform answers errors as `{ detail }`; without it only the HTTP status is left to show. */
export const zprávaChyby = async (odpověď: Response, výchozí: string): Promise<string> => {
  const chyba = await odpověď.json().catch(() => null) as { detail?: string } | null;

  return chyba?.detail ?? výchozí;
};
