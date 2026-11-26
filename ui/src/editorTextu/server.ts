/**
 * Požadavky editoru na server, viz model/Web/EditorTextuPozadavek.php. Jdou na adresu stránky, na které editor je
 * (modul je obslouží na začátku), s parametrem `editorTextu`. Adresa je absolutní: relativní by se v administraci
 * počítala od `<base href>`, ne od stránky.
 */

export const PARAMETR = "editorTextu";
/** Cizí web hlavičku bez povolení od nás poslat nemůže, server bez ní požadavek odmítne */
export const HLAVICKA = "X-Gamecon-Editor";

export type Akce = "nahled" | "obrazek";

/** Chyba, kterou má vidět člověk */
export class ChybaEditoru extends Error {}

export const adresaPozadavku = (akce: Akce, adresaStranky: string): string => {
  const adresa = new URL(adresaStranky);
  adresa.searchParams.set(PARAMETR, akce);
  adresa.hash = "";

  return adresa.toString();
};

type Fetch = typeof fetch;

const jeObjekt = (hodnota: unknown): hodnota is Record<string, unknown> =>
  typeof hodnota === "object" && hodnota !== null;

const posli = async (
  akce: Akce,
  telo: FormData,
  { signal, fetchFn = fetch, adresaStranky = window.location.href }: {
    signal?: AbortSignal;
    fetchFn?: Fetch;
    adresaStranky?: string;
  } = {},
): Promise<Record<string, unknown>> => {
  let odpoved: Response;
  try {
    odpoved = await fetchFn(adresaPozadavku(akce, adresaStranky), {
      method: "POST",
      body: telo,
      headers: { [HLAVICKA]: "1" },
      credentials: "same-origin",
      signal,
    });
  } catch (chyba) {
    if (signal?.aborted) {
      throw chyba;
    }
    throw new ChybaEditoru("Nepodařilo se spojit se serverem, zkus to prosím znovu.");
  }
  let data: unknown;
  try {
    data = await odpoved.json();
  } catch (chyba) {
    if (signal?.aborted) {
      throw chyba;
    }
    // místo JSON přišla stránka, třeba přihlášení po vypršení relace
    throw new ChybaEditoru(`Server odpověděl něčím, co editor nečeká (${odpoved.status}). Možná vypršelo přihlášení.`);
  }
  if (!jeObjekt(data) || data.ok !== true) {
    const zprava = jeObjekt(data) && typeof data.zprava === "string" ? data.zprava : `Chyba serveru (${odpoved.status}).`;
    throw new ChybaEditoru(zprava);
  }

  return data;
};

export type MoznostiPozadavku = Parameters<typeof posli>[2];

/**
 * Text vykreslený stejně jako na webu (Markdown Extra), s obrázky vedoucími na web. Text, který si píše uživatel sám
 * (`odUzivatele`, medailonek), vrátí server jen s vyčištěným HTML, jak ho ukazuje web. Typografie podle `jazyk` textu
 * (anglický obsah stránky má anglické uvozovky), bez něj česká.
 */
export const nactiNahled = async (
  text: string,
  {
    odUzivatele = false,
    jazyk = null,
    ...moznosti
  }: NonNullable<MoznostiPozadavku> & { odUzivatele?: boolean; jazyk?: string | null } = {},
): Promise<string> => {
  const telo = new FormData();
  telo.append("text", text);
  if (odUzivatele) {
    telo.append("odUzivatele", "1");
  }
  if (jazyk) {
    telo.append("jazyk", jazyk);
  }
  const data = await posli("nahled", telo, moznosti);
  if (typeof data.html !== "string") {
    throw new ChybaEditoru("Server neposlal náhled.");
  }

  return data.html;
};

export type NahranyObrazek = { cesta: string; markdown: string };

export const nahrajObrazek = async (
  soubor: Blob,
  nazev: string,
  moznosti?: MoznostiPozadavku,
): Promise<NahranyObrazek> => {
  const telo = new FormData();
  telo.append("obrazek", soubor, nazev);
  const data = await posli("obrazek", telo, moznosti);
  if (typeof data.cesta !== "string" || typeof data.markdown !== "string") {
    throw new ChybaEditoru("Server neposlal, kam obrázek uložil.");
  }

  return { cesta: data.cesta, markdown: data.markdown };
};
