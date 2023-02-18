import { GAMECON_KONSTANTY } from "../../../env";
import { formátujDenVTýdnu, tryParseNumber } from "../../../utils";

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
  výběr: ProgramTabulkaVýběr,
  aktivitaNáhledId?: number,
  rok: number,
}

export const URL_STATE_VÝCHOZÍ_MOŽNOST = Object.freeze({
  typ: "den",
  datum: new Date(GAMECON_KONSTANTY.PROGRAM_OD),
});

const NÁHLED_QUERY_STRING = "idAktivityNahled";

export const parsujUrl = (url: string) => {
  const basePath = new URL(GAMECON_KONSTANTY.BASE_PATH_PAGE).pathname;
  const urlObj = new URL(url, GAMECON_KONSTANTY.BASE_PATH_PAGE);
  const aktivitaNáhledId = tryParseNumber(urlObj.searchParams.get(NÁHLED_QUERY_STRING));

  const den = urlObj.pathname.slice(basePath.length);

  const výběr = tabulkaMožnostíUrlStateProgram({ přihlášen: true }).find(x => urlZTabulkaVýběr(x) === den) ?? URL_STATE_VÝCHOZÍ_MOŽNOST;
  const urlState: ProgramURLState = {
    výběr,
    aktivitaNáhledId,
    rok: GAMECON_KONSTANTY.ROCNIK,
  };
  return urlState;
};

/** vytvoří url z aktuálního url-stavu nebo z předaného stavu */
export const generujUrl = (urlState: ProgramURLState): string | undefined => {
  const výběr =
    tabulkaMožnostíUrlStateProgram({ přihlášen: true }).find(x => porovnejTabulkaVýběr(x, urlState.výběr));

  if (!výběr) return undefined;

  let url = GAMECON_KONSTANTY.BASE_PATH_PAGE + urlZTabulkaVýběr(výběr);

  const search: string[] = [];

  if (urlState.aktivitaNáhledId)
    search.push(`${NÁHLED_QUERY_STRING}=${urlState.aktivitaNáhledId}`);

  if (search.length)
    url += "?" + search.join("&");

  return url;
};

export const tabulkaMožnostíUrlStateProgram = (props?: { přihlášen?: boolean }): ProgramTabulkaVýběr[] =>
  GAMECON_KONSTANTY.PROGRAM_DNY
    .map((den) => ({
      typ: "den",
      datum: new Date(den),
    } as ProgramTabulkaVýběr))
    .concat(...((props?.přihlášen ?? false) ? [{ typ: "můj" } as ProgramTabulkaVýběr] : []));

const urlZTabulkaVýběr = (výběr: ProgramTabulkaVýběr) =>
  (výběr.typ === "můj"
    ? "muj"
    : formátujDenVTýdnu(výběr.datum));

export const porovnejTabulkaVýběr = (v1: ProgramTabulkaVýběr, v2: ProgramTabulkaVýběr) =>
  urlZTabulkaVýběr(v1) === urlZTabulkaVýběr(v2);
