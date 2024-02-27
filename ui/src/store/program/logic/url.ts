import { AktivitaStav } from "../../../api/program";
import { GAMECON_KONSTANTY } from "../../../env";
import { formátujDenVTýdnu, tryParseNumber } from "../../../utils";

// TODO: přidat title generátor pro daný url state, následně někde do tsx přicpat logiku měnení title stránky, title se bude generovat i přes php pro statické odkazy

export type ProgramTabulkaVýběr =
  | {
    typ: "můj";
  }
  | {
    typ: "den";
    datum: Date;
  }
  ;

export type ProgramURLState = {
  ročník: number,
  výběr: ProgramTabulkaVýběr,
  filtrPřihlašovatelné: boolean,
  aktivitaNáhledId?: number,
  filtrLinie?: string[],
  filtrTagy?: string[],
  filtrStavAktivit?: AktivitaStav[],
  filtrText?: string,
}

export const URL_STATE_VÝCHOZÍ_MOŽNOST = Object.freeze({
  typ: "den",
  datum: new Date(GAMECON_KONSTANTY.PROGRAM_OD),
});

export const URL_STATE_VÝCHOZÍ_STAV: ProgramURLState = Object.freeze({
  ročník: GAMECON_KONSTANTY.ROCNIK,
  výběr: URL_STATE_VÝCHOZÍ_MOŽNOST,
  aktivitaNáhledId: undefined,
  filtrPřihlašovatelné: false,
});

const NÁHLED_QUERY_KEY = "idAktivityNahled";
const LINIE_QUERY_KEY = "linie";
const TAGY_QUERY_KEY = "tagy";
const PŘIHLAŠOVATELNÉ_QUERY_KEY = "pouzePrihlasovatelne";
const ROCNIK_QUERY_KEY = "rocnik";
const STAVY_QUERY_KEY = "stav";
const TEXT_QUERY_KEY = "text";

export const parsujUrl = (url: string) => {
  const basePath = new URL(GAMECON_KONSTANTY.BASE_PATH_PAGE).pathname;
  const urlObj = new URL(url, GAMECON_KONSTANTY.BASE_PATH_PAGE);
  const aktivitaNáhledId = tryParseNumber(urlObj.searchParams.get(NÁHLED_QUERY_KEY));

  const den = urlObj.pathname.slice(basePath.length);

  const výběr = urlStateProgramTabulkaMožnostíDnyMůj({ přihlášen: true }).find(x => urlZTabulkaVýběr(x) === den) ?? URL_STATE_VÝCHOZÍ_MOŽNOST;
  const urlState: ProgramURLState = {
    výběr,
    aktivitaNáhledId,
    ročník: tryParseNumber(urlObj.searchParams.get(ROCNIK_QUERY_KEY)) ?? GAMECON_KONSTANTY.ROCNIK,
    filtrPřihlašovatelné: urlObj.searchParams.get(PŘIHLAŠOVATELNÉ_QUERY_KEY) === "true",
  };

  try {
    const linieRaw = urlObj.searchParams.get(LINIE_QUERY_KEY);
    if (linieRaw) {
      const linie = JSON.parse(decodeURIComponent(linieRaw));
      urlState.filtrLinie = linie;
    }
  } catch (e) { console.error(`failed to parse ${urlObj.searchParams.get(LINIE_QUERY_KEY) ?? ""}`); }
  try {
    const tagyRaw = urlObj.searchParams.get(TAGY_QUERY_KEY);
    if (tagyRaw) {
      const tagy = JSON.parse(decodeURIComponent(tagyRaw));
      urlState.filtrTagy = tagy;
    }
  } catch (e) { console.error(`failed to parse ${urlObj.searchParams.get(TAGY_QUERY_KEY) ?? ""}`); }
  try {
    const stavyRaw = urlObj.searchParams.get(STAVY_QUERY_KEY);
    if (stavyRaw) {
      const tagy = JSON.parse(decodeURIComponent(stavyRaw));
      urlState.filtrStavAktivit = tagy;
    }
  } catch (e) { console.error(`failed to parse ${urlObj.searchParams.get(STAVY_QUERY_KEY) ?? ""}`); }
  try {
    const textRaw = urlObj.searchParams.get(TEXT_QUERY_KEY);
    if (textRaw) {
      const text = JSON.parse(decodeURIComponent(textRaw));
      urlState.filtrText = text;
    }
  } catch (e) { console.error(`failed to parse ${urlObj.searchParams.get(TEXT_QUERY_KEY) ?? ""}`); }

  return urlState;
};

/** vytvoří url z aktuálního url-stavu nebo z předaného stavu */
export const generujUrl = (urlState: ProgramURLState): string | undefined => {
  const výběr =
    urlStateProgramTabulkaMožnostíDnyMůj({ přihlášen: true }).find(x => porovnejTabulkaVýběr(x, urlState.výběr));

  if (!výběr) return undefined;

  let url = GAMECON_KONSTANTY.BASE_PATH_PAGE + urlZTabulkaVýběr(výběr);

  const search: string[] = [];

  if (urlState.ročník !== GAMECON_KONSTANTY.ROCNIK)
    search.push(`${ROCNIK_QUERY_KEY}=${urlState.ročník}`);

  if (urlState.aktivitaNáhledId)
    search.push(`${NÁHLED_QUERY_KEY}=${urlState.aktivitaNáhledId}`);

  if (urlState.filtrLinie)
    search.push(`${LINIE_QUERY_KEY}=${encodeURIComponent(JSON.stringify(urlState.filtrLinie))}`);

  if (urlState.filtrTagy)
    search.push(`${TAGY_QUERY_KEY}=${encodeURIComponent(JSON.stringify(urlState.filtrTagy))}`);

  if (urlState.filtrStavAktivit)
    search.push(`${STAVY_QUERY_KEY}=${encodeURIComponent(JSON.stringify(urlState.filtrStavAktivit))}`);

  if (urlState.filtrText)
    search.push(`${TEXT_QUERY_KEY}=${encodeURIComponent(JSON.stringify(urlState.filtrText))}`);

  if (urlState.filtrPřihlašovatelné)
    search.push(`${PŘIHLAŠOVATELNÉ_QUERY_KEY}=true`);


  if (search.length)
    url += "?" + search.join("&");

  return url;
};

export const urlStateProgramTabulkaMožnostíDnyMůj = (props?: { přihlášen?: boolean, ročník?: number }): ProgramTabulkaVýběr[] =>
  GAMECON_KONSTANTY.PROGRAM_DNY
    .map((den) => ({
      typ: "den",
      datum: new Date(den),
    } as ProgramTabulkaVýběr))
    .concat(...((props?.přihlášen ?? false) ? [{ typ: "můj" } as ProgramTabulkaVýběr] : []));

const urlZTabulkaVýběr = (výběr: ProgramTabulkaVýběr) =>
  výběr.typ === "můj"
    ? "muj"
    : formátujDenVTýdnu(výběr.datum)
    ;

export const porovnejTabulkaVýběr = (v1: ProgramTabulkaVýběr, v2: ProgramTabulkaVýběr) =>
  urlZTabulkaVýběr(v1) === urlZTabulkaVýběr(v2);
